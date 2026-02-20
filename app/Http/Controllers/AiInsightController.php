<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\TenunGuide;
use Illuminate\Http\Request;
use App\Services\Ai\OpenAiService;
use App\Services\Ai\AiMode;
use App\Http\Resources\ApiResponseDefault;


class AiInsightController extends Controller
{
    public function buyerInsight(OpenAiService $ai)
    {
        $products = Product::withAvg('review as rating', 'rating')
            ->orderByDesc('sales')
            ->limit(5)
            ->get();

        $result = $ai->explain([
            'meta' => ['purpose' => 'buyer'],
            'products' => $products
        ], AiMode::BUYER);

        return new ApiResponseDefault(true, 'Success', $result);
    }

    public function tenunGuide(OpenAiService $ai, Request $request)
    {
        $data = $request->validate([
            'design_name' => 'required|string|max:255',
            'motif_width_lungsin' => 'required|integer|min:1',
            'motif_height_pakan' => 'required|integer|min:1',
            'motif_colors' => 'required|array',
            'reference_image' => 'nullable|image|max:2048'
        ]);

        $path = $request->hasFile('reference_image') 
            ? $request->file('reference_image')->store('tenun-guides', 'public') 
            : null;

        $payload = [
            'meta' => ['purpose' => 'tenun_guide'],
            'context' => array_merge($data, ['total_lungsin' => 1200])
        ];

        $result = $ai->tenunGuide($payload);

        // Pastikan kolom ai_result di database bertipe JSON/Text
        TenunGuide::create([
            'user_id' => auth()->id(),
            'design_name' => $data['design_name'],
            'motif_width_lungsin' => $data['motif_width_lungsin'],
            'motif_height_pakan' => $data['motif_height_pakan'],
            'motif_colors' => $data['motif_colors'],
            'reference_image_path' => $path,
            'ai_result' => $result
        ]);

        return new ApiResponseDefault(true, 'Guide Generated', $result);
    }
}