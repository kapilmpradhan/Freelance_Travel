<?php

return [
    'secret' => env('JWT_SECRET', 'your-secret-key'),
    'token_validity' => env('JWT_TOKEN_VALIDITY', 60), // 60 minutes default
];
