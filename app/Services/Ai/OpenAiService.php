<?php

namespace App\Services\Ai;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use RuntimeException;

class OpenAiService
{
    protected string $chat = 'https://api.openai.com/v1/chat/completions';
    protected string $image = 'https://api.openai.com/v1/images/generations';

    /* ================================
     | GENERIC EXPLAIN
     ================================ */
    public function explain(array $payload, AiMode $mode, int $ttl = 5): array
    {
        return Cache::remember(
            'ai:' . md5(json_encode($payload)),
            now()->addMinutes($ttl),
            fn() => $this->runExplain($payload)
        );
    }

    protected function runExplain(array $payload): array
    {
        $raw = $this->chat(
            $this->systemPrompt($payload),
            json_encode($payload)
        );

        return $this->safeJson($raw);
    }

    /* ================================
     | TENUN GUIDE (TEXT + IMAGE)
     ================================ */
    public function tenunGuide(array $payload): array
    {
        $text = $this->runExplain($payload);

        $image = null;

        try {
            $image = $this->generateTenunImage($payload['context']);
        } catch (\Throwable $e) {
            logger()->warning('TENUN IMAGE FAILED');
        }

        return array_merge([
            'image' => $image
        ], $this->normalizeTenun($text));
    }

    /* ================================
     | CHAT
     ================================ */
    protected function chat(string $system, string $user): string
    {
        $res = Http::withToken(config('services.openai.key'))
            ->timeout(120)
            ->post($this->chat, [
                'model' => 'gpt-4.1-mini',
                'temperature' => 0.2,
                'messages' => [
                    ['role'=>'system','content'=>$system],
                    ['role'=>'user','content'=>$user],
                ]
            ]);

        if (!$res->successful()) {
            throw new RuntimeException($res->body());
        }

        return $res->json('choices.0.message.content','');
    }

    /* ================================
     | IMAGE
     ================================ */
    protected function generateTenunImage(array $ctx): ?string
    {
        $prompt = "
    Flat technical weaving diagram.
    Balinese songket motif.
    Motif width {$ctx['motif_width_lungsin']} warp.
    Motif height {$ctx['motif_height_pakan']} weft.
    Colors: " . implode(', ', $ctx['motif_colors']) . ".
    White background, grid, schematic, instructional.
    No realism, no people, no shadows.
    ";

        $res = Http::withToken(config('services.openai.key'))
            ->timeout(120)
            ->post('https://api.openai.com/v1/images/generations', [
                'model' => 'gpt-image-1.5',
                'prompt' => $prompt,
                'quality' => 'low',  // paling murah
                'size' => '1024x1024'
            ]);

        if (!$res->successful()) {
            logger()->error('IMAGE GENERATION FAILED', [
                'status' => $res->status(),
                'body' => $res->body()
            ]);
            return null;
        }

        return $res->json('data.0.url');
    }

    /* ================================
     | PROMPT
     ================================ */
    protected function systemPrompt(array $payload): string
    {
        if(($payload['meta']['purpose'] ?? '') === 'tenun_guide') {

            return <<<PROMPT
Kamu penenun songket Bali senior.

WAJIB JSON:

summary
loom_setup:
 total_lungsin
 motif_start_lungsin
 motif_width_lungsin
 motif_height_pakan
 motif_thread

weaving_steps[]:
 row
 angkat_lungsin
 masukkan_benang_motif
 cara_memadatkan
 kunci_pakan_biasa

tips_penenun[]
kesalahan_umum[]

Teknis. Baris demi baris. Tidak abstrak.
PROMPT;
        }

        return 'Kembalikan JSON dengan summary.';
    }

    /* ================================
     | JSON
     ================================ */
    protected function safeJson(string $raw): array
    {
        $raw = trim($raw);

        if(!str_starts_with($raw,'{')) throw new RuntimeException();

        $json = json_decode($raw,true);

        if(!$json) throw new RuntimeException();

        return $json;
    }

    protected function normalizeTenun(array $data): array
    {
        return array_merge([
            'summary'=>'',
            'loom_setup'=>[],
            'weaving_steps'=>[],
            'tips_penenun'=>[],
            'kesalahan_umum'=>[]
        ],$data);
    }
}
