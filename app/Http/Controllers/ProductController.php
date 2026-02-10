<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Product;
use App\Http\Resources\ApiResponseDefault;
use Illuminate\Support\Facades\Validator;
use App\Models\ImagesProduct;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class ProductController extends Controller
{
    public function index (Request $request)
    {
        // Mengambil Semua Product
        $query = Product::orderBy('created_at', 'desc');

        // Cek Apakah Ada Parameter search dari Request
        if ($request->has('search')) {
            $searchTerm = $request->input('search');
            
            // Terapkan kondisi WHERE untuk nama atau deskripsi produk
            $query->where(function ($q) use ($searchTerm) {
                $q->where('name', 'like', "%{$searchTerm}%")
                  ->orWhere('description', 'like', "%{$searchTerm}%");
            });
        }

        // Cek Apakah Ada Kategori dari Request
        if ($request->has("category")) {
            $query->where("category", $request->input("category"));
        }

        // Cek apakah ada filter harga
        if ($request->filled('price')) {
            $price = $request->price;

            if ($price === '0-500000') {
                $query->whereBetween('price', [0, 500000]);
            } 
            elseif ($price === '500000-1000000') {
                $query->whereBetween('price', [500000, 1000000]);
            } 
            elseif ($price === '1000000+') {
                $query->where('price', '>=', 1000000);
            }
        }

        // Ambil Data Berdasarkan Query Sebelumnya
        $product = $query->get();

        if ($product->isEmpty()) {
            return new ApiResponseDefault(false, 'Tidak Ada Product Tersedia!', null, 200);
        }

        return new ApiResponseDefault(true, 'Berhasil Menampilkan Product!', $product);
    }

    public function myProduct()
    {
        $product = Product::where('artisan_id', Auth::id())->get();

        if ($product->isEmpty()) {
            return new ApiResponseDefault(false, "Anda belum punya produk!");
        }

        return new ApiResponseDefault(false, "Berhasil menampilkan produk anda!", $product);
    }

    public function store(Request $request) {
        $messages = [
            'name.required' => 'Nama Produk Wajib Diisi!',
            'name.max' => 'Nama Produk Maksimal 255 Karakter!',
            'description.required' => 'Deskripsi Wajib Diisi!',
            'category.required' => 'Kategori Wajib Diisi!',
            'price.required' => 'Harga Wajib Diisi!',
            'price.numeric' => 'Harga Wajib Berupa Angka!',
            'stock.required' => 'Stock Wajib Diisi!',
            'stock.numeric' => 'Stock Wajib Berupa Angka!',
            'material.required' => 'Material wajib diisi!',
            'wide.required' => 'Lebar wajib diisi!',
            'wide.numeric' => 'Lebar wajib berupa angka!',
            'long.required' => 'Panjang wajib diisi!',
            'long.numeric' => 'Panjang wajib berupa angka!',
            'discount.numeric' => 'Diskon wajib berupa angka!',
            'image.array' => 'Bidang gambar harus berupa array.',
            'image.max' => 'Anda hanya dapat mengunggah maksimal :max gambar.',
            'image.*.image' => 'File pada salah satu gambar harus berupa gambar (jpeg, png, jpg, gif, svg).',
            'image.*.mimes' => 'Format file gambar tidak valid. Hanya format :values yang diizinkan.',
            'image.*.max' => 'Ukuran file salah satu gambar tidak boleh melebihi :max kilobyte.',
        ];

        $validator = Validator::make($request->all(), [
            'name' => 'required|max:255',
            'price' => 'required|numeric',
            'description' => 'required',
            'category' => 'required',
            'stock' => 'required|numeric',
            'material' => 'required',
            'wide' => 'required|numeric',
            'long' => 'required|numeric',
            'image' => 'nullable|array|max:5',
            'discount' => 'nullable|numeric',
            'image.*' => 'image|mimes:jpeg,png,jpg,gif,svg|max:2048',
        ], $messages);

        if ($validator->fails()) {
            return new ApiResponseDefault(false, $validator->errors()->first(), null, 422);
        }

        $userId = Auth::id();
        
        $product = Product::create([
            'name' => $request->name,
            'description' => $request->description,
            'category' => $request->category,
            'price' => $request->price,
            'stock' => $request->stock,
            'material' => $request->material,
            'wide' => $request->wide,
            'long' => $request->long,
            'discount' => $request->discount,
            'artisan_id' => $userId,
        ]);

        if ($request->hasFile("image")) {
            foreach ($request->file('image') as $imageFile) {
                $path = $imageFile->store('Product', 'public');

                ImagesProduct::create([
                    'product_id' => $product->id,
                    'image' => $path,
                ]);
            }
        }

        if (!$product) {
            return new ApiResponseDefault(false, 'Gagal Menyimpan Produk!', null, 500);
        }

        return new ApiResponseDefault(true, 'Produk Berhasil Disimpan!', $product);
    }

    public function show($id)
    {
        $product = Product::with('user')->where('id', $id)->get();

        if ($product->isEmpty()) {
            return new ApiResponseDefault(false, 'Produk Tidak Ditemukan!', null, 404);
        }

        return new ApiResponseDefault(true, 'Produk Ditemukan!', $product);
    }

    public function update(Request $request, $id)
    {
        $product = Product::where('id', $id)->where('user_id', Auth::id())->first();

        if (!$product) {
            return new ApiResponseDefault(false, 'Produk Tidak Ditemukan atau Anda Tidak Bisa Mengedit Produk Ini!', null, 404);
        }

        $messages = [
            'name.required' => 'Nama Produk Wajib Diisi!',
            'name.max' => 'Nama Produk Maksimal 255 Karakter!',
            'description.required' => 'Deskripsi Wajib Diisi!',
            'category.required' => 'Kategori Wajib Diisi!',
            'price.required' => 'Harga Wajib Diisi!',
            'price.numeric' => 'Harga Wajib Berupa Angka!',
            'stock.required' => 'Stock Wajib Diisi!',
            'stock.numeric' => 'Stock Wajib Berupa Angka!',
            'material.required' => 'Material wajib diisi!',
            'wide.required' => 'Lebar wajib diisi!',
            'wide.numeric' => 'Lebar wajib berupa angka!',
            'long.required' => 'Panjang wajib diisi!',
            'long.numeric' => 'Panjang wajib berupa angka!',
            'discount.numeric' => 'Diskon wajib berupa angka!',
            'image.array' => 'Bidang gambar harus berupa array.',
            'image.max' => 'Anda hanya dapat mengunggah maksimal :max gambar.',
            'image.*.image' => 'File pada salah satu gambar harus berupa gambar (jpeg, png, jpg, gif, svg).',
            'image.*.mimes' => 'Format file gambar tidak valid. Hanya format :values yang diizinkan.',
            'image.*.max' => 'Ukuran file salah satu gambar tidak boleh melebihi :max kilobyte.',
        ];

        $validator = Validator::make($request->all(), [
            'name' => 'required|max:255',
            'description' => 'required',
            'category' => 'required',
            'price' => 'required|numeric',
            'stock' => 'required|numeric',
            'material' => 'required',
            'wide' => 'required|numeric',
            'long' => 'required|numeric',
            'discount' => 'nullable|numeric',
            'image' => 'nullable|array|max:5',
            'image.*' => 'image|mimes:jpeg,png,jpg,gif,svg|max:2048',
        ], $messages);

        if ($validator->fails()) {
            return new ApiResponseDefault(false, $validator->errors(), null, 422);
        }

        $product->update([
            'name' => $request->name,
            'description' => $request->description,
            'category' => $request->category,
            'price' => $request->price,
            'stock' => $request->stock,
            'material' => $request->material,
            'wide' => $request->wide,
            'long' => $request->long,
            'discount' => $request->discount,
        ]);

        if ($request->hasFile("image")) {
            foreach ($product->images_product as $oldImage) {
                Storage::disk('public')->delete($oldImage->image);
                $oldImage->delete();
            }

            foreach ($request->file('image') as $imageFile) {
                $path = $imageFile->store('Product', 'public');
                ImagesProduct::create([
                    'product_id' => $product->id,
                    'image' => $path,
                ]);
            }
        }

        if (!$product) {
            return new ApiResponseDefault(false, 'Produk Gagal Diupdate!', null, 422);
        }

        $product->load('images_product');

        return new ApiResponseDefault(true, 'Produk Berhasil Diupdate!', $product);
    }

    public function delete($id)
    {
        if (Auth::user()->role != 'admin') {
            $product = Product::where('id', $id)->where('user_id', Auth::id())->first();
    
            if (!$product) {
                return new ApiResponseDefault(false, 'Produk Tidak Ditemukan atau Anda Tidak Bisa Menghapus Produk Ini!', null, 404);
            }
        } else {
            $product = Product::find($id);
    
            if (!$product) {
                return new ApiResponseDefault(false, 'Produk Tidak Ditemukan!', null, 404);
            }   
        }

        $images = ImagesProduct::where('product_id', $product->id)->get();

        foreach ($images as $image) {
            Storage::disk('public')->delete($image->image);
        }

        $product->delete();

        return new ApiResponseDefault(true, 'Produk Berhasil Dihapus!', Null, 200);
    }

    public function endek()
    {
        $endek = Product::where('category', 'Endek')->count();

        return new ApiResponseDefault(true, 'Berhasil menampilkan jumlah kain songket!', ['total'=>$endek]);
    }

    public function songket()
    {
        $songket = Product::where('category', 'Songket')->count();

        return new ApiResponseDefault(true, 'Berhasil menampilkan jumlah!', ['total'=>$songket]);
    }
}