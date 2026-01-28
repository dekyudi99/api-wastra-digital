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
                    if (!$item->product || $item->product->stock < $item->quantity) {
                        throw new \Exception('Stok produk ' . $item->product->name . ' tidak mencukupi.');
                    }
                    $totalAmount += $item->product->last_price * $item->quantity;
                }

                $newOrder = Order::create([
                    'user_id' => $idUser,
                    'invoice_number' => 'INV-' . time(),
                    'total_amount' => $totalAmount,
                    'shipping_address' => $request->input('shipping_address'),
                    'status' => 'unpaid',
                ]);

                foreach ($cartItems as $item) {
                    OrderItem::create([
                        'order_id' => $newOrder->id,
                        'product_id' => $item->product_id,
                        'quantity' => $item->quantity,
                        'name_at_purchase' => $item->product->name,
                        'price_at_purchase' => $item->product->last_price,
                        'description_at_purchase' => $item->product->description,
                        'subtotal' => $item->quantity * $item->product->last_price,
                    ]);
                    $item->product->decrement('stock', $item->quantity);
                }
                
                Cart::whereIn('id', $selectedCartIds)->delete();

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
                    'user_id' => $idUser,
                    'invoice_number' => 'INV-' . time(),
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

    public function myOrder() {
        $id = Auth::id();
        $order = Order::where('user_id', $id)
                   ->orderBy('created_at', 'desc')
                   ->get();

        if (!$order) {
            return new ApiResponseDefault(false, 'Gagal Mengambil Data Pesanan!', null, 500);
        }

        return new ApiResponseDefault(true, 'Berhasil Mengambil Data Pesanan!', $order);
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
        $artisanId = Auth::id();

        $order = Order::with(['user', 'item' => function ($query) use ($artisanId) {
                $query->whereHas('product', function ($q) use ($artisanId) {
                    $q->where('user_id', $artisanId);
                })->with('product');
            }])
            ->find($id);

        // Keamanan: Jika order ada tapi tidak punya item milik artisan ini, tolak.
        if (!$order) {
            return new ApiResponseDefault(false, 'Pesanan tidak ditemukan!', null, 404);
        }

        return new ApiResponseDefault(true, 'Detail pesanan berhasil ditampilkan', $order);
    }

    public function orderIn()
    {
        $artisanId = Auth::id();

        // Ambil Order yang memiliki item milik pengrajin ini
        $orders = Order::whereHas('item.product', function ($query) use ($artisanId) {
                $query->where('user_id', $artisanId);
            })
            ->with(['user', 'item' => function ($query) use ($artisanId) {
                // Eager load HANYA item milik pengrajin ini
                $query->whereHas('product', function ($q) use ($artisanId) {
                    $q->where('user_id', $artisanId);
                })->with('product');
            }])
            ->latest()
            ->get();

        return new ApiResponseDefault(true, 'Daftar pesanan masuk berhasil diambil', $orders);
    }

    public function updateStatus(Request $request, $orderItemId)
    {
        $item = OrderItem::find($orderItemId);

        if (!$item) {
            return new ApiResponseDefault(false, 'Item pesanan tidak ditemukan!', null, 404);
        }

        $user = Auth::user();
        $requestStatus = $request->status;

        // LOGIKA PENJUAL (Artisan)
        if ($user->role === 'artisan') {
            // Validasi otoritas: Apakah produk ini milik dia?
            if ($item->product->user_id !== $user->id) {
                return new ApiResponseDefault(false, 'Akses ditolak!', null, 403);
            }

            $allowedStatus = ['processing', 'shipped', 'cancelled'];
            if (!in_array($requestStatus, $allowedStatus)) {
                return new ApiResponseDefault(false, 'Status tidak valid untuk penjual', 422);
            }
        } 
        // LOGIKA PEMBELI (Customer)
        else if ($user->role === 'customer') {
            // Validasi otoritas: Apakah ini order milik dia?
            if ($item->order->user_id !== $user->id) {
                return new ApiResponseDefault(false, 'Ini bukan pesanan Anda!', null, 403);
            }

            // Pembeli hanya boleh mengubah ke 'delivered' jika status saat ini 'shipped'
            if ($requestStatus !== 'delivered' || $item->status !== 'shipped') {
                return new ApiResponseDefault(false, 'Anda hanya bisa mengonfirmasi pesanan yang sudah dikirim', 422);
            }
        }

        $item->update(['status' => $requestStatus]);

        return new ApiResponseDefault(true, 'Status item berhasil diperbarui!', $item);
    }

    public function adminDashboardStats()
    {
        // 1. Stats Utama
        $artisanCount = User::where('role', 'artisan')->count();
        $productCount = Product::count();
        $ongoingOrders = Order::whereIn('status', ['paid', 'processing', 'shipped'])->count();
        
        // Komisi (10% dari total nominal pesanan yang berstatus 'delivered')
        $totalDeliveredRevenue = Order::where('status', 'delivered')->sum('total_amount');
        $bumdesCommission = $totalDeliveredRevenue * 0.1;

        // 2. Data Grafik Batang (6 Bulan Terakhir)
        $monthlyRevenue = Order::selectRaw('MONTHNAME(created_at) as month, SUM(total_amount) as total')
            ->where('created_at', '>=', now()->subMonths(6))
            ->whereIn('status', ['paid', 'processing', 'shipped', 'delivered'])
            ->groupBy('month')
            ->get();

        // 3. Data Top Pengrajin (Berdasarkan total item yang terjual)
        $topArtisans = User::where('role', 'artisan')
            ->withCount(['products as items_sold' => function($query) {
                $query->join('order_items', 'products.id', '=', 'order_items.product_id')
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