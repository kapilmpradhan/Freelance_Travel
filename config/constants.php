<?php

return [
    'base_url' => (env('APP_BASE_URL') !== null && env('APP_BASE_URL') !== '') ? env('APP_BASE_URL') : 'https://app.freelancetravel.com/',
    'backend_url' => (env('BACKEND_URL') !== null && env('BACKEND_URL') !== '') ? env('BACKEND_URL') : 'https://confirmation.freelancetravel.com'
];
