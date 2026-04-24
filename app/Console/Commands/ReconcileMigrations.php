<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;

class ReconcileMigrations extends Command
{
    protected $signature = 'migrate:reconcile {--only= : limit to a single migration filename}';
    protected $description = 'For each pending migration, either mark as run (schema already applied) or execute it.';

    public function handle(): int
    {
        $ran = DB::table('migrations')->pluck('migration')->all();

        $files = collect(File::files(database_path('migrations')))
            ->filter(fn ($f) => str_ends_with($f->getFilename(), '.php'))
            ->map(fn ($f) => pathinfo($f->getFilename(), PATHINFO_FILENAME))
            ->sort()
            ->values();

        if ($only = $this->option('only')) {
            $files = $files->filter(fn ($f) => $f === $only)->values();
        }

        $pending = $files->reject(fn ($name) => in_array($name, $ran, true))->values();

        if ($pending->isEmpty()) {
            $this->info('No pending migrations.');
            return self::SUCCESS;
        }

        $batch = (int) (DB::table('migrations')->max('batch') ?? 0) + 1;

        foreach ($pending as $name) {
            $path = database_path("migrations/{$name}.php");
            $migration = require $path;

            // Case 1: create_X_table — check if table already exists.
            if (preg_match('/_create_(?<table>[a-z0-9_]+)_table$/', $name, $m)) {
                if (Schema::hasTable($m['table'])) {
                    DB::table('migrations')->insert(['migration' => $name, 'batch' => $batch]);
                    $this->line("marked as run: {$name}");
                    continue;
                }
                $this->line("executing: {$name}");
                try {
                    $migration->up();
                } catch (QueryException $e) {
                    $msg = strtolower($e->getMessage());
                    if (str_contains($msg, 'duplicate') || str_contains($msg, 'already exists')) {
                        DB::table('migrations')->insert(['migration' => $name, 'batch' => $batch]);
                        $this->line("marked as run: {$name}");
                        continue;
                    }
                    throw $e;
                }
                DB::table('migrations')->insert(['migration' => $name, 'batch' => $batch]);
                $this->info("executed: {$name}");
                continue;
            }

            // Case 2: add/alter/widen/allow/change/extend on an existing table.
            // Run up(); if it throws a duplicate/exists error, treat as already applied.
            try {
                $migration->up();
                DB::table('migrations')->insert(['migration' => $name, 'batch' => $batch]);
                $this->info("executed: {$name}");
            } catch (QueryException $e) {
                $msg = strtolower($e->getMessage());
                if (str_contains($msg, 'duplicate') || str_contains($msg, 'already exists')) {
                    DB::table('migrations')->insert(['migration' => $name, 'batch' => $batch]);
                    $this->line("marked as run: {$name}");
                    continue;
                }
                throw $e;
            }
        }

        return self::SUCCESS;
    }
}
