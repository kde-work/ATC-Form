<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

/*
| UI отдаёт nginx из frontend/dist (Angular SPA).
| Этот маршрут срабатывает только если запрос всё же попал в Laravel
| (например, php artisan serve без nginx).
*/
Route::get('/{any?}', function () {
    return response(
        'ATC Express API is running. Open the Angular app via http://atc.form (nginx + frontend build) or http://127.0.0.1:4200 (ng serve).',
        200,
        ['Content-Type' => 'text/plain; charset=UTF-8']
    );
})->where('any', '^(?!api).*$');
