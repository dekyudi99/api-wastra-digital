<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Cart;
use Illuminate\Support\Facades\Validator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\PaymentController;
use App\Http\Resources\ApiResponseDefault;
use App\Models\User;
use App\Services\AuditLogger;
use App\Models\Wallet;

class OrderController extends Controller
{
    // Menambahkan produk ke cart
    public function cart(Request $request, $id) {
        $messages = [
            'quantity.required' => 'Kuantitas Wajib Diisi!',
            'quantity.numeric' => 'Kuantitas Wajib Berupa Nomor!',
            'quantity.min' => 'Kuantitas Minimal 1!',
        ];

        $validator = Validator::make($request->all(), [
            'quantity' => 'required|numeric|min:1',
        ], $messages);

        if ($validator->fails()) {
            return new ApiResponseDefault(false, $validator->errors()->first(), null, 422);
        }

        $idUser = Auth::id();
        $product = Product::find($id);
        if (!$product) {
            return new ApiResponseDefault(false, 'Produk tidak ditemukan!', null, 404);
        }

        // CEK STOK: Jangan sampai melebihi stok yang tersedia
        if ($product->stock < $request->quantity) {
            return new ApiResponseDefault(false, 'Stok tidak mencukupi!', null, 400);
        }

        $cart = Cart::where('user_id', $idUser)->where('product_id', $id)->first();
        
        if ($cart) {
            // Cek lagi jika total setelah ditambah melebihi stok
            if ($product->stock < ($cart->quantity + $request->quantity)) {
                return new ApiResponseDefault(false, 'Total di keranjang melebihi stok!', null, 400);
            }
            $cart->increment('quantity', $request->quantity);
            return new ApiResponseDefault(true, 'Kuantitas diperbarui!', $cart);
        }
        
        $newCartItem = Cart::create([
            'user_id' => $idUser,
            'product_id' => $id,
            'quantity' => $request->quantity,
        ]);

        if (!$newCartItem) {
            return new ApiResponseDefault(false, 'Produk Gagal Ditambahkan Di Keranjang!', null, 500);
        }

        return new ApiResponseDefault(true, 'Produk Berhasil Ditambahkan Di Keranjang!', $newCartItem, 201);
    }

    public function editCart(Request $request,$id)
    {
        $cart = Cart::find($id);

        if (!$cart) {
            return new ApiResponseDefault(false, "Keranjang tidak ditemukan!", null, 404);
        } elseif ($cart->user_id != Auth::id()) {
            return new ApiResponseDefault(false, "Anda tidak bisa mengupdate keranjang pengguna lain!", null, 403);
        }

        if ($request->has('method')) {
            if ($request->input("method") == "plus") {
                $cart->update([
                    'quantity' => $cart->quantity+1,
                ]);

                if (!$cart) {
                    return new ApiResponseDefault(false, "Gagal menambahkan keranjang!", null, 500);
                }

                return new ApiResponseDefault(true, "Berhasil menambahkan keranjang!");
            } elseif ($request->input("method") == "minus") {
                if ($cart->quantity <= 1) {
                    return new ApiResponseDefault(false, "Keranjang minimal berisi 1 produk!", null, 403);
                }

                $cart->update([
                    'quantity' => $cart->quantity-1,
                ]);
                
                if (!$cart) {
                    return new ApiResponseDefault(false, "Gagal mengurangi keranjang!", null, 500);
                }

                return new ApiResponseDefault(true, "Berhasil mengurangi keranjang!");
            } 
        }
    }

    public function deleteCart($id)
    {
        $cart = Cart::find($id);

        if (!$cart) {
            return new ApiResponseDefault(false, "Keranjang tidak ditemukan!", null, 404);
        } elseif ($cart->user_id != Auth::id()) {
            return new ApiResponseDefault(false, "Anda tidak bisa menghapus keranjang pengguna lain!", null, 403);
        }

        $cart->delete();

        if (!$cart) {
            return new ApiResponseDefault(false, "Gagal menghapus keranjang!", null, 500);
        }

        return new ApiResponseDefault(true, "Berhasil menghapus keranjang!");
    }

    // Melihat Keranjang saya
    public function mycart() {
        $id = Auth::id();
        $cart = Cart::where('user_id', $id)->with('product')->get();

        if (!$cart) {
            return new ApiResponseDefault(false, 'Gagal Mengambil Data Keranjang!', null, 500);
        }

        return new ApiResponseDefault(true, 'Daftar Produk di Keranjang Anda Berhasil Ditampilkan!', $cart);
    }

