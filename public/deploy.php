<?php
// Script sederhana untuk Webhook
$output = shell_exec("cd /var/www/wastradigital/backend && git pull origin main && composer install --no-dev && php artisan migrate --force");
echo "<pre>$output</pre>";