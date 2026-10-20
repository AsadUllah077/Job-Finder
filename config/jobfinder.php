<?php

return [
    'display_timezone' => 'Asia/Karachi',
    'adzuna_id' => env('ADZUNA_APP_ID'),
    'adzuna_key' => env('ADZUNA_APP_KEY'),
    'mail_enabled' => (bool) env('JOB_ALERTS_MAIL_ENABLED', false),
    'http_timeout' => 25,
    'default_keywords' => ['Laravel', 'PHP', 'MySQL', 'Vue.js', 'REST API', 'ERP'],
    'default_locations' => ['UAE', 'Dubai', 'Abu Dhabi', 'Sharjah', 'Pakistan', 'Islamabad', 'Rawalpindi', 'Lahore', 'Karachi', 'Peshawar', 'Saudi Arabia', 'Qatar'],
];
