<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use App\Http\Resources\ApiResponseDefault;
use Illuminate\Support\Facades\Validator;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use App\Services\OtpService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class AuthController extends Controller
{
    protected $otpService;

    public function __construct(OtpService $otpService)
    {
        $this->otpService = $otpService;
    }

    public function register(Request $request)
    {
        $messages = [
            'name.required'=> 'Nama wajib diisi!',
            'name.min' => 'Nama minimal 3 karakter!',
            'name.max' => 'Nama maksimal 100 karakter!',
            'email.required' => 'Email wajib diisi!',
            'email.email' => 'Format email salah!',
            'email.unique' => 'Email sudah digunakan!',
            'role.required' => 'Role wajib diisi!',
            'role.in' => 'Role wajib berupa :values',
            'password.required' => 'Password wajib diisi!',
            'password.min' => 'Password minimal 8 karakter!',
        ];

        $validator = Validator::make($request->all(), [
            'name'      => 'required|min:3|max:100',
            'email'     => 'required|email|unique:users',
            'role'      => 'required|in:artisan,customer',
            'password'  => 'required|min:8',
            'ktp'       => 'required_if:role,artisan|image|mimes:jpeg,png,jpg|max:2048',
            'village'   => 'required_if:role,artisan|string',
        ], $messages);

        if ($validator->fails()) {
            return new ApiResponseDefault(false, $validator->errors()->first(), null, 422);
        }

        if ($request->role == 'artisan') {
            $path = $request->file('ktp')->store('ktp', 'public');

            $user = User::create([
                'name'      => $request->name,
                'email'     => $request->email,
                'role'      => $request->role,
                'password'  => Hash::make($request->password),
                'ktp'       => $path,
                'address'   => $request->village,
            ]);
        } else {
            $user = User::create([
                'name'      => $request->name,
                'email'     => $request->email,
                'role'      => $request->role,
                'password'  => Hash::make($request->password),
            ]);
        }

        if (!$user) {
            return new ApiResponseDefault(false, 'Registrasi Gagal!', null, 422);
        }

        $token = $user->createToken('auth_token')->plainTextToken;
        $this->otpService->generate($request->email);

        return new ApiResponseDefault(
            true,
            'Registrasi Berhasil!',
            [
                'user'  => $user,
                'token' => $token,
            ],
            201
        );
    }

    public function login(Request $request)
    {
        $messages = [
            'email.required' => 'Email wajib diisi!',
            'email.email' => 'Format email salah!',
            'password.required' => 'Password wajib diisi!',
            'password.min' => 'Password minimal 8 karakter!',
        ];

        $validator = Validator::make($request->all(), [
            'email'     => 'required|email',
            'password'  => 'required|min:8',
        ], $messages);

        if ($validator->fails()) {
            return new ApiResponseDefault(false, $validator->errors()->first(), null, 422);
        }

        $user = User::where('email', $request->email)->first();
        if (!$user || !Hash::check($request->password, $user->password)) {
            return new ApiResponseDefault(false, 'Email atau Password salah', null, 422);
        }

        if (!$user->email_verified) {
            $this->otpService->generate($request->email);
        }
        
        $token = $user->createToken('auth_token')->plainTextToken;

        return new ApiResponseDefault(true, 'Login Berhasil!', [
            'user'  => $user,
            'token' => $token,
        ]);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return new ApiResponseDefault(true, 'Logout Berhasil');
    }

    public function changePassword(Request $request)
    {
        $messages = [
            'password.required' => 'Password Wajib Diisi!',
            'password.min' => 'Password Minimal 8 Karakter!',
            'confirmPassword.required' => 'Konfirmasi Password Wajib Diisi!',
            'confirmPassword.same' => 'Konfirmasi Password Harus Sama!',
        ];

        $validator = Validator::make($request->all(), [
            'token' => 'required',
            'password'=> 'required|min:8|same:confirmPassword',
            'confirmPassword' => 'required|min:8',
        ], $messages);

        if ($validator->fails()) {
            return new ApiResponseDefault(false, $validator->errors(), null, 422);
        }

        $reset = DB::table('password_reset_tokens')->where('token', $request->token)->first();

        if (!$reset) {
            return new ApiResponseDefault(false, 'Token tidak valid atau sudah kadaluarsa.', null, 400);
        }

        $user = User::where('email', $reset->email)->first();
        $user->update(['password' => Hash::make($request->password)]);

        DB::table('password_reset_tokens')->where('email', $reset->email)->delete();

        return new ApiResponseDefault(true, 'Password berhasil diperbarui.');
    }

    public function verifyEmail(Request $request) {
        $messages = [
            'otp.required' => 'Kode OTP wajib diisi!',
            'otp.digits' => 'Kode OTP wajib 6 digit!',
            'otp.integer' => 'Kode OTP wajib Berupa Integer',
        ];
        
        $validator = Validator::make($request->all(), [
            'otp' => 'required|integer|digits:6',
        ], $messages);

        if ($validator->fails()) {
            return new ApiResponseDefault(false, $validator->errors(), null, 422);
        }

        $succeed = $this->otpService->verify($request->otp);

        if (!$succeed) {
            return new ApiResponseDefault(false, 'Kode OTP salah atau Sudah Kadaluwarsa!', null, 422);
        }

        $user = User::where('id', Auth::user()->id)->first();
        $user->email_verified = 1;
        $user->save();

        return new ApiResponseDefault(true, 'Verifikasi Email Berhasil', Null);
    }

    // public function forgetPassword(Request $request)
    // {
    //     $messages = [
    //         'email.required' => 'Email Wajib Diisi!',
    //         'email.email' => 'Format Email Salah!',
    //         'email.exists' => 'Email Tidak Terdaftar!',
    //     ];

    //     $validator = Validator::make($request->all(), [
    //         'email'=> 'required|email|exists:users,email',
    //     ], $messages);

    //     if ($validator->fails()) {
    //         return new ApiResponseDefault(false, $validator->errors(), null, 422);
    //     }

    //     $user = User::where('email', $request->email)->first();

    //     $token = Str::random(60);

    //     DB::table('password_reset_tokens')->updateOrInsert(
    //         ['email' => $user->email],
    //         ['token' => $token],
    //     );

    //     Mail::to($user->email)->send(new ChangePasswordMail($user, $token));

    //     return new ApiResponseDefault(true, 'Link Ganti Password Telah Dikirim ke Email');
    // }

    public function sendToken()
    {
        $this->otpService->generate(Auth::user()->email);
    }
}