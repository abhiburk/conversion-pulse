<?php

use App\Models\Site;

test('origins on the site domain or its subdomains are allowed', function (?string $origin) {
    $site = Site::factory()->make(['domain' => 'example.com']);

    expect($site->allowsOrigin($origin))->toBeTrue();
})->with([
    'no origin header' => null,
    'exact domain' => 'https://example.com',
    'subdomain' => 'https://shop.example.com',
    'uppercase host' => 'https://SHOP.Example.com',
]);

test('other origins are rejected', function (string $origin) {
    $site = Site::factory()->make(['domain' => 'example.com']);

    expect($site->allowsOrigin($origin))->toBeFalse();
})->with([
    'different domain' => 'https://attacker.test',
    'domain as suffix of another' => 'https://notexample.com',
    'domain as subdomain of attacker' => 'https://example.com.attacker.test',
    'opaque origin' => 'null',
    'different port' => 'https://example.com:8443',
]);

test('a port in the site domain must match the origin port', function () {
    $site = Site::factory()->make(['domain' => 'localhost:8000']);

    expect($site->allowsOrigin('http://localhost:8000'))->toBeTrue();
    expect($site->allowsOrigin('http://localhost:3000'))->toBeFalse();
});
