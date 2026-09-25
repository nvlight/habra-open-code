<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('habr_sources', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('source_id')->unique();
            $table->string('url')->unique();
            $table->string('type')->default('article')->index();
            $table->string('company_slug')->nullable();
            $table->string('title')->nullable();
            $table->timestamp('published_at')->nullable()->index();
            $table->timestamp('lastmod')->nullable();
            $table->string('status')->default('pending')->index();
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->unsignedInteger('http_status')->nullable();
            $table->string('content_file')->nullable();
            $table->string('content_hash', 64)->nullable();
            $table->timestamp('fetched_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('habr_sources');
    }
};
