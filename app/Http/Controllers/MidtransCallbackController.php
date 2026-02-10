<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\Order;
use App\Models\TransactionHistory;
use Midtrans\Config;
use App\Jobs\ProcessSettledOrder;

class MidtransCallbackController extends Controller
{
    public function handle(Request $request)
    {
        $request->validate([
            'order_id' => 'required|string',
            'status_code' => 'required|string',
            'gross_amount' => 'required',
            'signature_key' => 'required|string',
            'transaction_status' => 'required|string',
        ]);

        Config::$serverKey = config('midtrans.server_key');
        Config::$isProduction = config('midtrans.is_production');

        $signature = hash(
            'sha512',
            $request->order_id .
            $request->status_code .
            $request->gross_amount .
            config('midtrans.server_key')
        );

        if ($signature !== $request->signature_key) {
            return response()->json(['message' => 'Invalid signature'], 403);
        }

        $order = Order::lockForUpdate()
            ->where('order_code', $request->order_id)
            ->firstOrFail();

        $shouldDispatch = false;

        DB::transaction(function () use ($request, $order, &$shouldDispatch) {

            TransactionHistory::updateOrCreate(
                [
                    'order_id' => $order->id,
                    'midtrans_order_id' => $request->order_id,
                ],
                [
                    'date' => $request->transaction_time ?? now(),
                    'payment_type' => $request->payment_type,
                    'gross_amount' => (int) $request->gross_amount,
                    'status' => $request->transaction_status,
                    'email_customer' => data_get($request->customer_details, 'email'),
                    'payload' => json_encode($request->all()),
                ]
            );

            if (
                in_array($request->transaction_status, ['capture', 'settlement']) &&
                ($request->fraud_status ?? 'accept') === 'accept'
            ) {
                if ($order->payment_status !== 'settled') {
                    $order->update(['payment_status' => 'settled']);
                    $shouldDispatch = true;
                }
            }

            if (in_array($request->transaction_status, ['cancel', 'deny', 'expire'])) {
                $order->update(['payment_status' => 'failed']);
            }
        });

        if ($shouldDispatch) {
            ProcessSettledOrder::dispatch($order->id);
        }

        return response()->json(['message' => 'Callback processed'], 200);
    }
}
