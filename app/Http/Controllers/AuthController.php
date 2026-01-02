<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use App\Http\Resources\ApiResponseDefault;
use Illuminate\Support\Facades\Validator;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class AuthController extends Controller
{
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
            'role'      => 'required|in:pengguna,pengerajin',
            'password'  => 'required|min:8',
        ], $messages);

        if ($validator->fails()) {
            return new ApiResponseDefault(false, $validator->errors()->first(), null, 422);
        }

        $user = User::create([
            'name'      => $request->name,
            'email'     => $request->email,
            'role'      => $request->role,
            'password'  => Hash::make($request->password),
        ]);

        if (!$user) {
            return new ApiResponseDefault(false, 'Registrasi Gagal!', null, 422);
        }

        $token = $user->createToken('auth_token')->plainTextToken;

        return new ApiResponseDefault(
            true,
            'Registrasi Berhasil!',
            [
                $user,
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
}