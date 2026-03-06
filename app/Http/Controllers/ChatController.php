<?php

namespace App\Http\Controllers;

use App\Models\Topic;
use App\Models\ChatMessage;
use App\Models\WastraKnowledge;
use Illuminate\Http\Request;
use OpenAI\Laravel\Facades\OpenAI;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ChatController extends Controller {

    public function getTopics() {
        return Topic::where('user_id', auth()->id())->latest()->get();
    }

    public function getMessages($topicId) {
        return ChatMessage::where('topic_id', $topicId)->oldest()->get();
    }

    public function createTopic() {
        return Topic::create([
            'user_id' => auth()->id(),
            'title' => 'Percakapan ' . now()->format('d M H:i')
        ]);
    }

    public function ask(Request $request) {
        $request->validate(['message' => 'required', 'topic_id' => 'required']);

        // Matikan buffering agar streaming lancar di Apache/Nginx
        if (function_exists('ini_set')) {
            ini_set('output_buffering', 'off');
            ini_set('zlib.output_compression', false);
        }

        $lowerMessage = strtolower($request->message);

        // 1. Deteksi Niat: Membuat Gambar Desain Baru
        $isImageRequest = Str::contains($lowerMessage, ['buatkan gambar', 'generate image', 'lukis', 'gambarkan', 'desain']);
        if ($isImageRequest) {
            return $this->generateImage($request);
        }

        // 2. Deteksi Niat: Meminta Langkah Teknis (Baris Demi Baris)
        $isTechnicalGuide = Str::contains($lowerMessage, ['langkah', 'instruksi', 'cara membuat', 'teknis', 'baris demi baris']);
        if ($isTechnicalGuide) {
            return $this->generateTechnicalGuide($request);
        }

        // 3. Respon Teks Biasa (Streaming)
        return $this->streamTextResponse($request);
    }

    /**
     * PERBAIKAN: Menggunakan Base64 agar OpenAI bisa membaca gambar dari Localhost/Private VPS
     */
    private function generateTechnicalGuide($request) {
        try {
            $lastImage = ChatMessage::where('topic_id', $request->topic_id)
                ->where('role', 'assistant')
                ->where('type', 'image')
                ->latest()
                ->first();

            if (!$lastImage) {
                return response()->json(['error' => true, 'message' => 'Silakan buat desain desain terlebih dahulu.'], 400);
            }

            // Konversi gambar ke Base64 (Solusi agar OpenAI bisa 'melihat' gambar Anda)
            $path = storage_path('app/public/' . str_replace('storage/', '', $lastImage->image_path));
            if (!file_exists($path)) {
                return response()->json(['error' => true, 'message' => 'File gambar tidak ditemukan.'], 404);
            }
            
            $type = pathinfo($path, PATHINFO_EXTENSION);
            $data = file_get_contents($path);
            $base64 = 'data:image/' . $type . ';base64,' . base64_encode($data);

            $response = OpenAI::chat()->create([
                'model' => 'gpt-4o', 
                'messages' => [
                    [
                        'role' => 'system', 
                        'content' => 'Anda adalah instruktur penenunan teknis Wastra Bali. Analisis gambar yang diberikan dan berikan instruksi teknis BARIS DEMI BARIS sesuai format PDF panduan teknis (Baris, Instruksi Teknis, Warna). Gunakan istilah lungsin dan pakan.'
                    ],
                    [
                        'role' => 'user',
                        'content' => [
                            ['type' => 'text', 'text' => $request->message],
                            ['type' => 'image_url', 'image_url' => ['url' => $base64]], 
                        ],
                    ],
                ],
            ]);

            $fullContent = $response->choices[0]->message->content;

            // Simpan Riwayat Chat
            ChatMessage::create(['topic_id' => $request->topic_id, 'role' => 'user', 'content' => $request->message]);
            ChatMessage::create(['topic_id' => $request->topic_id, 'role' => 'assistant', 'content' => $fullContent]);

            return response()->json(['type' => 'text', 'content' => $fullContent]);

        } catch (\Exception $e) {
            return response()->json(['error' => true, 'message' => 'Gagal menganalisis desain: ' . $e->getMessage()], 500);
        }
    }

    private function generateImage($request) {
        try {
            $translatedPrompt = $this->translatePrompt($request->message);

            $result = OpenAI::images()->create([
                'model' => 'dall-e-3',
                'prompt' => $translatedPrompt . " in the style of authentic Balinese Sidemen Songket weaving, highly detailed textile texture.",
                'n' => 1,
                'size' => '1024x1024',
            ]);

            $url = $result->data[0]->url;
            $imageContent = file_get_contents($url);
            $fileName = 'ai_images/ai_' . Str::random(10) . '.png';
            
            Storage::disk('public')->put($fileName, $imageContent);
            $dbPath = 'storage/' . $fileName; 

            ChatMessage::create(['topic_id' => $request->topic_id, 'role' => 'user', 'content' => $request->message]);
            ChatMessage::create([
                'topic_id' => $request->topic_id,
                'role' => 'assistant',
                'type' => 'image',
                'image_path' => $dbPath,
                'content' => 'Berikut adalah desain wastra baru yang saya buatkan untuk Anda.'
            ]);

            return response()->json([
                'type' => 'image', 
                'image_path' => asset($dbPath),
                'content' => 'Berikut adalah desain wastra baru yang saya buatkan untuk Anda.'
            ]);

        } catch (\Exception $e) {
            return response()->json(['error' => true, 'message' => 'Gagal membuat gambar: ' . $e->getMessage()], 500);
        }
    }

    private function streamTextResponse($request) {
        return response()->stream(function () use ($request) {
            $history = ChatMessage::where('topic_id', $request->topic_id)
                ->latest()->limit(10)->get()->reverse()->values()
                ->map(fn($msg) => ['role' => $msg->role, 'content' => $msg->content])
                ->toArray();

            $history[] = ['role' => 'user', 'content' => $request->message];

            $stream = OpenAI::chat()->createStreamed([
                'model' => 'gpt-3.5-turbo',
                'messages' => $history,
            ]);

            $fullContent = "";
            foreach ($stream as $response) {
                $text = $response->choices[0]->delta->content ?? '';
                if ($text) {
                    echo "data: " . json_encode(['text' => $text]) . "\n\n";
                    $fullContent .= $text;
                    if (connection_aborted()) break;
                    ob_flush(); flush();
                }
            }

            ChatMessage::create(['topic_id' => $request->topic_id, 'role' => 'user', 'content' => $request->message]);
            ChatMessage::create(['topic_id' => $request->topic_id, 'role' => 'assistant', 'content' => $fullContent]);
            
            echo "data: [DONE]\n\n";
        }, 200, [
            'Content-Type' => 'text/event-stream',
            'X-Accel-Buffering' => 'no',
            'Cache-Control' => 'no-cache',
        ]);
    }

    private function translatePrompt($text) {
        $response = OpenAI::chat()->create([
            'model' => 'gpt-3.5-turbo',
            'messages' => [
                ['role' => 'system', 'content' => 'Translate the user input into a highly descriptive English prompt for DALL-E 3 image generation. Focus on Balinese songket textile details.'],
                ['role' => 'user', 'content' => $text]
            ],
        ]);
        return $response->choices[0]->message->content;
    }
}