    public function cartCount()
    {
        $cart = Cart::where('user_id', Auth::id())->count();

        return new ApiResponseDefault(true, "Berhasil menampilkan total keranjang!", ['total'=>$cart]);
    }

    public function orderCart(Request $request)
    {
        $messages = [
            'cart_ids.required' => 'ID Keranjang Wajib Diisi!',
            'cart_ids.array' => 'ID Keranjang Wajib Bertipe Array!',
            'cart_ids.*.exists' => 'Keranjang Tidak Ada!',
            'shipping_address.required' => 'Lokasi Tujuan Wajib Diisi!',
            'shipping_address.string' => 'Lokasi Tujuan Wajib Bertipe String!',
        ];

        $validator = Validator::make($request->all(), [
            'cart_ids'       => 'required|array',
            'cart_ids.*'     => 'exists:carts,id',
            'shipping_address' => 'required|string',
        ], $messages);

        if ($validator->fails()) {
            return new ApiResponseDefault(false, $validator->errors(), null, 422);
        }

        $idUser = Auth::id();
        $selectedCartIds = $request->input('cart_ids');

        $cartItems = Cart::where('user_id', $idUser)
                        ->whereIn('id', $selectedCartIds)
                        ->with('product')
                        ->get();

        if ($cartItems->count() != count($selectedCartIds)) {
            return new ApiResponseDefault(false, 'Beberapa Item Tidak Valid!', null, 400);
        }

        try {
            $order = DB::transaction(function () use ($cartItems, $idUser, $request, $selectedCartIds) {

            $totalAmount = 0;

            foreach ($cartItems as $item) {
                if ($item->product->stock < $item->quantity) {
                    throw new \Exception('Stok tidak cukup');
                }
                $totalAmount += $item->product->last_price * $item->quantity;
            }

            $order = Order::create([
                'customer_id' => $idUser,
                'order_code' => 'INV-' . time(),
                'order_status' => 'pending',
                'payment_status' => 'unpaid',
                'total_amount' => $totalAmount,
                'shipping_address' => $request->shipping_address,
            ]);

            foreach ($cartItems as $item) {
                OrderItem::create([
                    'order_id'   => $order->id,
                    'product_id' => $item->product_id,
                    'artisan_id' => $item->product->artisan_id,
                    'price_at_purchase'      => $item->product->last_price,
                    'name_at_purchase'      => $item->product->name,
                    'description_at_purchase'      => $item->product->description,
                    'quantity'   => $item->quantity,
                    'subtotal'   => $item->quantity * $item->product->last_price,
                    'item_status'=> 'pending',
                ]);

                $item->product->decrement('stock', $item->quantity);
            }

            Cart::whereIn('id', $selectedCartIds)->delete();

            return $order;
        });

            $paymentController = new PaymentController();

            $paymentResponse = $paymentController->pay($order->id);

            $paymentData = json_decode($paymentResponse->getContent(), true);

            $paymentUrl = $paymentData['payment_url'] ?? null;

            return new ApiResponseDefault(true, 'Pesanan Berhasil Dibuat!', [$order, $paymentUrl], 201);
        } catch (\Exception $e) {
            return new ApiResponseDefault(false, 'Gagal Membuat Pesanan: '.$e, null, 500);
        }
    }

