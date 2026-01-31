<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use App\Http\Resources\ApiResponseDefault;
use Illuminate\Support\Facades\Validator;

class AdminController extends Controller
{
    public function totalPendaftaran()
    {
        $user = User::where('isArtisan', 0)->count();

        return new ApiResponseDefault(true, "Berhasil menampilkan jumlah pendaftaran pengrajin", ['total' => $user]);
    }

    public function listPendaftaran()
    {
        $user = User::where('isArtisan', 0)->latest()->paginate(5);

        if ($user->isEmpty()) {
            return new ApiResponseDefault(true, "Belum ada pendaftaran!");
        }

        return new ApiResponseDefault(false, "Berhasil menampilkan list pendaftaran", $user);
    }

    public function confirm($id)
    {
        $user = User::find($id);

        if (!$user) {
            return new ApiResponseDefault(false, "User tidak ditemukan!", null, 404);
        }

        $user->update([
            'isArtisan' => 1,
        ]);

        return new ApiResponseDefault(true, "Berhasil mengkonfirmasi pengrajin!", $user);
    }

    public function totalActiveArtisan()
    {
        $user = User::where('isArtisan', 1)->count();

        return new ApiResponseDefault(true, "Berhasil menampilkan jumlah pengrajin active", ['total' => $user]);
    }

    public function listActiveArtisan()
    {
        $user = User::where('isArtisan', 1)->get();

        if ($user->isEmpty()) {
            return new ApiResponseDefault(true, "Belum ada pendaftaran!");
        }

        return new ApiResponseDefault(false, "Berhasil menampilkan list pendaftaran", $user);
    }

    public function deactiveArtisan($id)
    {
        $user = User::find($id);

        if (!$user) {
            return new ApiResponseDefault(false, "User tidak ditemukan!", null, 404);
        }

        $user->update([
            'isArtisan' => 0,
        ]);

        return new ApiResponseDefault(true, "Berhasil mengkonfirmasi pengrajin!", $user);
    }
}
