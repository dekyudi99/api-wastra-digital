<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\User;
use App\Http\Resources\ApiResponseDefault;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class UserController extends Controller
{
    public function profile()
    {
        $user = User::find(Auth::id());

        return new ApiResponseDefault(true, 'Berhasil Menampilkan Profile', $user);
    }

    public function update(Request $request)
    {
        $userId = Auth::id();
        $user = User::find($userId);

        if (!$user) {
            return new ApiResponseDefault(false, "User tidak ditemukan", null, 404);
        }

        $messages = [
            "name.required" => "Nama Wajib Diisi!",
            "name.max" => "Nama Maksimal :values Karakter!",
            'profile_picture.image' => 'File pada salah satu gambar harus berupa gambar (jpeg, png, jpg, gif, svg).',
            'profile_picture.mimes' => 'Format file gambar tidak valid. Hanya format :values yang diizinkan.',
            'profile_picture.max' => 'Ukuran file salah satu gambar tidak boleh melebihi :max kilobyte.',
        ];

        $validator = Validator::make($request->all(), [
            "name" => "required|max:255",
            "profile_picture" => "nullable|image|mimes:jpeg,png,jpg,gif,svg|max:2048"
        ], $messages);

        if ($validator->fails()) {
            return new ApiResponseDefault(false, $validator->errors(), null, 422);
        }

        if ($request->hasFile("profile_picture")) {
            if ($user->profile_picture) {
                Storage::disk("public")->delete($user->profile_picture);
            }

            $path = $request->profile_picture->store("profile", "public");
        } else {
            $path = $user->profile_picture;
        }

        $user->update([
            "name" => $request->name,
            "phone_number" => $request->phone_number,
            "address" => $request->address,
            "profile_picture" => $path,
        ]);

        if (!$user) {
            return new ApiResponseDefault(false, "Gagal Mengupdate Data Pengguna!", null, 500);
        }

        return new ApiResponseDefault(true, "Berhasil Mengupdate Data Pengguna!", $user);
    }
}