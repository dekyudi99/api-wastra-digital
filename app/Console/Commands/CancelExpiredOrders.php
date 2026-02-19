<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Order;

class CancelExpiredOrders extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:cancel-expired-orders';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $expiryTime = now()->subMinutes(15);

        $expiredOrders = Order::with('items')->where('payment_status', 'unpaid')
            ->where('created_at', '<=', $expiryTime)
            ->get();

        foreach ($expiredOrders as $order) {
            $order->update(['order_status' => 'cancelled']);
            foreach ($order->items as $item) {
                $item->update([
                    'item_status' => 'cancelled',
                ]);
                $item->product->increment('stock', $item->quantity);
            }
        }

        $this->info('Pesanan kadaluwarsa telah dibatalkan.');
    }
}
