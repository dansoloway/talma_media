<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('media', function (Blueprint $table) {
            $table->id();
            $table->string('drive_file_id')->unique();
            $table->string('name');
            $table->string('mime_type');
            $table->foreignId('folder_id')->nullable()->constrained('folders')->nullOnDelete();
            $table->string('web_view_link')->nullable();
            $table->string('thumb_path')->nullable();
            $table->string('md5', 32)->nullable()->index();
            $table->unsignedInteger('width')->nullable();
            $table->unsignedInteger('height')->nullable();
            $table->timestamp('taken_at')->nullable()->index();
            $table->string('taken_at_source')->nullable();
            $table->decimal('lat', 10, 7)->nullable();
            $table->decimal('lng', 10, 7)->nullable();
            $table->string('status')->default('active')->index();
            $table->text('caption')->nullable();
            $table->json('ai_raw')->nullable();
            $table->string('prompt_version')->nullable();
            $table->text('embedding')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('media');
    }
};
