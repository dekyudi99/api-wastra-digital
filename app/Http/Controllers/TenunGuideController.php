<?php

namespace App\Http\Controllers;

use App\Models\TenunGuide;
use Illuminate\Http\Request;
use App\Http\Resources\ApiResponseDefault;

class TenunGuideController extends Controller
{
    public function index(Request $request)
    {
        $userId = auth()->id();

        $guides = TenunGuide::where('user_id', $userId)
            ->latest()
            ->paginate(10);

        // rapikan response supaya frontend gampang pakai
        $data = $guides->through(function ($g) {
            return [
                'id' => $g->id,
                'design_name' => $g->design_name,
                'motif_width_lungsin' => $g->motif_width_lungsin,
                'motif_height_pakan' => $g->motif_height_pakan,
                'motif_colors' => $g->motif_colors,
                'reference_image_url' => $g->reference_image_path
                    ? asset('storage/' . $g->reference_image_path)
                    : null,
                'ai_result' => $g->ai_result,
                'created_at' => $g->created_at->toDateTimeString(),
            ];
        });

        return new ApiResponseDefault(
            true,
            'Riwayat panduan tenun berhasil diambil',
            [
                'items' => $data->items(),
                'pagination' => [
                    'current_page' => $guides->currentPage(),
                    'last_page' => $guides->lastPage(),
                    'per_page' => $guides->perPage(),
                    'total' => $guides->total(),
                ],
            ],
            200
        );
    }
}
 