<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\User;
use App\Http\Resources\ApiResponseDefault;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Hash;

class UserController extends Controller
{
    public function profile()
    {
        $user = User::find(Auth::id());

        return new ApiResponseDefault(true, 'Berhasil Menampilkan Profile', $user);
    }

    public function update(Request $request)
    {
        $user = User::find(Auth::id());

        if (!$user) {
            return new ApiResponseDefault(false, "User tidak ditemukan", null, 404);
        }

        $validator = Validator::make($request->all(), [
            "name" => "required|max:255",
            "profile_picture" => "nullable|image|mimes:jpeg,png,jpg,gif,svg|max:2048",
            'phone' => 'required|digits_between:10,13',
        ]);

        if ($validator->fails()) {
            return new ApiResponseDefault(false, $validator->errors()->first(), null, 422);
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
            "phone" => $request->phone,
            "profile_picture" => $path,
        ]);

        if (!$user) {
            return new ApiResponseDefault(false, "Gagal Mengupdate Data Pengguna!", null, 500);
        }

        return new ApiResponseDefault(true, "Berhasil Mengupdate Data Pengguna!", $user);
    }

    public function changePassword(Request $request)
    {
        $user = User::find(Auth::id());

        $validator = Validator::make($request->all(), [
            'current_password' => 'required',
            'new_password' => 'required|min:8',
            'confirm_password' => 'required|same:new_password',
        ]);

        if ($validator->fails()) {
            return new ApiResponseDefault(false, $validator->errors()->first(), null, 422);
        }

        // VALIDASI: Cek apakah password lama benar
        if (!Hash::check($request->current_password, $user->password)) {
            return new ApiResponseDefault(false, "Password saat ini salah!", null, 400);
        }

        $user->update([
            'password' => Hash::make($request->new_password),
        ]);

        return new ApiResponseDefault(true, "Password Berhasil Diperbarui!", null);
    }
}