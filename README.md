<h1 align="center">Api Wastra Digital</h1>

# Requirements
- php versi 8.1 ke atas

# Package Reqiurements
- Laravel Sanctum 
    - `composer require laravel/sanctum` Install Laravel Sanctum Untuk Akses Token
    - `php artisan vendor:publish --provider="Laravel\Sanctum\SanctumServiceProvider"` Publish Konfigurasi
- Jalankan Storage Link
    - `php artisan storage:link` or `ln -s ../storage/app/public public/storage` for linux
- Midtrans
    - `composer require midtrans/midtrans-php`

# Cara Menjalankan
- `composer install` menginstall dependensi
- `cp .env.example .env` mengcopy .env.example menjadi .env dan lakukan konfigurasi
- Setting Env
- `php artisan key:generate` membuat key aplikasi laravel
- `php artisan migrate` membuat database
- `php -S localhost:8000 -t public` menjalankan laravel di jaringan lokal

# Midtran Payment Simulator
`[Midtran Payment Simulator](https://simulator.sandbox.midtrans.com/)`