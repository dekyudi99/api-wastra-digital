<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use App\Http\Resources\ApiResponseDefault;
use App\Models\ShippingAddress;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

class ShippingAddressController extends Controller
{
    public function index()
    {
        $address = ShippingAddress::where('user_id', Auth::id())->get();

        if ($address->isEmpty()) {
            return new ApiResponseDefault(true, 'Anda belum menentukan alamat pengiriman!');
        }

        return new ApiResponseDefault(true, "Berhasil menampilkan alamat pengiriman anda!", $address);
    }

    public function store(Request $request)
    {
        $validate = Validator::make($request->all(), [
            'received_name' => 'required|max:255',
            'telepon_number' => 'required|digits_between:10,13',
            'provinsi' => 'required|max:255',
            'kabupaten' => 'required|max:255',
            'kecamatan' => 'required|max:255',
            'kode_pos' => 'required|max:255',
            'alamat_detail' => 'required|max:255',
        ]);
        
        if ($validate->fails()) {
            return new ApiResponseDefault(false, $validate->errors()->first(), null, 422);
        }

        $address = ShippingAddress::create([
            'received_name' => $request->received_name,
            'telepon_number' => $request->telepon_number,
            'provinsi' => $request->provinsi,
            'kabupaten' => $request->kabupaten,
            'kecamatan' => $request->kecamatan,
            'kode_pos' => $request->kode_pos,
            'alamat_detail' => $request->alamat_detail,
            'user_id' => Auth::id(),
        ]);

        if (!$address) {
            return new ApiResponseDefault(false, 'Gagal menyimpan alamat!', null, 500);
        }

        return new ApiResponseDefault(true, 'Berhasil menyimpan alamat!', $address, 201);
    }

    public function show($id) 
    {
        $address = ShippingAddress::find($id);

        if (!$address) {
            return new ApiResponseDefault(false, "Alamat tidak ditemukan!", null, 404);
        }

        return new ApiResponseDefault(true, "Alamat berhasil ditampilkan!", $address);
    }

    public function update(Request $request, $id)
    {
        $address = ShippingAddress::find($id);

        if (!$address) {
            return new ApiResponseDefault(false, "Alamat tidak ditemukan!", null, 404);
        } elseif ($address->user_id != Auth::id()) {
            return new ApiResponseDefault(false, "Anda tidak bisa mengedit alamat pengguna lain!", null, 403);
        }

        $validate = Validator::make($request->all(), [
            'received_name' => 'required|max:255',
            'telepon_number' => 'required|digits_between:10,13',
            'provinsi' => 'required|max:255',
            'kabupaten' => 'required|max:255',
            'kecamatan' => 'required|max:255',
            'kode_pos' => 'required|max:255',
            'alamat_detail' => 'required|max:255',
        ]);
        
        if ($validate->fails()) {
            return new ApiResponseDefault(false, $validate->errors()->first(), null, 422);
        }

        $address->update([
            'received_name' => $request->received_name,
            'telepon_number' => $request->telepon_number,
            'provinsi' => $request->provinsi,
            'kabupaten' => $request->kabupaten,
            'kecamatan' => $request->kecamatan,
            'kode_pos' => $request->kode_pos,
            'alamat_detail' => $request->alamat_detail,
        ]);

        if (!$address) {
            return new ApiResponseDefault(false, 'Gagal menyimpan alamat!', null, 500);
        }

        return new ApiResponseDefault(true, 'Berhasil menyimpan alamat!', $address, 201);
    }

    public function delete($id)
    {
        $address = ShippingAddress::find($id);

        if (!$address) {
            return new ApiResponseDefault(false, "Alamat tidak ditemukan!", null, 404);
        } elseif ($address->user_id != Auth::id()) {
            return new ApiResponseDefault(false, "Anda tidak bisa mengedit alamat pengguna lain!", null, 403);
        }

        $address->delete();

        if (!$address) {
            return new ApiResponseDefault(false, "Gagal menghapus alamat!", 500);
        }

        return new ApiResponseDefault(true, "Alamat berhasil dihapus!");
    }
}
