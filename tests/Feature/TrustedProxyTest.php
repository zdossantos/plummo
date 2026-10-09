<?php

use Illuminate\Http\Middleware\TrustProxies;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

beforeEach(function () {
    TrustProxies::flushState();
});

afterEach(function () {
    putenv('TRUSTED_PROXIES');
    unset($_ENV['TRUSTED_PROXIES'], $_SERVER['TRUSTED_PROXIES']);
    TrustProxies::flushState();
    Request::setTrustedProxies([], -1);
});

it('preserves HTTPS and client IP behind the configured Coolify proxy without trusting forwarded hosts', function () {
    putenv('TRUSTED_PROXIES=10.0.0.0/8');
    $_ENV['TRUSTED_PROXIES'] = '10.0.0.0/8';
    $this->refreshApplication();
    Route::get('/proxy-check', fn (Request $request) => [
        'secure' => $request->isSecure(),
        'host' => $request->getHost(),
        'ip' => $request->ip(),
    ]);

    $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.2'])
        ->withHeaders([
            'Host' => 'plummo.zdossantos.fr',
            'X-Forwarded-Proto' => 'https',
            'X-Forwarded-Port' => '443',
            'X-Forwarded-For' => '203.0.113.10',
            'X-Forwarded-Host' => 'untrusted.example',
        ])->getJson('http://plummo.zdossantos.fr/proxy-check')->assertExactJson([
            'secure' => true,
            'host' => 'plummo.zdossantos.fr',
            'ip' => '203.0.113.10',
        ]);
});

it('ignores forwarded HTTPS from outside the configured proxy range', function () {
    putenv('TRUSTED_PROXIES=10.0.0.0/8');
    $_ENV['TRUSTED_PROXIES'] = '10.0.0.0/8';
    $this->refreshApplication();
    Route::get('/proxy-check', fn (Request $request) => ['secure' => $request->isSecure()]);

    $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.10'])
        ->withHeader('X-Forwarded-Proto', 'https')
        ->getJson('http://plummo.zdossantos.fr/proxy-check')->assertExactJson(['secure' => false]);
});
