<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Resources\ApiResponseDefault;
use App\Models\OrderItem;
use App\Models\CancelRequest;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\DB;
use App\Models\CancelApproval;
use App\Models\Order;
use App\Services\RefundService;
use App\Services\MidtransRefundService;
use Illuminate\Support\Facades\Auth;

class CancelController extends Controller
{
    public function cancelOrder(Request $request, $id)
    {
        $order = Order::with('items')->find($id);

        if (!$order) {
            return new ApiResponseDefault(false, "Pesanan tidak ditemukan!", null, 404);
        } 

        if ($order->customer_id !== Auth::id()) {
            return new ApiResponseDefault(false, "Anda tidak berhak membatalkan produk yang bukan milik anda!", null, 403);
        }

        if ($order->payment_status !== 'unpaid') {
            return new ApiResponseDefault(false, "Anda tidak bisa membatalkan pesanan ini!", null, 403);
        }

        $request->validate([
            'reason' => "required|string",
        ]);

        $order->update([
            'order_status' => 'cancelled', 
        ]);

        foreach ($order->items as $item) {
            $orderItem = OrderItem::find($item->id);

            CancelRequest::create([
                'order_item_id' => $orderItem->id,
                'buyer_id' => auth()->id(),
                'reason' => $request->reason,
                'status' => 'completed',
            ]);

            $orderItem->update([
                'item_status' => 'cancelled'
            ]);
        }

        return new ApiResponseDefault(true, "Pesanan telah dibatalkan!");
    }

    public function request(Request $request, $orderItemId)
    {
        $item = OrderItem::with('order')->findOrFail($orderItemId);

        $this->authorize('create', [CancelRequest::class, $item]);

        $request->validate([
            'reason' => 'required|string',
        ]);
            
        $cancel = CancelRequest::create([
            'order_item_id' => $item->id,
            'buyer_id' => auth()->id(),
            'reason' => $request->reason,
            'status' => 'requested',
        ]);
                
        $item->update([
            'item_status' => 'cancelled',
            'cancelled_at' => now()
        ]);

        AuditLogger::log(auth()->user(), 'request_cancel', $cancel);

        return new ApiResponseDefault(true, 'Permintaan pembatalan dikirim');
    }

    public function sellerApprove(Request $request, $id)
    {
        $cancel = CancelRequest::findOrFail($id);

        $this->authorize('sellerApprove', $cancel);

        DB::transaction(function () use ($request, $cancel) {
            CancelApproval::create([
                'cancel_request_id' => $cancel->id,
                'role' => 'seller',
                'approved' => true,
                'note' => $request->note,
            ]);

            $cancel->update(['status' => 'seller_approved']);
        });

        AuditLogger::log(auth()->user(), 'seller_approve_cancel', $cancel);

        return new ApiResponseDefault(true, 'Disetujui penjual');
    }

    public function adminApprove($id)
    {
        $cancel = CancelRequest::findOrFail($id);

        $this->authorize('adminApprove', $cancel);

        DB::transaction(function () use ($cancel) {

            CancelApproval::create([
                'cancel_request_id' => $cancel->id,
                'role' => 'admin',
                'approved' => true,
            ]);

            // === REFUND KE MIDTRANS DULU ===
            $refund = MidtransRefundService::refund($cancel);

            if ($refund->status !== 'success') {
                throw new \Exception('Refund Midtrans gagal');
            }

            // === BARU LEDGER ===
            RefundService::refundItem($cancel);

            $cancel->update(['status' => 'completed']);
        });

        AuditLogger::log(auth()->user(), 'admin_approve_cancel_refund', $cancel);

        return new ApiResponseDefault(true, 'Refund berhasil diproses');
    }
}