    public function directOrder(Request $request, $id) {
        $messages = [
            'quantity.required' => 'Kuantitas Wajib Diisi!',
            'quantity.numeric' => 'Kuantitas Wajib Berupa Nomor!',
            'quantity.min' => 'Kuantitas Minimal 1!',
            'shipping_address.required' => 'Lokasi Tujuan Wajib Diisi!',
            'shipping_address.string' => 'Lokasi Tujuan Wajib Bertipe String!',
        ];

        $validator = Validator::make($request->all(), [
            'quantity' => 'required|numeric|min:1',
            'shipping_address' => 'required|string',
        ], $messages);

        if ($validator->fails()) {
            return new ApiResponseDefault(false, $validator->errors()->first(), null, 422);
        }

        $idUser = Auth::id();
        $quantity = $request->input('quantity');

        $product = Product::find($id);

        if (!$product) {
            return new ApiResponseDefault(false, 'Produk Tidak Ditemukan!', null, 404);
        }

        if ($product->stock < $quantity) {
            return new ApiResponseDefault(false, 'Stok Produk Tidak Mencukupi!', null, 400);
        }

        try {
            $order = DB::transaction(function () use ($idUser, $product, $quantity, $request) {
                
                $newOrder = Order::create([
                    'customer_id' => $idUser,
                    'order_code' => 'INV-' . time(),
                    'total_amount' => $quantity * $product->last_price,
                    'shipping_address' => $request->input('shipping_address'),
                    'status' => 'unpaid',
                ]);

                OrderItem::create([
                    'order_id' => $newOrder->id,
                    'product_id' => $product->id,
                    'quantity' => $quantity,
                    'name_at_purchase' => $product->name, 
                    'price_at_purchase' => $product->last_price,
                    'description_at_purchase' => $product->description,
                    'subtotal' => $quantity * $product->last_price,
                    'artisan_id' => $product->artisan_id
                ]);

                $product->decrement('stock', $quantity);
                
                return $newOrder;
            });

            $paymentController = new PaymentController();

            $paymentResponse = $paymentController->pay($order->id);

            $paymentData = json_decode($paymentResponse->getContent(), true);
            
            $paymentUrl = $paymentData['payment_url'] ?? null;

            return new ApiResponseDefault(true, 'Pesanan Berhasil Dibuat!', [$order, $paymentUrl], 201);
        } catch (\Exception $e) {
            return new ApiResponseDefault(false, 'Gagal Membuat Pesanan: '.$e, null, 500);
        }
    }

    public function myOrder(Request $request) {
        $id = Auth::id();
        // Ambil status dari query parameter, misal: ?status=pending
        $status = $request->query('status'); 

        $query = Order::where('customer_id', $id);

        if ($status && $status !== 'semua') {
            if ($status === 'unpaid') {
                $query->where('payment_status', $status)->whereNot('order_status', 'cancelled');
            } else {
                // Hanya ambil ORDER yang memiliki ITEM dengan status tersebut
                $query->whereNot('payment_status', 'unpaid')->whereHas('items', function ($q) use ($status) {
                    $q->where('item_status', $status);
                });
    
                // Filter relasi ITEMS agar hanya item dengan status tersebut yang muncul di dalam array
                $query->with(['items' => function ($q) use ($status) {
                    $q->where('item_status', $status);
                }]);
            }
        } else {
            $query->with('items');
        }

        $order = $query->orderBy('created_at', 'desc')->paginate(5);

        // Jangan pakai if (!$order), paginate selalu mengembalikan objek lengthAwarePaginator
        return new ApiResponseDefault(true, 'Berhasil Mengambil Data Pesanan!', $order);
    }

    public function detailOrder($id) {
        $orderItem = OrderItem::with('order')->find($id);

        if (!$orderItem) {
            return new ApiResponseDefault(false, "Pesanan tidak ditemukan!", null, 404);
        }

        if ($orderItem->order->customer_id != Auth::id()) {
            return new ApiResponseDefault(false, "Ini bukan pesanan anda!", null, 403);
        }

        return new ApiResponseDefault(true, "Detail pesanan berhasil ditampilkan!", $orderItem);
    }

    // untuk admin melihat semua pesanan
    public function Order() {
        $Order = Order::all();

        if (!$Order) {
            return new ApiResponseDefault(false, 'Gagal Mengambil Data Pesanan', null, 500);
        }

        return new ApiResponseDefault(false, 'Berhasil Menampilkan Seluruh Data Pesanan!', $Order);
    }

    public function show($id)
    {
        if (Auth::user()->role == 'artisan') {
            $artisanId = Auth::id();
    
            $order = OrderItem::whereHas('product', function ($q) use ($artisanId) {
                $q->where('artisan_id', $artisanId);
            })->with('order', 'order.buyer')->find($id);            
            if (!$order) {
                return new ApiResponseDefault(false, 'Pesanan tidak ditemukan!', null, 404);
            }
    
            return new ApiResponseDefault(true, 'Detail pesanan berhasil ditampilkan', [$order]);
        } elseif (Auth::user()->role == 'customer') {
            $order = Order::where('customer_id', Auth::id())->with('items')->find($id);
        }

        if (!$order) {
            return new ApiResponseDefault(false, 'Pesanan tidak ditemukan!', null, 404);
        }

        return new ApiResponseDefault(true, 'Detail pesanan berhasil ditampilkan', $order);
    }

