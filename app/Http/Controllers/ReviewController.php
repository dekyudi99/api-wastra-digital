<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use App\Models\Review;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use App\Http\Resources\ApiResponseDefault;

class ReviewController extends Controller
{
    public function store(Request $request, $id)
    {
        $messages = [
            'rating.required' => 'Rating Wajib Diisi!',
            'rating.numeric' => 'Rating Wajib Berupa Angka!',
            'rating.min' => 'Rating Minimal 1!',
            'rating.max' => 'Rating Maksimal 5!',
        ];

        $validator = Validator::make($request->all(), [
            'rating' => 'required|numeric|min:1|max:5',
            'comment' => 'nullable|string',
        ], $messages);

        if ($validator->fails()) {
            return new ApiResponseDefault(false, $validator->errors(), null, 422);
        }

        $userId = Auth::id();
        $user = User::whereId($userId)->first();
        $productId = $id;

        $hasPurchased = $user->order()
            ->where('status', 'paid')
            ->whereHas('item', function ($query) use ($productId) {
                $query->where('product_id', $productId);
            })
            ->exists(); 

        if (!$hasPurchased) {
            return new ApiResponseDefault(false, 'Anda Hanya Bisa Mereview Produk yang Sudah Anda Beli!', null, 403);
        }
        
        $alreadyReviewed = Review::where('user_id', $user->id)
                                ->where('product_id', $productId)
                                ->exists();

        if ($alreadyReviewed) {
            return new ApiResponseDefault(false, 'Anda Sudah Pernah Mereview Produk Ini!', null, 409);
        }

        $review = Review::create([
            'user_id' => $user->id,
            'product_id' => $productId,
            'rating' => $request->rating,
            'comment' => $request->comment,
        ]);

        return new ApiResponseDefault(true, 'Berhasil Membuat Review!', $review, 201);
    }

    public function update(Request $request, $id)
    {
        $messages = [
            'rating.required' => 'Rating Wajib Diisi!',
            'rating.numeric' => 'Rating Wajib Berupa Angka!',
            'rating.min' => 'Rating Minimal 1!',
            'rating.max' => 'Rating Maksimal 5!',
        ];

        $validator = Validator::make($request->all(), [
            'rating' => 'required|numeric|min:1|max:5',
            'comment' => 'nullable|string',
        ], $messages);

        if ($validator->fails()) {
            return new ApiResponseDefault(false, $validator->errors(), null, 422);
        }

        $userId = Auth::id();
        $review = Review::whereId($id)->first();

        if ($userId != $review->user_id) {
             return new ApiResponseDefault(false, 'Anda Tidak Bisa Mengedit Review Orang Lain!', null, 409);
        }

        $review->update([
            'rating' => $request->rating,
            'comment' => $request->comment,
        ]);

        return new ApiResponseDefault(true, 'Berhasil mengedit Review', $review);
    }

    public function reviewProduct($id)
    {
        $review = Review::where('product_id', $id)->latest()->paginate(3);

        if ($review->isEmpty()) {
            return new ApiResponseDefault(true, 'Belum ada review!');
        }

        return new ApiResponseDefault(true, 'Berhasil menampilkan review product!', $review);
    }
}