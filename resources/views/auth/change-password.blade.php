<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="Content-Security-Policy" content="upgrade-insecure-requests">
    <title>Ganti Password | Wastra Digital</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="icon" type="image/png" href="{{ asset('logo/logoWastraDigital.png') }}">
</head>
<body class="bg-light d-flex align-items-center justify-content-center" style="height: 100vh">
    <div class="card shadow p-4" style="width: 400px">
        <div class="text-center mb-4">
            <img src="{{ asset('logo/logoWastraDigital.png') }}" alt="Wastra Digital" style="width: 60%;">
        </div>
        <h4 class="text-center mb-3">Ganti Password</h4>
        
        <form method="POST" action="{{ url()->current() == request()->url() ? route('password.change') : str_replace('http:', 'https:', route('password.change')) }}">
            @csrf
            <input type="hidden" name="token" value="{{ $token ?? request('token') }}">
            <input type="hidden" name="email" value="{{ request('email') }}">

            <div class="mb-3">
                <label class="form-label">Password Baru</label>
                <input type="password" id="password" name="password" class="form-control" required minlength="8">
            </div>

            <div class="mb-3">
                <label class="form-label">Konfirmasi Password</label>
                <input type="password" id="confirmPassword" name="confirmPassword" class="form-control" required>
                <small id="message" class="mt-1" style="display: none;"></small>
            </div>

            <button type="submit" id="submitBtn" class="btn w-100" style="background-color: #964B00; color: white; padding: 10px; border-radius: 5px;">
                Perbarui Password
            </button>
        </form>
    </div>

    <script>
        const password = document.getElementById('password');
        const confirmPassword = document.getElementById('confirmPassword');
        const message = document.getElementById('message');
        const submitBtn = document.getElementById('submitBtn');

        function validatePassword() {
            if (confirmPassword.value.length > 0) {
                message.style.display = 'block';
                if (confirmPassword.value === password.value) {
                    message.innerHTML = '✓ Password cocok';
                    message.style.color = 'green';
                    submitBtn.disabled = false;
                } else {
                    message.innerHTML = '× Password tidak cocok';
                    message.style.color = 'red';
                    submitBtn.disabled = true;
                }
            } else {
                message.style.display = 'none';
            }
        }

        confirmPassword.addEventListener('input', validatePassword);
        password.addEventListener('input', validatePassword);
    </script>
</body>
</html>