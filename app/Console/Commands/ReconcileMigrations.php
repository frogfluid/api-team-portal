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
                    $this->markAsRun($name, $batch);
                    continue;
                }
                $this->line("executing: {$name}");
                try {
                    $migration->up();
                } catch (QueryException $e) {
                    if ($this->isSchemaAlreadyAppliedError($e)) {
                        $this->markAsRun($name, $batch);
                        continue;
                    }
                    throw $e;
                }
                DB::table('migrations')->insert(['migration' => $name, 'batch' => $batch]);
                $this->info("executed: {$name}");
                continue;
            }

            // Case 2: add/alter/widen/allow/change/extend on an existing table.
            // Run up(); if it throws a schema-collision error, treat as already applied.
            try {
                $migration->up();
                DB::table('migrations')->insert(['migration' => $name, 'batch' => $batch]);
                $this->info("executed: {$name}");
            } catch (QueryException $e) {
                if ($this->isSchemaAlreadyAppliedError($e)) {
                    $this->markAsRun($name, $batch);
                    continue;
                }
                throw $e;
            }
        }

        return self::SUCCESS;
    }

    /**
     * Detect SQLSTATE driver-error codes indicating the schema change is already in place.
     * Matches only schema-collision codes — not data-integrity collisions like 1062
     * (Duplicate entry for key PRIMARY), which must still surface as real errors.
     */
    private function isSchemaAlreadyAppliedError(QueryException $e): bool
    {
        $code = (int) ($e->errorInfo[1] ?? 0);

        // 1050 — Table already exists
        // 1060 — Duplicate column name
        // 1061 — Duplicate key name
        // 1826 — Duplicate foreign key constraint name
        // 1091 — Can't DROP; check column/key exists (for idempotent dropIfExists mistakes)
        return in_array($code, [1050, 1060, 1061, 1826, 1091], true);
    }

    private function markAsRun(string $name, int $batch): void
    {
        DB::table('migrations')->insert(['migration' => $name, 'batch' => $batch]);
        $this->line("marked as run: {$name}");
    }
}
