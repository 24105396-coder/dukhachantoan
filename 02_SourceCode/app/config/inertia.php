<?php

return [
    'ssr' => [
        'enabled' => (bool) env('INERTIA_SSR_ENABLED', false),
        'url' => env('INERTIA_SSR_URL', 'http://127.0.0.1:13714'),
        'ensure_bundle_exists' => true,
    ],

    'ensure_pages_exist' => false,

    'page_paths' => [resource_path('js/Pages')],

    'page_extensions' => ['js', 'jsx', 'svelte', 'ts', 'tsx', 'vue'],

    'use_script_element_for_initial_page' => false,

    // Frontend được phát triển song song: không bắt buộc tệp trang tồn tại khi chạy test back-end
    'testing' => [
        'ensure_pages_exist' => (bool) env('INERTIA_TEST_ENSURE_PAGES', false),
        'page_paths' => [resource_path('js/Pages')],
        'page_extensions' => ['js', 'jsx', 'svelte', 'ts', 'tsx', 'vue'],
    ],

    'history' => [
        'encrypt' => (bool) env('INERTIA_ENCRYPT_HISTORY', false),
    ],
];
