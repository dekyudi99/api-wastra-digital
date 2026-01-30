<?php
namespace App\Http\Controllers;

use Midtrans\Config;
use Midtrans\Notification;
use App\Http\Resources\ApiResponseDefault;
use App\Models\TransactionHistory;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use Exception;
use Illuminate\Support\Facades\DB;
use App\Models\AdminCommision;
use App\Models\User;

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
                $order->update([
                    'status' => 'paid',
                ]);
            } elseif ($notif->transaction_status == 'pending') {
                return response()->json(['message' => 'Menunggu Pembayaran'], 200);
            } elseif (in_array($notif->transaction_status, ['cancel', 'expire', 'deny'])) {
                return response()->json(['message' => 'Pembayaran gagal / dibatalkan'], 400);
            }
            
            try {
                DB::transaction(function () use ($order, $notif) {

                    // LOCK ORDER (ANTI DOUBLE CALLBACK)
                    $order = Order::lockForUpdate()->find($order->id);

                    if ($order->is_commissioned) {
                        // Sudah pernah diproses → STOP
                        return;
                    }

                    $order->update([
                        'status' => 'paid',
                        'is_commissioned' => true,
                    ]);

                    $orderItems = OrderItem::where('order_id', $order->id)
                        ->where('is_commissioned', false)
                        ->lockForUpdate()
                        ->get();

                    $adminCommission = AdminCommision::lockForUpdate()->first();

                    foreach ($orderItems as $item) {

                        // 🔒 LOCK PRODUCT
                        $product = Product::lockForUpdate()->find($item->product_id);
                        if (!$product) {
                            throw new \Exception('Produk tidak ditemukan');
                        }

                        // ❗ CEK STOK (ANTI MINUS)
                        if ($product->stock < $item->quantity) {
                            throw new \Exception(
                                "Stok produk {$product->id} tidak mencukupi"
                            );
                        }

                        // ✅ KURANGI STOK + TAMBAH SOLD
                        $product->decrement('stock', $item->quantity);
                        $product->increment('sales', $item->quantity);

                        // HITUNG KOMISI
                        $gross = $item->total_price;
                        $artisanAmount = intval($gross * 0.9);
                        $adminAmount   = $gross - $artisanAmount;

                        // 🔒 LOCK ARTISAN
                        $artisan = User::lockForUpdate()->find($item->artisan_id);
                        if (!$artisan) {
                            throw new \Exception('Artisan tidak ditemukan');
                        }

                        // SALDO
                        $artisan->increment('saldo', $artisanAmount);
                        $adminCommission->increment('saldo', $adminAmount);

                        // TANDAI ITEM SELESAI
                        $item->update([
                            'is_commissioned' => true,
                            'status' => 'end',
                        ]);
                    }

                    TransactionHistory::create([
                        'date' => now(),
                        'invoice_number' => $notif->order_id,
                        'channel' => $notif->payment_type ?? null,
                        'status' => 'paid',
                        'value' => $notif->gross_amount,
                        'email_customer' => $order->user->email ?? null,
                    ]);
                });
            } catch (Exception $e) {
                return new ApiResponseDefault(false, 'Gagal memproses order: ' . $e->getMessage(), null, 500);
            }
            return response()->json(['message' => 'Callback processed successfully'], 200);
        }
    }
}