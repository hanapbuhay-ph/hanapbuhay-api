<?php

return [
    'disk' => env('MEDIA_DISK', 'public'),
    'cloudinary_url' => env('CLOUDINARY_URL'),
    'private_url_minutes' => (int) env('PRIVATE_MEDIA_URL_MINUTES', 5),
];
