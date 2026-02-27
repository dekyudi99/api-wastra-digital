<?php

namespace App\Http\Controllers;

use App\Models\Topic;
use App\Models\ChatMessage;
use Illuminate\Http\Request;
use OpenAI\Laravel\Facades\OpenAI;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ChatController extends Controller {

    // Ambil semua topik untuk Sidebar
    public function getTopics() {
        return Topic::where('user_id', auth()->id())->latest()->get();
    }

    // Ambil riwayat chat berdasarkan topik yang diklik
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

        // 1. Deteksi Niat User (Intent Detection)
        $isImageRequest = Str::contains(strtolower($request->message), [
            'buatkan gambar', 'generate image', 'lukis', 'gambarkan', 'tampilkan gambar'
        ]);

        if ($isImageRequest) {
            return $this->generateImage($request);
        }

        // 2. Jika Teks: Jalankan Streaming Response
        return response()->stream(function () use ($request) {
            // Perbaikan Error: Gunakan values() untuk mereset indeks array
            $history = ChatMessage::where('topic_id', $request->topic_id)
                ->latest()
                ->limit(10)
                ->get()
                ->reverse()
                ->values() 
                ->map(fn($msg) => [
                    'role' => $msg->role, 
                    'content' => $msg->content
                ])
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

            // Simpan Riwayat Chat Teks ke Database
            ChatMessage::create(['topic_id' => $request->topic_id, 'role' => 'user', 'content' => $request->message]);
            ChatMessage::create(['topic_id' => $request->topic_id, 'role' => 'assistant', 'content' => $fullContent]);
            
            echo "data: [DONE]\n\n";
        }, 200, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache',
            'Connection' => 'keep-alive',
        ]);
    }

    private function generateImage($request) {
        try {
            // 1. Terjemahkan Prompt ke Bahasa Inggris secara internal agar DALL-E lebih akurat
            $translatedPrompt = $this->translatePrompt($request->message);

            // 2. Panggil DALL-E 3
            $result = OpenAI::images()->create([
                'model' => 'dall-e-3',
                'prompt' => $translatedPrompt,
                'n' => 1,
                'size' => '1024x1024',
            ]);

            $url = $result->data[0]->url;
            $imageContent = file_get_contents($url);
            $fileName = 'ai_images/ai_' . Str::random(10) . '.png';
            
            Storage::disk('public')->put($fileName, $imageContent);
            $dbPath = 'storage/' . $fileName; 

            // 3. Simpan Riwayat
            ChatMessage::create([
                'topic_id' => $request->topic_id, 
                'role' => 'user', 
                'content' => $request->message
            ]);

            ChatMessage::create([
                'topic_id' => $request->topic_id,
                'role' => 'assistant',
                'type' => 'image',
                'image_path' => $dbPath,
                'content' => 'Berikut adalah gambar yang Anda minta.'
            ]);

            return response()->json([
                'type' => 'image', 
                'image_path' => asset($dbPath),
                'content' => 'Berikut adalah gambar yang Anda minta.'
            ]);

        } catch (\OpenAI\Exceptions\ErrorException $e) {
            // Tangani jika ditolak oleh Safety System
            return response()->json([
                'error' => true,
                'message' => 'Maaf, permintaan gambar Anda ditolak oleh sistem keamanan OpenAI karena mengandung konten sensitif.'
            ], 400);
        } catch (\Exception $e) {
            return response()->json([
                'error' => true,
                'message' => 'Terjadi kesalahan teknis: ' . $e->getMessage()
            ], 500);
        }
    }

    private function translatePrompt($text) {
        $response = OpenAI::chat()->create([
            'model' => 'gpt-3.5-turbo',
            'messages' => [
                ['role' => 'system', 'content' => 'Translate the user input into a highly descriptive English prompt for DALL-E 3 image generation. Only output the translation.'],
                ['role' => 'user', 'content' => $text]
            ],
        ]);
        return $response->choices[0]->message->content;
    }
}
