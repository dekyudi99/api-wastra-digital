<?php

namespace App\Http\Controllers;

use App\Models\OrderItem;
use App\Models\Review;
use Illuminate\Http\Request;
use App\Http\Resources\ApiResponseDefault;
use App\Models\User;

class ReviewController extends Controller
{
    public function store(Request $request, OrderItem $orderItem)
    {
        $request->validate([
            'rating'=>'required|integer|min:1|max:5',
            'comment'=>'nullable|string'
        ]);

        // AUTH buyer
        if ($orderItem->order->user_id !== auth()->id()) {
            abort(403);
        }

        // must completed
        if ($orderItem->item_status !== 'completed') {
            return new ApiResponseDefault(false,'Pesanan belum selesai',422);
        }

        // only once
        if (Review::where('order_item_id',$orderItem->id)->exists()) {
            return new ApiResponseDefault(false,'Item sudah direview',409);
        }

        $review = Review::create([
            'user_id'=>auth()->id(),
            'order_item_id'=>$orderItem->id,
            'product_id'=>$orderItem->product_id,
            'artisan_id'=>$orderItem->product->user_id,
            'rating'=>$request->rating,
            'comment'=>$request->comment
        ]);

        return new ApiResponseDefault(true,'Review tersimpan',$review,201);
    }

    public function update(Request $request, Review $review)
    {
        if ($review->user_id !== auth()->id()) abort(403);

        $request->validate([
            'rating'=>'required|integer|min:1|max:5',
            'comment'=>'nullable'
        ]);

        $review->update($request->only('rating','comment'));

        return new ApiResponseDefault(true,'Review diperbarui',$review);
    }

    public function reviewProduct($productId)
    {
        $reviews = Review::with('user')
            ->where('product_id',$productId)
            ->latest()
            ->paginate(5);

        return new ApiResponseDefault(true,'Review produk',$reviews);
    }

    public function showTotalReviews($sellerId)
    {
        $seller = User::with('product.review')->find($sellerId);

        if (!$seller || $seller->role != 'artisan') {
            return new ApiResponseDefault(false, "Ini bukan pengrajin atau Pengguna ini tidak ada!", null, 403);
        }

        // Menghitung total dengan mapping collection
        $totalReviews = $seller->product->sum(function ($product) {
            return $product->review->count();
        });

        return new ApiResponseDefault(true, "Berhasil mengambil rating pengrajin!", ['rating' => $totalReviews]);
    }
}
