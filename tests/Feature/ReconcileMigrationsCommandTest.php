<?php

use Illuminate\Support\Facades\DB;
use function Pest\Laravel\artisan;

it('marks a migration as run when the table already exists', function () {
    $filename = '2026_01_01_000000_create_test_widget_table';
    $path = database_path("migrations/{$filename}.php");

    try {
        DB::statement('CREATE TABLE IF NOT EXISTS test_widget_table (id int)');
        DB::table('migrations')->where('migration', 'like', '%test_widget%')->delete();

        file_put_contents(
            $path,
            "<?php return new class extends \\Illuminate\\Database\\Migrations\\Migration { public function up(): void { \\Illuminate\\Support\\Facades\\Schema::create('test_widget_table', fn (\\Illuminate\\Database\\Schema\\Blueprint \$t) => \$t->id()); } public function down(): void { \\Illuminate\\Support\\Facades\\Schema::dropIfExists('test_widget_table'); } };",
        );

        artisan('migrate:reconcile', ['--only' => $filename])
            ->expectsOutputToContain("marked as run: {$filename}")
            ->assertExitCode(0);

        expect(DB::table('migrations')->where('migration', $filename)->exists())->toBeTrue();
    } finally {
        @unlink($path);
        DB::table('migrations')->where('migration', $filename)->delete();
        DB::statement('DROP TABLE IF EXISTS test_widget_table');
    }
});

it('executes a migration when target schema does not exist', function () {
    $filename = '2026_01_02_000000_create_test_newthing_table';
    $path = database_path("migrations/{$filename}.php");

    try {
        DB::statement('DROP TABLE IF EXISTS test_newthing');
        DB::table('migrations')->where('migration', 'like', '%test_newthing%')->delete();

        file_put_contents(
            $path,
            "<?php return new class extends \\Illuminate\\Database\\Migrations\\Migration { public function up(): void { \\Illuminate\\Support\\Facades\\Schema::create('test_newthing', fn (\\Illuminate\\Database\\Schema\\Blueprint \$t) => \$t->id()); } public function down(): void { \\Illuminate\\Support\\Facades\\Schema::dropIfExists('test_newthing'); } };",
        );

        artisan('migrate:reconcile', ['--only' => $filename])
            ->expectsOutputToContain("executed: {$filename}")
            ->assertExitCode(0);

        expect(\Illuminate\Support\Facades\Schema::hasTable('test_newthing'))->toBeTrue();
    } finally {
        \Illuminate\Support\Facades\Schema::dropIfExists('test_newthing');
        @unlink($path);
        DB::table('migrations')->where('migration', $filename)->delete();
    }
});
