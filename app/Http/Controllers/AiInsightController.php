<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Services\Ai\OpenAiService;
use App\Services\Ai\AiMode;
use App\DataTransferObjects\Ai\AiContextDTO;
use App\DataTransferObjects\Ai\ProductInsightDTO;
use App\DataTransferObjects\Ai\AiPayloadDTO;
use App\Http\Resources\ApiResponseDefault;
use Illuminate\Http\Request;

class AiInsightController extends Controller
{
    /* =========================================================
     | BUYER – OVERVIEW
     ========================================================= */
    public function buyerInsight(OpenAiService $ai)
    {
        $products = Product::query()
            ->withAvg('review as rating', 'rating')
            ->orderByDesc('sales')
            ->limit(5)
            ->get();

        $payload = AiPayloadDTO::make(
            AiContextDTO::forBuyer(),
            [
                'meta' => [
                    'purpose' => 'buyer_overview',
                ],
                'top_selling_products' =>
                    ProductInsightDTO::fromCollection($products),
            ]
        );

        $insight = $ai->explain($payload, AiMode::BUYER, 10);

        return new ApiResponseDefault(
            true,
            'Insight pembeli berhasil dihasilkan',
            $insight,
            200
        );
    }

    /* =========================================================
     | SELLER – OVERVIEW
     ========================================================= */
    public function sellerInsight(OpenAiService $ai)
    {
        $userId = auth()->id();

        $products = Product::query()
            ->where('user_id', $userId)
            ->withAvg('review as rating', 'rating')
            ->orderByDesc('sales')
            ->limit(5)
            ->get();

        $payload = AiPayloadDTO::make(
            AiContextDTO::forSeller(),
            [
                'meta' => [
                    'purpose' => 'seller_overview',
                ],
                'top_products' =>
                    ProductInsightDTO::fromCollection($products),
            ]
        );

        $insight = $ai->explain($payload, AiMode::SELLER, 15);

        return new ApiResponseDefault(
            true,
            'Insight pengrajin berhasil dihasilkan',
            $insight,
            200
        );
    }

    /* =========================================================
     | SELLER – STOCK & DISCOUNT
     ========================================================= */
    public function stockAndDiscountInsight(OpenAiService $ai)
    {
        $userId = auth()->id();

        $products = Product::query()
            ->where('user_id', $userId)
            ->withAvg('review as rating', 'rating')
            ->get();

        $payload = [
            'meta' => [
                'purpose' => 'stock_and_discount_optimization',
                'contains_personal_data' => false,
            ],
            'products' => $products->map(fn ($p) => [
                'id'       => $p->id,
                'name'     => $p->name,
                'price'    => (float) $p->price,
                'discount' => (int) $p->discount,
                'stock'    => (int) $p->stock,
                'sales'    => (int) $p->sales,
                'rating'   => round($p->rating ?? 0, 1),
            ])->values()->toArray(),
        ];

        $insight = $ai->explain($payload, AiMode::SELLER, 15);

        return new ApiResponseDefault(
            true,
            'Rekomendasi stok & diskon berhasil dihasilkan',
            $insight,
            200
        );
    }

    /* =========================================================
     | SELLER – PRODUCT HEALTH SCORE
     ========================================================= */
    public function productHealthScore(OpenAiService $ai)
    {
        $userId = auth()->id();

        $products = Product::query()
            ->where('user_id', $userId)
            ->withAvg('review as rating', 'rating')
            ->get();

        $payload = [
            'meta' => [
                'purpose' => 'product_health_score',
                'contains_personal_data' => false,
            ],
            'products' => $products->map(fn ($p) => [
                'id'       => $p->id,
                'name'     => $p->name,
                'price'    => (float) $p->price,
                'discount' => (int) $p->discount,
                'stock'    => (int) $p->stock,
                'sales'    => (int) $p->sales,
                'rating'   => round($p->rating ?? 0, 1),
            ])->values()->toArray(),
        ];

        $insight = $ai->explain($payload, AiMode::SELLER, 20);

        return new ApiResponseDefault(
            true,
            'Health score produk berhasil dihasilkan',
            $insight,
            200
        );
    }

    /* =========================================================
     | DESIGN PREVIEW — DI-NONAKTIFKAN (HEMAT)
     ========================================================= */
    public function designPreview()
    {
        return new ApiResponseDefault(
            false,
            'Fitur preview desain belum diaktifkan',
            [],
            200
        );
    }

    public function tenunGuide(OpenAiService $ai, Request $request)
    {
        $validated = $request->validate([
            'motif_width_lungsin'  => 'required|integer|min:10|max:400',
            'motif_height_pakan'   => 'required|integer|min:5|max:200',
            'motif_thread'         => 'required|string|max:50',
        ]);

        $payload = [
            'meta' => [
                'purpose' => 'tenun_guide',
                'contains_personal_data' => false,
            ],
            'context' => [
                'lokasi' => 'Sidemen, Bali',
                'alat' => 'alat tenun tradisional',
                'total_lungsin' => 1200,
                'motif_width_lungsin' => $validated['motif_width_lungsin'],
                'motif_height_pakan' => $validated['motif_height_pakan'],
                'motif_thread' => $validated['motif_thread'],
                'posisi_motif' => 'tengah kain',
                'pengulangan' => 'berulang teratur',
            ],
        ];

        $insight = $ai->explain($payload, AiMode::SELLER, 30);

        return new ApiResponseDefault(
            true,
            'Panduan teknis tenun berhasil dihasilkan',
            $insight,
            200
        );
    }
}
