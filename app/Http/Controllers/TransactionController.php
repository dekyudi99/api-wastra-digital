<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\TransactionHistory;
use App\Http\Resources\ApiResponseDefault;

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
}
