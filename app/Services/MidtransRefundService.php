<?php

namespace App\Services;

use Midtrans\Transaction;
use App\Models\CancelRequest;
use Illuminate\Support\Facades\DB;
use App\Models\Refund;

class MidtransRefundService
{
    public static function refund(CancelRequest $cancel)
    {
        return DB::transaction(function () use ($cancel) {

            $item = $cancel->orderItem;
            $order = $item->order;

            // === ID POTENSI REFUND (IDEMPOTENCY) ===
            $refundKey = 'refund-item-' . $item->id;

            // === CEK SUDAH ADA REFUND? ===
            $refund = Refund::firstOrCreate(
                ['midtrans_refund_key' => $refundKey],
                [
                    'cancel_request_id' => $cancel->id,
                    'order_item_id' => $item->id,
                    'amount' => $item->subtotal,
                    'status' => 'pending',
                ]
            );

            // Kalau sudah sukses → STOP
            if ($refund->status === 'success') {
                return $refund;
            }

            try {
                $response = Transaction::refund(
                    $order->order_code,
                    [
                        'refund_key' => $refundKey,
                        'amount' => $item->subtotal,
                        'reason' => 'Cancel order item #' . $item->id,
                    ]
                );

                $refund->update([
                    'status' => 'success',
                    'response' => json_encode($response),
                ]);

                return $refund;

            } catch (\Exception $e) {

                $refund->update([
                    'status' => 'failed',
                    'response' => $e->getMessage(),
                ]);

                throw $e;
            }
        });
    }
}
