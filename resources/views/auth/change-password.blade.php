<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Ganti Password | Wastra Digital</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="icon" type="image/png" href="{{ asset('logo/logoWastraDigital.png') }}">
</head>
<body class="bg-light d-flex align-items-center justify-content-center" style="height: 100vh">
    <div class="card shadow p-4" style="width: 400px">
        <div style="width: 100%; display: flex; justify-content: center;">
            <img src="{{ asset('logo/logoWastraDigital.png') }}" alt="Wastra Digital" style="width: 60%;">
        </div>
        <h4 class="text-center mb-3">Ganti Password</h4>
        <form method="POST" action="{{ route('password.change') }}">
            @csrf
            <input type="hidden" name="token" value="{{ request('token') }}">

            <div class="mb-3">
                <label>Password Baru</label>
                <input type="password" id="password" name="password" class="form-control" required>
            </div>

            <div class="mb-3">
                <label>Konfirmasi Password</label>
                <input type="password" id="confirmPassword" name="confirmPassword" class="form-control" required>
                <!-- Pesan validasi -->
                <small id="message" style="display: none;"></small>
            </div>

            <script>
                const password = document.getElementById('password');
                const confirmPassword = document.getElementById('confirmPassword');
                const message = document.getElementById('message');

                confirmPassword.addEventListener('input', function() {
                    // Tampilkan pesan hanya saat pengguna mulai mengisi
                    if (confirmPassword.value.length > 0) {
                        message.style.display = 'block';
                        
                        if (confirmPassword.value === password.value) {
                            message.innerHTML = 'Password sesuai';
                            message.style.color = 'green';
                        } else {
                            message.innerHTML = 'Password tidak sesuai';
                            message.style.color = 'red';
                        }
                    } else {
                        // Sembunyikan jika input dikosongkan kembali
                        message.style.display = 'none';
                    }
                });
            </script>

            <button type="submit" class="btn btn-primary w-100" style="background-color: #964B00; color: white; padding: 10px 20px; border: none; border-radius: 5px; cursor: pointer;">Perbarui Password</button>
        </form>
    </div>
</body>
</html>