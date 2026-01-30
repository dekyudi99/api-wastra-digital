<?php

namespace App\Services\Ai;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use App\Models\AiInsightLog;
use RuntimeException;

class OpenAiService
{
    protected string $endpoint = 'https://api.openai.com/v1/chat/completions';

    /* =========================================================
     | PUBLIC API
     ========================================================= */
    public function explain(array $payload, AiMode $mode, int $ttlMinutes = 10): array
    {
        $cacheKey = $this->cacheKey($mode, $payload);

        return Cache::remember($cacheKey, now()->addMinutes($ttlMinutes), function () use ($payload, $mode) {

            $systemPrompt = $this->systemPrompt($mode, $payload);
            $userPrompt   = json_encode($payload, JSON_PRETTY_PRINT);

            try {
                $raw = $this->callOpenAi($systemPrompt, $userPrompt);
                $parsed = $this->safeJsonParse($raw);

                // 🚨 VALIDASI STRUKTUR
                $validated = $this->validateResponse($parsed, $payload);

                // 🧾 LOG SUKSES
                $this->logInsight($mode, $payload, $validated);

                return $validated;

            } catch (\Throwable $e) {

                // 🧾 LOG ERROR
                $this->logInsight($mode, $payload, [
                    'error' => true,
                    'message' => $e->getMessage(),
                ]);

                // 🛟 FAIL-SAFE RESPONSE
                return $this->fallbackResponse($payload);
            }
        });
    }

    /* =========================================================
     | OPENAI CALL
     ========================================================= */
    protected function callOpenAi(string $system, string $user): string
    {
        $res = Http::withToken(config('services.openai.key'))
            ->timeout(30)
            ->post($this->endpoint, [
                'model' => 'gpt-4.1-mini',
                'temperature' => 0.4,
                'messages' => [
                    ['role' => 'system', 'content' => $system],
                    ['role' => 'user', 'content' => $user],
                ],
            ]);

        if (!$res->successful()) {
            throw new RuntimeException('OpenAI request failed');
        }

        return data_get($res->json(), 'choices.0.message.content', '');
    }

    /* =========================================================
     | PROMPT
     ========================================================= */
    protected function systemPrompt(AiMode $mode, array $payload): string
    {
        return match ($payload['meta']['purpose'] ?? 'overview') {

            'tenun_guide' => <<<PROMPT
            Kamu adalah penenun tradisional senior dari Bali
            yang berpengalaman membuat motif songket dengan benang pakan tambahan.

            TUGASMU:
            Memberikan panduan teknis menenun motif songket
            seperti penjelasan dari penenun ke penenun.

            GAYA BAHASA (WAJIB):
            - Teknis
            - Sederhana
            - Runtut
            - Gunakan istilah kerja penenun (lungsin, pakan, kunci, padatkan)
            - Jangan gunakan istilah desain atau penjelasan abstrak

            WAJIB mengembalikan JSON VALID dengan struktur berikut:

            {
            "summary": string,
            "loom_setup": {
                "total_lungsin": number,
                "motif_start_lungsin": number,
                "motif_width_lungsin": number,
                "motif_height_pakan": number,
                "motif_thread": string
            },
            "weaving_steps": [
                {
                "row": number,
                "angkat_lungsin": string,
                "masukkan_benang_motif": boolean,
                "cara_memadatkan": string,
                "kunci_pakan_biasa": number
                }
            ],
            "tips_penenun": string[],
            "kesalahan_umum": string[]
            }

            ATURAN KETAT:
            - Jangan menyingkat langkah
            - Jangan melompati baris
            - Setiap baris motif HARUS dijelaskan
            - Jika ada data yang tidak disebutkan,
            buat asumsi teknis yang masuk akal dan jelaskan secara praktis
            PROMPT,

            'product_health_score' => <<<PROMPT
            Kamu adalah AI analis kesehatan produk untuk pengrajin wastra.
            WAJIB mengembalikan JSON VALID:

            {
            "summary": string,
            "products": [
                {
                "product_id": number,
                "product_name": string,
                "health_score": number,
                "status": "healthy" | "stable" | "weak" | "critical",
                "main_factors": string[],
                "recommended_action": string
                }
            ],
            "general_notes": string[]
            }

            JANGAN mengembalikan response kosong.
            PROMPT,

                        'stock_and_discount_optimization' => <<<PROMPT
            Kamu adalah AI optimasi stok & diskon untuk pengrajin wastra.
            WAJIB mengembalikan JSON VALID:

            {
            "summary": string,
            "actions": [
                {
                "product_id": number,
                "product_name": string,
                "stock_action": "increase" | "decrease" | "hold",
                "discount_action": "increase" | "decrease" | "hold",
                "suggested_discount": number,
                "reason": string
                }
            ],
            "general_strategy": string[]
            }
            PROMPT,

            default => <<<PROMPT
            Kamu adalah AI analis marketplace wastra Bali.
            WAJIB mengembalikan JSON VALID:

            {
            "summary": string
            }
            PROMPT
        };
    }

