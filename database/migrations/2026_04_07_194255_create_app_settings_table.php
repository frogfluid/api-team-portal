<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('app_settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->text('value')->nullable();
            $table->string('description')->nullable();
            $table->timestamps();
        });

        // Seed default: alert N days before contract expiry
        DB::table('app_settings')->insert([
            'key'         => 'contract_expiry_alert_days',
            'value'       => '30',
            'description' => 'How many days before contract expiry to send admin notifications',
            'created_at'  => now(),
            'updated_at'  => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('app_settings');
    }
};
