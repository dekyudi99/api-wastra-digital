<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use App\Http\Resources\ApiResponseDefault;
use Illuminate\Support\Facades\Validator;
use App\Models\Wallet;
use App\Models\OrderItem;
use App\Models\AuditLog;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\Auth;

class AdminController extends Controller
{
    public function commision() {
        $wallet = Wallet::where('owner_type', 'admin')->first();

        return new ApiResponseDefault(true, 'Berhasil menampilkan saldo!', ['saldo' => $wallet->balance]);
    }

    public function totalPendaftaran()
    {
        $user = User::where('email_verified', 1)->where('status', 'pending')->count();

        return new ApiResponseDefault(true, "Berhasil menampilkan jumlah pendaftaran pengrajin", ['total' => $user]);
    }

    public function listPendaftaran()
    {
        $user = User::where('email_verified', 1)->where('status', 'pending')->latest()->paginate(5);

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

        $oldData = $user->status;

        $user->update([
            'status' => 'approved',
        ]);

        $actor = User::find(Auth::id());

        AuditLogger::log(
            actor: $actor,
            action: 'confirm_artisan_status',
            model: $user,
            old: [
                'status' => $oldData,
            ],
            new: [
                'status' => $user->status,
            ]
        );

        return new ApiResponseDefault(true, "Berhasil mengkonfirmasi pengrajin!", $user);
    }

    public function totalActiveArtisan()
    {
        $user = User::where('email_verified', 1)->where('role', 'artisan')->where('status', 'approved')->count();

        return new ApiResponseDefault(true, "Berhasil menampilkan jumlah pengrajin active", ['total' => $user]);
    }

    public function listActiveArtisan()
    {
        $user = User::where('email_verified', 1)->where('status', 'approved')->get();

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

        $oldData = $user->status;

        $user->update([
            'status' => 'rejected',
        ]);

        $actor = User::find(Auth::id());

        AuditLogger::log(
            actor: $actor,
            action: 'rejected_artisan_status',
            model: $user,
            old: [
                'status' => $oldData,
            ],
            new: [
                'status' => $user->status,
            ]
        );

        return new ApiResponseDefault(true, "Berhasil mengkonfirmasi pengrajin!", $user);
    }

    public function orderOnProgress()
    {
        $order = OrderItem::whereNot('item_status', ['cancelled', 'finish'])->count();

        return new ApiResponseDefault(true, "Berhasil mengampilkan pesanan aktif!", ['total' => $order]);
    }

    public function orderItem() {
        $orderItem = OrderItem::whereNot('item_status', 'cancelled')->with('seller', 'order.buyer')->latest()->limit(5)->get();

        if ($orderItem->isEmpty()) {
            return new ApiResponseDefault(true, "Belum ada pesanan!");
        }

        return new ApiResponseDefault(true, "Berhasil mengambil 5 pesanan terbaru!", $orderItem);
    }

    public function logging()
    {
        $auditLog = AuditLog::with('User')->latest()->limit(5)->get();

        if ($auditLog->isEmpty()) {
            return new ApiResponseDefault(true, "Belum ada tindakan apapun yang dilakukan pengguna!");
        }

        return new ApiResponseDefault(true, "Berhasil menampilkan aktivitas terbaru!", $auditLog);
    }

    public function activeArtisanList()
    {
        $user = User::where('role', 'artisan')->paginate(5);

        if ($user->isEmpty()) {
            return new ApiResponseDefault(true, "Belum ada pengrajin aktif!");
        }

        return new ApiResponseDefault(true, "Berhasil menampilkan pengrajin aktif!", $user);
    }
}
