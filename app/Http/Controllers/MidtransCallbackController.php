<?php
namespace App\Http\Controllers;

use Midtrans\Config;
use Midtrans\Notification;
use App\Http\Resources\ApiResponseDefault;
use App\Models\TransactionHistory;
use App\Models\Order;
use App\Models\Product;
use Exception;
use Illuminate\Support\Facades\DB;

class MidtransCallbackController extends Controller
{
    public function handle()
    {
        Config::$serverKey = env('MIDTRANS_SERVER_KEY');
        Config::$isProduction = (bool) env('MIDTRANS_IS_PRODUCTION');

        try {
            $notif = new Notification();
        } catch (\Exception $e) {
            return new ApiResponseDefault(false, 'Gagal Mendapatkan Notifikasi Dari Midtrans: '.$e, null, 400);
        }

        $order = Order::where('invoice_number', $notif->order_id)->first();

        if (!$order) {
            return new ApiResponseDefault(false, 'Pesanan Tidak Ditemukan!', null, 404);
        } else {
            if ($notif->transaction_status == 'capture') {
                if ($notif->fraud_status == 'accept') {
                    // Card payment berhasil
                    $order->update([
                        'status' => 'paid',
                    ]);
                }
            } elseif ($notif->transaction_status == 'settlement') {
                // Pembayaran berhasil (non-credit-card / selesai)
                $order->update([
                    'status' => 'paid',
                ]);
            } elseif ($notif->transaction_status == 'pending') {
                return response()->json(['message' => 'Menunggu Pembayaran'], 200);
            } elseif (in_array($notif->transaction_status, ['cancel', 'expire', 'deny'])) {
                return response()->json(['message' => 'Pembayaran gagal / dibatalkan'], 400);
            }
            // Simpan riwayat transaksi
            TransactionHistory::create([
                'date' => now(),
                'invoice_number' => $notif->order_id,
                'channel' => $notif->payment_type,
                'status' => $notif->transaction_status,
                'value' => $notif->gross_amount,
                'email_customer' => $notif->customer_details->email ?? $order->user->email ?? null,
            ]);
            try {
                DB::transaction(function () use ($order, $notif) {
                    // Update status order menjadi paid
                    $order->update(['status' => 'paid']);
                    // Pastikan relation items tersedia; gunakan eager relation kalau perlu
                    $orderItems = $order->items()->get();
                    foreach ($orderItems as $item) {
                        // Ambil product via relation (pastikan relation product didefinisikan)
                        $product = $item->product ?? Product::find($item->product_id);
                        if (!$product) {
                            // Jika produk tidak ditemukan, skip (atau bisa throw exception tergantung kebijakan)
                            continue;
                        }
                        // Increment sold secara atomic
                        $product->increment('sold', $item->quantity);
                    }
                    // Simpan riwayat transaksi
                    TransactionHistory::create([
                        'date' => now(),
                        'invoice_number' => $notif->order_id,
                        'channel' => $notif->payment_type ?? null,
                        'status' => $notif->transaction_status ?? 'paid',
                        'value' => $notif->gross_amount ?? $order->total_amount ?? null,
                        'email_customer' => $notif->customer_details->email ?? ($order->user->email ?? null),
                    ]);
                });
            } catch (Exception $e) {
                return new ApiResponseDefault(false, 'Gagal memproses order: ' . $e->getMessage(), null, 500);
            }
            return response()->json(['message' => 'Callback processed successfully'], 200);
        }
    }
}