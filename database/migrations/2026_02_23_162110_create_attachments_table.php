<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('attachments', function (Blueprint $table) {
            $table->id();

            // Polymorphic: attachable_type, attachable_id + index
            $table->morphs('attachable');

            // 保存先情報
            $table->string('disk', 50)->default('public');
            $table->string('path'); // Storage上の相対パス

            // メタ情報
            $table->string('original_name');
            $table->string('mime_type', 100)->nullable();
            $table->unsignedBigInteger('size_bytes')->nullable();
            $table->string('caption')->nullable();

            // 誰がアップしたか（任意だが、後々便利）
            $table->foreignId('uploaded_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamps();

            // よく使う検索用（attachableはmorphsにindex済み）
            $table->index('uploaded_by', 'ix_attachments_uploaded_by');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attachments');
    }
};
