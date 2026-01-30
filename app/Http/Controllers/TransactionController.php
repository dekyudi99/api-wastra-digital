<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\TransactionHistory;
use App\Http\Resources\ApiResponseDefault;
use App\Models\User;
use App\Models\AdminCommision;
use Dom\Attr;
use Illuminate\Support\Facades\Auth;

class TransactionController extends Controller
{
    public function index()
    {
        $transaction = TransactionHistory::paginate(5);

        if ($transaction->isEmpty()) {
            return new ApiResponseDefault(true, "Belum ada transaksi saat ini!");
        }

        return new ApiResponseDefault(true, "Berhasil menampilkan data transaksi!", $transaction);
    }

    public function saldoUser()
    {
        $user = User::find(Auth::id());

        $saldo = [
            'saldo' => $user->saldo,
        ];

        return new ApiResponseDefault(true, "Berhasil menampilkan saldo", $saldo);
    }

    public function commision()
    {
        if (Auth::user()->role != 'admin') {
            return new ApiResponseDefault(false, 'Anda tidak bisa mengakses ini!', null, 403);
        }

        $commission = AdminCommision::first();

        return new ApiResponseDefault(true, "Berhasil menampilkan commision!", $commission);
    }
}
