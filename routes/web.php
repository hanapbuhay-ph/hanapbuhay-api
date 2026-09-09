<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/phpinfo', function () {
    return [
        'curl_cainfo' => ini_get('curl.cainfo'),
        'openssl_cafile' => ini_get('openssl.cafile'),
        'loaded_ini' => php_ini_loaded_file(),
    ];
});
