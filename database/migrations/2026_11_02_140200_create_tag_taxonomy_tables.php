<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tag_categories', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->string('label_en');
            $table->string('label_he');
            $table->boolean('multi')->default(true);
            $table->boolean('ai_enabled')->default(true);
            $table->timestamps();
        });

        Schema::create('tags', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->constrained('tag_categories')->cascadeOnDelete();
            $table->string('value');
            $table->string('label_en');
            $table->string('label_he');
            $table->json('synonyms')->nullable();
            $table->timestamps();

            $table->unique(['category_id', 'value']);
        });

        Schema::create('media_tags', function (Blueprint $table) {
            $table->id();
            $table->foreignId('media_id')->constrained('media')->cascadeOnDelete();
            $table->foreignId('tag_id')->constrained('tags')->cascadeOnDelete();
            $table->string('source');
            $table->decimal('confidence', 4, 3)->nullable();
            $table->string('status')->default('suggested');
            $table->foreignId('by_user')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['media_id', 'tag_id']);
            $table->index(['status', 'source']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('media_tags');
        Schema::dropIfExists('tags');
        Schema::dropIfExists('tag_categories');
    }
};
