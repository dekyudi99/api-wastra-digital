<?php

namespace App\Services;

use App\Mail\OtpMail;
use App\Models\TemporaryToken;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;

class OtpService
{
    public function generate($email)
    {
        $hasToken = TemporaryToken::where('email', $email)->first();

        if (!$hasToken) {
            $otp = TemporaryToken::create([
                'email'      => $email,
                'token'      => random_int(100000, 999999),
                'expired_at' => Carbon::now()->addMinutes(5),
            ]);
            Mail::to($email)->send(new OtpMail($otp->token));

            return $otp;
        }

        $hasToken->token = random_int(100000, 999999);
        $hasToken->expired_at = Carbon::now()->addMinutes(5);
        $hasToken->save();

        Mail::to($email)->send(new OtpMail($hasToken->token));
        return $hasToken;
    }

    public function verify($token)
    {
        $otp = TemporaryToken::where('email', Auth::user()->email)
            ->where('token', $token)
            ->first();

        if (!$otp || now()->greaterThan($otp->expired_at)) {
            return false;
        }

        $otp->delete();
        return true;
    }
}