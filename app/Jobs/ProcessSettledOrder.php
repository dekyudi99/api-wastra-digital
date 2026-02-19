<?php

namespace App\Jobs;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Wallet;
use App\Models\WalletMutation;
use App\Services\AuditLogger;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ProcessSettledOrder implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $orderId;

    public $tries = 5;
    public $timeout = 60;
    public $backoff = [10, 30, 60];

    public function __construct(int $orderId)
    {
        $this->orderId = $orderId;
    }

    public function handle()
    {
        DB::transaction(function () {

            // 🔒 LOCK ORDER
            $order = Order::lockForUpdate()->findOrFail($this->orderId);

            // IDEMPOTENCY HARD STOP
            if ($order->processed_at !== null) {
                return;
            }

            if ($order->payment_status !== 'settled') {
                return;
            }

            // 🔒 LOCK ITEMS
            $items = OrderItem::where('order_id', $order->id)
                ->lockForUpdate()
                ->get();

            foreach ($items as $item) {

                // Skip kalau sudah pernah diproses
                if ($item->is_processed) {
                    continue;
                }

                // ===== HITUNG =====
                $gross = $item->subtotal;
                $sellerAmount = intval($gross * 0.9);
                $adminAmount  = $gross - $sellerAmount;

                // ===== WALLET SELLER =====
                $sellerWallet = Wallet::where([
                    'owner_type' => 'artisan',
                    'owner_id'   => $item->artisan_id,
                ])->lockForUpdate()->firstOrFail();

                // ===== WALLET ADMIN =====
                $adminWallet = Wallet::where([
                    'owner_type' => 'admin',
                ])->lockForUpdate()->firstOrFail();

                // ===== MUTATION =====
                WalletMutation::create([
                    'wallet_id' => $sellerWallet->id,
                    'type'      => 'credit',
                    'amount'    => $sellerAmount,
                    'source'    => 'order_income',
                    'ref_id'    => $item->id,
                ]);

                WalletMutation::create([
                    'wallet_id' => $adminWallet->id,
                    'type'      => 'credit',
                    'amount'    => $adminAmount,
                    'source'    => 'admin_fee',
                    'ref_id'    => $item->id,
                ]);

                // ===== UPDATE BALANCE (CACHE) =====
                $sellerWallet->increment('balance', $sellerAmount);
                $adminWallet->increment('balance', $adminAmount);

                // // ===== UPDATE ITEM =====
                // $item->update([
                //     'item_status'  => 'processing',
                //     'is_processed' => true,
                // ]);

                // ===== AUDIT =====
                AuditLogger::log(
                    actor: $order->buyer,
                    action: 'process_order_item',
                    model: $item,
                    old: null,
                    new: [
                        'seller_credit' => $sellerAmount,
                        'admin_fee'     => $adminAmount,
                    ]
                );
            }

            // // TANDAI ORDER SUDAH DIPROSES
            // $order->update([
            //     'processed_at' => now(),
            // ]);
        });
    }

    public function failed(\Throwable $exception)
    {
        Log::error('ProcessSettledOrder FAILED', [
            'order_id' => $this->orderId,
            'message' => $exception->getMessage(),
        ]);
    }
}
