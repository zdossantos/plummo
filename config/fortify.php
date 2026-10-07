<?php

return [
    'guard' => 'web',
    'passwords' => 'users',
    'username' => 'email',
    'email' => 'email',
    'views' => true,
    'prefix' => 'admin',
    'home' => '/admin',
    'lowercase_usernames' => true,
    'middleware' => ['web'],
    'limiters' => ['login' => 'admin-login'],
    'redirects' => ['login' => '/admin', 'logout' => '/admin/login'],
    'features' => [],
];
