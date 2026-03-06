<?php

namespace App\Http\Controllers;

use App\Models\Topic;
use App\Models\ChatMessage;
use App\Models\WastraKnowledge; // Pastikan Model ini sudah dibuat
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

        // Matikan buffering PHP agar streaming lancar di Apache
        if (function_exists('ini_set')) {
            ini_set('output_buffering', 'off');
            ini_set('zlib.output_compression', false);
        }

        $lowerMessage = strtolower($request->message);

        // 1. Deteksi Niat: Membuat Gambar Desain Baru
        $isImageRequest = Str::contains($lowerMessage, [
            'buatkan gambar', 'generate image', 'lukis', 'gambarkan', 'desain'
        ]);

        if ($isImageRequest) {
            return $this->generateImage($request);
        }

        // 2. Deteksi Niat: Meminta Langkah Teknis Penenunan (Baris Demi Baris)
        $isTechnicalGuide = Str::contains($lowerMessage, [
            'langkah', 'instruksi', 'cara membuat', 'teknis', 'baris demi baris'
        ]);

        if ($isTechnicalGuide) {
            return $this->generateTechnicalGuide($request);
        }

        // 3. Respon Teks Biasa (Streaming)
        return $this->streamTextResponse($request);
    }

    /**
     * ALUR: Analisis desain terakhir dan berikan instruksi teknis Baris-Demi-Baris
     */
    private function generateTechnicalGuide($request) {
        try {
            // Ambil pesan terakhir
            $lastImage = ChatMessage::where('topic_id', $request->topic_id)
                ->where('role', 'assistant')
                ->where('type', 'image')
                ->latest()
                ->first();

            if (!$lastImage) {
                return response()->json(['error' => true, 'message' => 'Buat desain dulu.'], 400);
            }

            // Pastikan OpenAI merespon sebelum koneksi timeout
            $response = OpenAI::chat()->create([
                'model' => 'gpt-4o', 
                'messages' => [
                    ['role' => 'system', 'content' => 'Berikan instruksi baris demi baris.'],
                    [
                        'role' => 'user',
                        'content' => [
                            ['type' => 'text', 'text' => $request->message],
                            ['type' => 'image_url', 'image_url' => ['url' => asset($lastImage->image_path)]],
                        ],
                    ],
                ],
            ]);

            return response()->json(['type' => 'text', 'content' => $response->choices[0]->message->content]);

        } catch (\PDOException $e) {
            // Error koneksi database Laragon
            return response()->json(['error' => true, 'message' => 'MySQL Laragon terputus.'], 500);
        } catch (\Exception $e) {
            // Error OpenAI atau lainnya
            return response()->json(['error' => true, 'message' => 'Gagal memproses gambar: ' . $e->getMessage()], 500);
        }
    }

    /**
     * ALUR: Membuat gambar dengan DALL-E 3
     */
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

    /**
     * ALUR: Streaming teks biasa (GPT-3.5 / GPT-4o)
     */
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