<?php

namespace App\DataTransferObjects\Ai;

use Illuminate\Support\Collection;

class ProductInsightDTO
{
    public static function fromCollection(Collection $products): array
    {
        return $products->map(function ($product) {
            return [
                'id'       => $product->id,
                'name'     => $product->name,
                'price'    => (float) $product->price,
                'discount' => (int) ($product->discount ?? 0),
                'final_price' => isset($product->last_price)
                    ? (float) $product->last_price
                    : null,
                'sales'    => (int) ($product->sales ?? 0),
                'rating'   => isset($product->rating)
                    ? (float) $product->rating
                    : null,
            ];
        })->values()->toArray();
    }
}
