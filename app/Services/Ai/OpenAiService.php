<?php

namespace App\Services\Ai;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Illuminate\Support\Facades\Storage;

class OpenAiService
{
    protected string $chatEndpoint = 'https://api.openai.com/v1/chat/completions';
    protected string $imageEndpoint = 'https://api.openai.com/v1/images/generations';

    /**
     * Menjalankan AI untuk memberikan insight dengan caching.
     */
    public function explain(array $payload, AiMode $mode, int $ttl = 5): array
    {
        return Cache::remember(
            'ai_insight:' . md5(json_encode($payload) . $mode->value),
            now()->addMinutes($ttl),
            fn() => $this->runExplain($payload)
        );
    }

    /**
     * Menghasilkan panduan tenun lengkap dengan gambar skema.
     */
    public function tenunGuide(array $payload): array
    {
        $textResult = $this->runExplain($payload);
        $imageUrl = null;

        try {
            $imageUrl = $this->generateTenunImage($payload['context']);
        } catch (\Throwable $e) {
            Log::warning('AI Image Generation Failed: ' . $e->getMessage());
        }

        return array_merge(
            ['image' => $imageUrl],
            $this->normalizeTenun($textResult)
        );
    }

    protected function chat(string $system, string $user): string
    {
        $apiKey = config('services.openai.key');
        if (!$apiKey) throw new RuntimeException('OpenAI API Key is not set.');

        $res = Http::withToken($apiKey)
            ->timeout(60)
            ->post($this->chatEndpoint, [
                'model' => 'gpt-4o-mini', // Model paling efisien & mendukung JSON Mode
                'temperature' => 0.3,
                'response_format' => ['type' => 'json_object'], // Memastikan output JSON valid
                'messages' => [
                    ['role' => 'system', 'content' => $system],
                    ['role' => 'user', 'content' => $user],
                ]
            ]);

        if (!$res->successful()) {
            Log::error('OpenAI API Error', ['body' => $res->body()]);
            throw new RuntimeException("AI Chat Service Unavailable.");
        }

        return $res->json('choices.0.message.content', '{}');
    }

    protected function generateTenunImage(array $ctx): ?string
    {
        $prompt = "Traditional Balinese Songket textile with {$ctx['design_name']} motif, " .
          "repeating symmetrical pattern, colors " . implode(', ', $ctx['motif_colors']) . ", " .
          "woven fabric texture, intricate details, high detail, seamless pattern, " .
          "ethnic textile design, no grid, no blueprint, no text";

        $res = Http::withToken(config('services.openai.key'))->post($this->imageEndpoint, [
            'model' => 'dall-e-2',
            'prompt' => $prompt,
            'n' => 1,
            'size' => '512x512'
        ]);

        if (!$res->successful()) return null;

        $externalUrl = $res->json('data.0.url');
        
        // DOWNLOAD & SIMPAN PERMANEN
        $imageContent = file_get_contents($externalUrl);
        $fileName = 'ai-results/' . uniqid() . '.png';
        Storage::disk('public')->put($fileName, $imageContent);

        // Kembalikan path lokal
        return Storage::url($fileName);
    }

    protected function systemPrompt(array $payload): string
    {
        $ctx = $payload['context'];
        $colors = implode(', ', $ctx['motif_colors']);
        $motifDesc = $ctx['motif'] ?? 'Motif tradisional';
        $lebar = $ctx['motif_width_lungsin'];
        $tinggi = $ctx['motif_height_pakan'];

        // Menghitung area lungsin secara dinamis
        $center = 600; 
        $start = $center - ($lebar / 2);
        $end = $center + ($lebar / 2);

        return "Anda adalah Guru Besar Tenun Sidemen. Tugas: Buat panduan teknis untuk motif '{$ctx['design_name']}' ({$motifDesc}).
        
        DATA INPUT FORM:
        - Lebar Motif: {$lebar} lungsin (Gunakan rentang nomor {$start} sampai {$end}).
        - Tinggi Motif: {$tinggi} baris pakan.
        - Warna Benang: {$colors}.

        ATURAN TEKNIS SANGAT KETAT:
        1. WAJIB bagi menjadi minimal 30 baris instruksi unik. Jangan menggabungkan baris (Contoh: 1-10) jika polanya berubah.
        2. Setiap baris HARUS menyebutkan nomor lungsin spesifik berdasarkan gambar referensi.
        3. Ikuti alur pertumbuhan motif: 
        - Baris 1-10: Tahap Dasar/Kaki (Mulai dari tengah).
        - Baris 11-{$tinggi}: Tahap Badan/Pelebaran sesuai bentuk di gambar referensi.
        4. Setiap baris WAJIB diakhiri kalimat: 'Kunci dengan 2 pakan polos'.

        WAJIB JSON FORMAT:
        {
        \"summary\": \"Analisis teknis motif berdasarkan gambar referensi.\",
        \"weaving_steps\": [
            {\"row\": \"1\", \"lift\": \"{$center}-" . ($center+1) . "\", \"instruction\": \"Titik awal sesuai referensi, masukkan pakan, kunci 2 pakan polos.\", \"thread_color\": \"{$colors}\"},
            ... (lanjutkan sampai baris {$tinggi})
        ],
        \"tips_ahli\": [\"Pastikan perpindahan warna {$colors} halus.\"],
        \"perhatian\": [\"Gunakan 2 pakan kunci agar motif tidak naik.\"]
        }";
    }

    protected function runExplain(array $payload): array
    {
        // Menggunakan model Vision agar AI bisa 'melihat' gambar referensi
        $messages = [
            ['role' => 'system', 'content' => $this->systemPrompt($payload)],
        ];

        // Jika ada gambar referensi dari user, kirimkan ke AI
        if (!empty($payload['context']['reference_image_url'])) {
            $messages[] = [
                'role' => 'user',
                'content' => [
                    ['type' => 'text', 'text' => 'Gunakan gambar ini sebagai referensi utama pola tenun:'],
                    ['type' => 'image_url', 'image_url' => ['url' => $payload['context']['reference_image_url']]]
                ]
            ];
        } else {
            $messages[] = ['role' => 'user', 'content' => json_encode($payload)];
        }

        $res = Http::withToken(config('services.openai.key'))->post($this->chatEndpoint, [
            'model' => 'gpt-4o', // Model yang bisa melihat gambar
            'temperature' => 0.2,
            'response_format' => ['type' => 'json_object'],
            'messages' => $messages
        ]);

        return json_decode($res->json('choices.0.message.content', '{}'), true);
    }

    protected function safeJson(string $raw): array
    {
        $data = json_decode($raw, true);
        return (json_last_error() === JSON_ERROR_NONE) ? $data : ['error' => 'Invalid JSON format from AI'];
    }

    protected function normalizeTenun(array $data): array
    {
        return array_merge([
            'summary' => '',
            'loom_setup' => ['total_lungsin' => 1200],
            'weaving_steps' => [],
            'tips_penenun' => [],
            'kesalahan_umum' => []
        ], $data);
    }
}