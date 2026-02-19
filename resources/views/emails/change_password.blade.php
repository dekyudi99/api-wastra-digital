@component('mail::message')
# Pergantian Password

Halo **{{ $user->name }}**,  
Silakan klik tombol di bawah ini untuk mengganti password Anda.

@component('mail::button', ['url' => config('app.url') . '/change-password?token=' . $token])
Ganti Password
@endcomponent

Jika Anda tidak meminta pergantian password, abaikan email ini.

Terima kasih,  
**{{ config('app.name') }}**
@endcomponent