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
        $products = Product::withAvg('review as rating','rating')
            ->orderByDesc('sales')->limit(5)->get();

        $payload = [
            'meta'=>['purpose'=>'buyer'],
            'products'=>$products
        ];

        return new ApiResponseDefault(true,'OK',$ai->explain($payload,AiMode::BUYER));
    }

    public function sellerInsight(OpenAiService $ai)
    {
        $products = Product::where('artisan_id',auth()->id())
            ->withAvg('review as rating','rating')
            ->get();

        return new ApiResponseDefault(true,'OK',
            $ai->explain([
                'meta'=>['purpose'=>'seller'],
                'products'=>$products
            ],AiMode::SELLER)
        );
    }

    public function stockAndDiscountInsight(OpenAiService $ai)
    {
        return $this->sellerInsight($ai);
    }

    public function productHealthScore(OpenAiService $ai)
    {
        return $this->sellerInsight($ai);
    }

    /* ================= TENUN GUIDE ================= */

    public function tenunGuide(OpenAiService $ai, Request $request)
    {
        $data = $request->validate([
            'design_name'=>'required',
            'motif_width_lungsin'=>'required|int',
            'motif_height_pakan'=>'required|int',
            'motif_colors'=>'required|array',
            'reference_image'=>'nullable|image'
        ]);

        $path = null;

        if($request->hasFile('reference_image')) {
            $path = $request->file('reference_image')->store('tenun-guides','public');
        }

        $payload = [
            'meta'=>['purpose'=>'tenun_guide'],
            'context'=>[
                'design_name'=>$data['design_name'],
                'total_lungsin'=>1200,
                'motif_width_lungsin'=>$data['motif_width_lungsin'],
                'motif_height_pakan'=>$data['motif_height_pakan'],
                'motif_colors'=>$data['motif_colors']
            ]
        ];

        $result = $ai->tenunGuide($payload);

        TenunGuide::create([
            'user_id'=>auth()->id(),
            'design_name'=>$data['design_name'],
            'motif_width_lungsin'=>$data['motif_width_lungsin'],
            'motif_height_pakan'=>$data['motif_height_pakan'],
            'motif_colors'=>$data['motif_colors'],
            'reference_image_path'=>$path,
            'ai_result'=>$result
        ]);

        return new ApiResponseDefault(true,'OK',$result);
    }
}
