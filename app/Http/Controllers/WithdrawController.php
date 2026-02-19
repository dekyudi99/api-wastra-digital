<?php

namespace App\Http\Controllers;

use App\Http\Resources\ApiResponseDefault;
use Illuminate\Http\Request;
use App\Models\Wallet;
use App\Models\Withdrawal;
use App\Models\WalletMutation;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class WithdrawController extends Controller
{
    public function balance()
    {
        $wallet = Wallet::where('owner_id', Auth::id())->first();

        if (!$wallet) {
            return new ApiResponseDefault(false, 'Wallet tidak ditemukan!', null, 404);
        }

        return new ApiResponseDefault(true, "Berhasil mengambil wallet!", $wallet);
    }

    public function requestWithdraw(Request $request)
    {
        $request->validate([
            'amount'=>'required|integer|min:10000',
            'bank'=>'required',
            'rekening'=>'required'
        ]);

        $wallet = Wallet::where([
            'owner_type'=>'artisan',
            'owner_id'=>auth()->id()
        ])->firstOrFail();

        if ($wallet->balance < $request->amount) {
            return response()->json(['message'=>'Saldo tidak cukup'],422);
        }

        $ $withdrawal = Withdrawal::create([
            'seller_id'=>Auth::id(),
            'amount'=>$request->amount,
            'bank_name'=>$request->bank,
            'bank_account'=>$request->rekening,
        ]);

        return new ApiResponseDefault(true, "Withdraw berhasil diajukan!", $withdrawal);
    }

    public function approve($id)
    {
        $withdrawal = Withdrawal::findOrFail($id);

        $withdrawal->update([
            'status'=>'approved'
        ]);

        return new ApiResponseDefault(true, "Withdraw telah disetujui!", $withdrawal);
    }

    public function markPaid($id)
    {
        DB::transaction(function() use ($id){

            $withdrawal = Withdrawal::lockForUpdate()->findOrFail($id);

            if ($withdrawal->status !== 'approved') {
                abort(422);
            }

            $wallet = Wallet::lockForUpdate()->find($withdrawal->user->wallet->id);

            if ($wallet->balance < $withdrawal->amount) {
                throw new \Exception('Saldo berubah');
            }

            WalletMutation::create([
                'wallet_id'=>$wallet->id,
                'type'=>'debit',
                'amount'=>$withdrawal->amount,
                'source'=>'withdrawal',
                'ref_id'=>$withdrawal->id,
            ]);

            $wallet->decrement('balance',$withdrawal->amount);

            $withdrawal->update(['status'=>'paid']);
        });

        return new ApiResponseDefault(true, "Withdraw selesai!");
    }
}
