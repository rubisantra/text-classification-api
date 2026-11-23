<?php

return [
    'api_key' => env('AI_API_KEY'),
    'base_url' => env('AI_BASE_URL', 'https://api.groq.com/openai/v1'),
    'model' => env('AI_MODEL', 'llama-3.3-70b-versatile'),
    'timeout' => env('AI_TIMEOUT', 30),
];
