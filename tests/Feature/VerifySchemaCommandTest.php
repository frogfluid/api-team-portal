<?php

use function Pest\Laravel\artisan;

it('exits 0 when migration sets match', function () {
    config(['schema-parity.web_migrations_path' => base_path('tests/fixtures/migrations-match/web')]);
    config(['schema-parity.api_migrations_path' => base_path('tests/fixtures/migrations-match/api')]);

    artisan('verify:schema')->assertExitCode(0);
});

it('exits 1 and prints missing filenames when api is behind web', function () {
    config(['schema-parity.web_migrations_path' => base_path('tests/fixtures/migrations-drift/web')]);
    config(['schema-parity.api_migrations_path' => base_path('tests/fixtures/migrations-drift/api')]);

    artisan('verify:schema')
        ->expectsOutputToContain('missing on API')
        ->assertExitCode(1);
});

it('exits 1 with error when web path does not exist', function () {
    config(['schema-parity.web_migrations_path' => '/nonexistent/path-that-should-not-exist']);
    config(['schema-parity.api_migrations_path' => base_path('tests/fixtures/migrations-match/api')]);

    artisan('verify:schema')
        ->expectsOutputToContain('Web migrations path not found')
        ->assertExitCode(1);
});

it('exits 1 and prints extra-on-api when api is ahead of web', function () {
    // Reuse the drift fixtures with roles swapped: the existing "api" side has 1 file, "web" side has 2 files.
    // Swapping gives us: web has 1, api has 2 → api is ahead.
    config(['schema-parity.web_migrations_path' => base_path('tests/fixtures/migrations-drift/api')]);
    config(['schema-parity.api_migrations_path' => base_path('tests/fixtures/migrations-drift/web')]);

    artisan('verify:schema')
        ->expectsOutputToContain('extra on API')
        ->assertExitCode(1);
});
