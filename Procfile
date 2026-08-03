web: vendor/bin/heroku-php-apache2 public/
worker: php artisan queue:work --timeout=600 --sleep=3 --tries=3
scheduler: php artisan schedule:work
reverb: php artisan reverb:start
