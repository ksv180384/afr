<?php

return [
    'ssr' => [
        'enabled' => env('INERTIA_SSR_ENABLED', false),
        'url' => env('INERTIA_SSR_APP_URL', 'http://127.0.0.1:5010'),
        'public_url' => env('INERTIA_SSR_APP_URL', 'http://127.0.0.1:5010'),
        'admin_url' => env('INERTIA_SSR_ADMIN_URL', 'http://127.0.0.1:13714'),
    ],
    'testing' => [
        'ensure_pages_exist' => true,
        'page_paths' => [resource_path('js/App/Pages'), resource_path('js/Admin/Pages')],
        'page_extensions' => ['js', 'jsx', 'svelte', 'ts', 'tsx', 'vue'],
    ],
];
