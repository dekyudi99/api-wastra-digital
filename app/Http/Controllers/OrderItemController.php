<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\AuditLogger;
use App\Models\OrderItem;
use App\Http\Resources\ApiResponseDefault;
use App\Models\Wallet;
use App\Models\Product;

class OrderItemController extends Controller
{
    public function updateStatus(Request $request, $id)
    {
        $item = OrderItem::findOrFail($id);

        $this->authorize('updateStatus', $item);

        $request->validate([
            'status' => 'required|in:processing,shipped,completed',
        ]);

        $old = $item->only('item_status');

        $item->update([
            'item_status' => $request->status,
            'processing_at' => $request->status === 'processing' ? now() : null,
            'shipped_at' => $request->status === 'shipped' ? now() : null,
            'completed_at' => $request->status === 'completed' ? now() : null,
        ]);

        AuditLogger::log(
            auth()->user(),
            'update_item_status',
            $item,
            $old,
            ['item_status' => $request->status]
        );

        return new ApiResponseDefault(true, 'Status item diperbarui', $item);
    }

    public function confirmReceived($id)
    {
        $item = OrderItem::findOrFail($id);

        $this->authorize('view', $item);

        $old = $item->only('item_status');

        $item->update([
            'item_status' => 'finish',
            'finished_at' => now(),
        ]);

        $wallet = Wallet::where('owner_id', $item->artisan_id)->first();

        $wallet->update([
            'balance' => $wallet->balance - ($item->subtotal - (($item->subtotal*10)/100)),
            'available_balance' => $wallet->available_balance + ($item->subtotal - (($item->subtotal*10)/100)),
        ]);

        $product = Product::find($item->product_id);

        $product->update([
            'sales' => $product->sales + $item->quantity,
        ]);

        $item->order->refreshStatus();

        AuditLogger::log(
            auth()->user(),
            'confirm_received',
            $item,
            $old,
            ['item_status' => 'finish']
        );

        return new ApiResponseDefault(true, 'Pesanan dikonfirmasi');
    }
}
