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

        $cart = Cart::where('user_id', $idUser)->where('product_id', $id)->first();
        
        if ($cart) {
            $cart->increment('quantity', $request->quantity);
            
            return new ApiResponseDefault(true, 'Kuantitas Produk di Keranjang Berhasil Diperbarui!', $cart);
        } else {
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
        $order = Order::find($id);

        if (!$order) {
            return new ApiResponseDefault(false, 'Pesanan Tidak Ditemukan!', null, 404);
        }

        return new ApiResponseDefault(true, 'Detail Pesanan Berhasil Ditempilkan!', $order);
    }

    public function orderIn()
    {
        $orderItems = OrderItem::with(['product', 'order'])
            ->whereHas('product', function ($query) {
                $query->where('user_id', Auth::id());
            })->get();
        
        if ($orderItems->isEmpty()) {
            return new ApiResponseDefault(false, 'Belum ada pesanan!', null, 404);
        }

        return new ApiResponseDefault(true, 'Berhasil menampilkan pesanan!', $orderItems);
    }
}