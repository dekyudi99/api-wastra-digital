<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\AiRecommendation;
use App\Models\Product;

class AiRecommendationController extends Controller
{
    public function approve($id)
    {
        $rec = AiRecommendation::findOrFail($id);

        if ($rec->status !== 'pending') {
            abort(400, 'Already processed');
        }

        $product = Product::findOrFail($rec->product_id);

        if ($rec->type === 'discount') {
            $product->update([
                'discount' => $rec->suggested_change['discount']
            ]);
        }

        $rec->update(['status' => 'approved']);

        return response()->json(['success' => true]);
    }
}
