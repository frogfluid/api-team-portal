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
