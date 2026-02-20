<?php

namespace App\Http\Controllers;

use App\Models\TenunGuide;
use Illuminate\Http\Request;
use App\Http\Resources\ApiResponseDefault;

class TenunGuideController extends Controller
{
    // List Riwayat: Hanya ambil data dasar (Tanpa ai_result)
    public function index(Request $request)
    {
        $guides = TenunGuide::where('user_id', auth()->id())
            ->latest()
            ->paginate(10);

        $data = $guides->through(function ($g) {
            return [
                'id' => $g->id,
                'design_name' => $g->design_name,
                'motif_width_lungsin' => $g->motif_width_lungsin,
                'motif_height_pakan' => $g->motif_height_pakan,
                'motif_colors' => $g->motif_colors,
                'created_at' => $g->created_at->diffForHumans(), // Lebih user-friendly
            ];
        });

        return new ApiResponseDefault(true, 'Daftar riwayat diambil', [
            'items' => $data->items(),
            'pagination' => [
                'current_page' => $guides->currentPage(),
                'total' => $guides->total(),
            ],
        ]);
    }

    // Detail Riwayat: Ambil data lengkap + AI Result
    public function show($id)
    {
        $guide = TenunGuide::where('user_id', auth()->id())->findOrFail($id);

        // Pastikan URL gambar menggunakan asset() agar bisa diakses React
        $aiResult = $guide->ai_result;
        if (isset($aiResult['image'])) {
            $aiResult['image'] = asset($aiResult['image']);
        }

        return new ApiResponseDefault(true, 'Detail panduan berhasil diambil', [
            'id' => $guide->id,
            'design_name' => $guide->design_name,
            'ai_result' => $aiResult, // Data berat dimuat di sini
            'reference_image_url' => $guide->reference_image_path ? asset('storage/' . $guide->reference_image_path) : null,
        ]);
    }
}