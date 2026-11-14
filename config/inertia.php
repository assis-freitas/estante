<?php

return [

    // The starter kit keeps pages in resources/js/pages (lowercase). Inertia's
    // default is js/Pages, which only works on case-insensitive filesystems.
    'testing' => [
        'ensure_pages_exist' => true,
        'page_paths' => [
            resource_path('js/pages'),
        ],
        'page_extensions' => ['js', 'jsx', 'svelte', 'ts', 'tsx', 'vue'],
    ],

];
