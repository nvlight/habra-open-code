<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('publications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('company_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type')->index();
            $table->string('status')->default('draft')->index();
            $table->string('title');
            $table->text('lead')->nullable();
            $table->longText('body');
            $table->string('cover')->nullable();
            $table->string('difficulty')->nullable()->index();
            $table->string('label')->nullable();
            $table->boolean('is_translation')->default(false);
            $table->string('source_url')->nullable();
            $table->string('original_author')->nullable();
            $table->boolean('is_recovery_mode')->default(false);
            $table->unsignedSmallInteger('reading_time')->default(0);
            $table->unsignedBigInteger('views_count')->default(0);
            $table->unsignedBigInteger('reach')->default(0);
            $table->integer('rating')->default(0)->index();
            $table->unsignedInteger('votes_up')->default(0);
            $table->unsignedInteger('votes_down')->default(0);
            $table->unsignedInteger('comments_count')->default(0);
            $table->unsignedInteger('bookmarks_count')->default(0);
            $table->timestamp('published_at')->nullable()->index();
            $table->timestamps();
        });

        Schema::create('hub_publication', function (Blueprint $table) {
            $table->foreignId('publication_id')->constrained()->cascadeOnDelete();
            $table->foreignId('hub_id')->constrained()->cascadeOnDelete();
            $table->primary(['publication_id', 'hub_id']);
        });

        Schema::create('tags', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->timestamps();
        });

        Schema::create('publication_tag', function (Blueprint $table) {
            $table->foreignId('publication_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tag_id')->constrained()->cascadeOnDelete();
            $table->primary(['publication_id', 'tag_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('publication_tag');
        Schema::dropIfExists('tags');
        Schema::dropIfExists('hub_publication');
        Schema::dropIfExists('publications');
    }
};