    /* =========================================================
     | SAFETY & VALIDATION
     ========================================================= */
    protected function safeJsonParse(string $raw): array
    {
        $raw = trim($raw);

        if (!str_starts_with($raw, '{')) {
            throw new RuntimeException('AI response is not JSON');
        }

        $decoded = json_decode($raw, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new RuntimeException('Invalid JSON from AI');
        }

        return $decoded;
    }

    protected function validateResponse(array $data, array $payload): array
    {
        $purpose = $payload['meta']['purpose'] ?? 'overview';

        return match ($purpose) {
            'tenun_guide' => array_merge([
                'summary' => '',
                'loom_setup' => [
                    'total_lungsin' => 0,
                    'motif_start_lungsin' => 0,
                    'motif_width_lungsin' => 0,
                    'motif_height_pakan' => 0,
                    'motif_thread' => '',
                ],
                'weaving_steps' => [],
                'tips_penenun' => [],
                'kesalahan_umum' => [],
            ], $data),

            'product_health_score' =>
                array_merge([
                    'summary' => '',
                    'products' => [],
                    'general_notes' => [],
                ], $data),

            'stock_and_discount_optimization' =>
                array_merge([
                    'summary' => '',
                    'actions' => [],
                    'general_strategy' => [],
                ], $data),

            default =>
                array_merge(['summary' => ''], $data),
        };
    }

    protected function fallbackResponse(array $payload): array
    {
        return match ($payload['meta']['purpose'] ?? 'overview') {
            'tenun_guide' => [
                'summary' => 'Panduan teknis belum dapat dihasilkan saat ini.',
                'loom_setup' => [
                    'total_lungsin' => 1200,
                    'motif_start_lungsin' => 0,
                    'motif_width_lungsin' => 0,
                    'motif_height_pakan' => 0,
                    'motif_thread' => '',
                ],
                'weaving_steps' => [],
                'tips_penenun' => [
                    'Pastikan benang lungsin rata dan tidak ada yang kendur.',
                ],
                'kesalahan_umum' => [
                    'Benang motif ditarik terlalu kuat.',
                ],
            ],
            'product_health_score' => [
                'summary' => 'Insight kesehatan produk belum tersedia.',
                'products' => [],
                'general_notes' => [],
            ],
            'stock_and_discount_optimization' => [
                'summary' => 'Insight stok & diskon belum tersedia.',
                'actions' => [],
                'general_strategy' => [],
            ],
            default => [
                'summary' => 'Insight belum tersedia saat ini.',
            ],
        };
    }

    /* =========================================================
     | LOGGING
     ========================================================= */
    protected function logInsight(AiMode $mode, array $payload, array $response): void
    {
        AiInsightLog::create([
            'mode'           => $mode->value,
            'endpoint'       => request()->path(),
            'payload_hash'   => md5(json_encode($payload)),
            'payload'        => $payload,
            'response'       => $response,
            'prompt_version' => PromptVersion::get(),
        ]);
    }

    protected function cacheKey(AiMode $mode, array $payload): string
    {
        return 'aiinsight:' . $mode->value . ':' . md5(json_encode($payload));
    }
}