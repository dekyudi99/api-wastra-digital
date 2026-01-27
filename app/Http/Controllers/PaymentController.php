<?php

namespace App\Http\Controllers;

use Midtrans\Config;
use Midtrans\Snap;
use App\Models\Order;
use App\Models\TransactionHistory;
use App\Http\Resources\ApiResponseDefault;
use App\Models\Product;

class PaymentController extends Controller
{
    public function pay($id)
    {
        Config::$serverKey = env('MIDTRANS_SERVER_KEY');
        Config::$isProduction = (bool) env('MIDTRANS_IS_PRODUCTION');
        Config::$isSanitized = true;
        Config::$is3ds = true;

        $order= Order::findOrFail($id);
        if ($order->status != "unpaid") {
            return new ApiResponseDefault(false, "Pesanan sudah selesai atau dibatalkan!", null, 403);
        }

        if ($order->created_at->diffInMinutes(now()) > 15) {
            $order->update(['status' => 'cancelled']);
            return new ApiResponseDefault(false, "Waktu pembayaran habis!", null, 400);
        }
        
        $transactionHistory = TransactionHistory::where('invoice_number', $order->invoice_number)->first();

        if (!$transactionHistory) {
            $params = [
                'transaction_details' => [
                    'order_id' => $order->invoice_number,
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