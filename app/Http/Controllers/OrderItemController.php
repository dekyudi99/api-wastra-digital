<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\AuditLogger;
use App\Models\OrderItem;
use App\Http\Resources\ApiResponseDefault;

class OrderItemController extends Controller
{
    public function updateStatus(Request $request, $id)
    {
        $item = OrderItem::findOrFail($id);

        $this->authorize('updateStatus', $item);

        $request->validate([
            'status' => 'required|in:processing,shipped',
        ]);

        $old = $item->only('item_status');

        $item->update([
            'item_status' => $request->status,
            'shipped_at' => $request->status === 'shipped' ? now() : null,
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
            'item_status' => 'completed',
            'completed_at' => now(),
        ]);

        $item->order->refreshStatus();

        AuditLogger::log(
            auth()->user(),
            'confirm_received',
            $item,
            $old,
            ['item_status' => 'completed']
        );

        return new ApiResponseDefault(true, 'Pesanan dikonfirmasi');
    }
}
