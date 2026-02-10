<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Resources\ApiResponseDefault;
use App\Models\OrderItem;
use App\Models\CancelRequest;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\DB;
use App\Models\CancelApproval;
use App\Services\RefundService;
use App\Services\MidtransRefundService;

class CancelController extends Controller
{
    public function request(Request $request, $orderItemId)
    {
        $item = OrderItem::findOrFail($orderItemId);

        $this->authorize('create', [CancelRequest::class, $item]);

        $request->validate([
            'reason' => 'required|string|min:10',
        ]);

        $cancel = CancelRequest::create([
            'order_item_id' => $item->id,
            'customer_id' => auth()->id(),
            'reason' => $request->reason,
            'status' => 'requested',
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
