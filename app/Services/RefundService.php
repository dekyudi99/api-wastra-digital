<?php

namespace App\Services;

use App\Models\CancelRequest;
use Illuminate\Support\Facades\DB;
use App\Models\Wallet;
use App\Models\WalletMutation;

class RefundService
{
    public static function refundItem(CancelRequest $cancel)
    {
        DB::transaction(function () use ($cancel) {

            $item = $cancel->orderItem;

            // === HITUNG NILAI ===
            $sellerAmount = intval($item->subtotal * 0.9);
            $adminAmount  = intval($item->subtotal * 0.1);

            // === WALLET ===
            $sellerWallet = Wallet::where([
                'owner_type' => 'artisan',
                'owner_id' => $item->artisan_id,
            ])->lockForUpdate()->first();

            $adminWallet = Wallet::where([
                'owner_type' => 'admin',
            ])->lockForUpdate()->first();

            // === MUTATION (DEBIT) ===
            WalletMutation::create([
                'wallet_id' => $sellerWallet->id,
                'type' => 'debit',
                'amount' => $sellerAmount,
                'source' => 'refund',
                'ref_id' => $item->id,
            ]);

            WalletMutation::create([
                'wallet_id' => $adminWallet->id,
                'type' => 'debit',
                'amount' => $adminAmount,
                'source' => 'refund',
                'ref_id' => $item->id,
            ]);

            // === UPDATE BALANCE (CACHE) ===
            $sellerWallet->decrement('balance', $sellerAmount);
            $adminWallet->decrement('balance', $adminAmount);

            // === UPDATE ITEM ===
            $item->update(['item_status' => 'cancelled']);

            // === UPDATE CANCEL ===
            $cancel->update(['status' => 'completed']);

            // === REFRESH ORDER STATUS ===
            $item->order->refreshStatus();
        });
    }
}
