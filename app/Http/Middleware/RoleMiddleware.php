<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Http\Resources\ApiResponseDefault;

class RoleMiddleware
{
    public function handle(Request $request, Closure $next, ...$roles)
    {
        if (!Auth::check() || !in_array(Auth::user()->role, $roles)) {
            return new ApiResponseDefault(false, 'Role Anda Tidak Sesuai, Anda Tidak Bisa Mengakses Routes Ini!', null, 403);
        }

        return $next($request);
    }
}