    public function orderUnpaid()
    {
        $order = Order::where('customer_id', Auth::id())->orderBy('created_at', 'desc')->paginate(5);

        if ($order->isEmpty()) {
            return new ApiResponseDefault(true, "Belum ada pesanan!");
        }

        return new ApiResponseDefault(true, "Berhasil menampilkan pesanan!", $order);
    }

    public function orderIn(Request $request)
    {
        $artisanId = Auth::id();
        $status = $request->query('status', 'all');

        $query = OrderItem::whereHas('product', function ($q) use ($artisanId) {
                $q->where('artisan_id', $artisanId);
            });

        if ($status !== 'all') {
            $query->where('item_status', $status);
        }

        $orderItem = $query->with('order', 'order.buyer')
        ->latest()
        ->paginate(5);

        return new ApiResponseDefault(true, 'Daftar pesanan masuk berhasil diambil', $orderItem);
    }

    public function orderInNewer() {
        $artisanId = Auth::id();
        $orderItem = OrderItem::whereHas('product', function ($q) use ($artisanId){
            $q->where('artisan_id', $artisanId);
        })->whereHas('order', function ($q) {
            $q->where('payment_status', 'settled');
        })->whereNot('item_status', ['cancelled', 'completed', 'finish'])->latest()->take(5)->get();

        if ($orderItem->isEmpty()) {
            return new ApiResponseDefault(true, "Tidak ada pesanan terbaru!");
        }

        return new ApiResponseDefault(true, "Pesanan berhasil ditampilkan!", $orderItem);
    }

    public function totalTransaction()
    {
        $artisanId = Auth::id();
        $orderAll = OrderItem::whereHas('product', function ($q) use ($artisanId){
            $q->where('artisan_id', $artisanId);
        })->count();

        $orderActive = OrderItem::whereHas('product', function ($q) use ($artisanId){
            $q->where('artisan_id', $artisanId);
        })->whereNot('item_status', ['cancelled', 'completed', 'finish'])->count();

        $balanceEstimated = Wallet::where('owner_id', Auth::id())->first();

        return new ApiResponseDefault(
            false,
            "Berhasil menampilkan total transaksi!",
            [
                'all' => $orderAll,
                'active' => $orderActive,
                'balance' => $balanceEstimated->balance + $balanceEstimated->available_balance,
            ]
            );
    }

    public function adminDashboardStats()
    {
        // 1. Stats Utama
        $artisanCount = User::where('role', 'artisan')->count();
        $productCount = Product::count();
        $ongoingOrders = Order::whereIn('order_status', ['paid', 'processing', 'shipped'])->count();
        
        // Komisi (10% dari total nominal pesanan yang berstatus 'delivered')
        $totalDeliveredRevenue = Order::where('order_status', 'delivered')->sum('total_amount');
        $bumdesCommission = $totalDeliveredRevenue * 0.1;

        // 2. Data Grafik Batang (6 Bulan Terakhir)
        $monthlyRevenue = Order::selectRaw('MONTHNAME(created_at) as month, SUM(total_amount) as total')
            ->where('created_at', '>=', now()->subMonths(6))
            ->whereIn('order_status', ['paid', 'processing', 'shipped', 'delivered'])
            ->groupBy('month')
            ->get();

        // 3. Data Top Pengrajin (Berdasarkan total item yang terjual)
        $topArtisans = User::where('role', 'artisan')
            ->withCount(['product as items_sold' => function($query) {
                $query->join('order_items', 'product.id', '=', 'order_item.product_id')
                    ->select(DB::raw("sum(quantity)"));
            }])
            ->orderBy('items_sold', 'desc')
            ->take(3)
            ->get();

        return response()->json([
            'success' => true,
            'data' => [
                'stats' => [
                    'artisans' => $artisanCount,
                    'products' => $productCount,
                    'ongoing_orders' => $ongoingOrders,
                    'commission' => (int) $bumdesCommission,
                    'total_revenue' => (int) $totalDeliveredRevenue
                ],
                'revenue_chart' => $monthlyRevenue,
                'top_artisans' => $topArtisans
            ]
        ]);
    }
}