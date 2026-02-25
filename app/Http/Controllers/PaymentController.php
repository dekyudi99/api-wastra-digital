<?php

namespace App\Http\Controllers;

use Midtrans\Config;
use Midtrans\Snap;
use App\Models\Order;
use App\Models\TransactionHistory;
use App\Http\Resources\ApiResponseDefault;

class PaymentController extends Controller
{
    public function pay($id)
    {
        Config::$serverKey = config('midtrans.server_key');
        Config::$isProduction = config('midtrans.is_production');
        Config::$isSanitized = config('midtrans.is_sanitized');
        Config::$is3ds = config('midtrans.is_3ds');

        $order= Order::find($id);

        if (!$order) {
            return new ApiResponseDefault(false, "Pesanan tidak ditemukan!", null, 404);
        }

        if ($order->payment_status != "unpaid") {
            return new ApiResponseDefault(false, "Pesanan sudah selesai atau dibatalkan!", null, 403);
        }

        if ($order->created_at->diffInMinutes(now()) > 15) {
            $order->update(['order_status' => 'cancelled']);
            return new ApiResponseDefault(false, "Waktu pembayaran habis!", null, 400);
        }
        
        $transactionHistory = TransactionHistory::where('order_id', $order->id)->first();

        if (!$transactionHistory) {
            $params = [
                'transaction_details' => [
                    'order_id' => $order->order_code,
                    'gross_amount' => $order->total_amount,
                ],
                'customer_details' => [
                    'first_name' => auth()->user()->name,
                    'email' => auth()->user()->email,
                ],
            ];
    
            try {
                $paymentUrl = Snap::createTransaction($params)->redirect_url;
    
                return response()->json(['payment_url' => $paymentUrl]);
            } catch (\Exception $e) {
                return response()->json(['error' => $e->getMessage()], 500);
            }
        }

        if ($transactionHistory->status == 'settlement') {
            return new ApiResponseDefault(false, 'Anda Sudah Membayar Membership Ini!', null, 400);
        }

    }
}