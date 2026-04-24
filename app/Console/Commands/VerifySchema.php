<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class VerifySchema extends Command
{
    protected $signature = 'verify:schema';
    protected $description = 'Diff api-team-portal migration filenames against the web project. Non-zero exit on drift.';

    public function handle(): int
    {
        $webPath = config('schema-parity.web_migrations_path');
        $apiPath = config('schema-parity.api_migrations_path');

        if (! is_dir($webPath)) {
            $this->error("Web migrations path not found: {$webPath}");
            return self::FAILURE;
        }

        if (! is_dir($apiPath)) {
            $this->error("API migrations path not found: {$apiPath}");
            return self::FAILURE;
        }

        $web = collect(scandir($webPath))->filter(fn ($f) => str_ends_with($f, '.php'))->values();
        $api = collect(scandir($apiPath))->filter(fn ($f) => str_ends_with($f, '.php'))->values();

        $missingOnApi = $web->diff($api)->values();
        $extraOnApi = $api->diff($web)->values();

        if ($missingOnApi->isEmpty() && $extraOnApi->isEmpty()) {
            $this->info('Schema parity OK: migration sets match.');
            return self::SUCCESS;
        }

        if ($missingOnApi->isNotEmpty()) {
            $this->line('');
            $this->warn('missing on API (present in web):');
            $missingOnApi->each(fn ($f) => $this->line("  - {$f}"));
        }

        if ($extraOnApi->isNotEmpty()) {
            $this->line('');
            $this->warn('extra on API (not in web):');
            $extraOnApi->each(fn ($f) => $this->line("  - {$f}"));
        }

        return self::FAILURE;
    }
}
