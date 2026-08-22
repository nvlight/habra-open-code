<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hubs', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('alias')->unique();
            $table->text('description')->nullable();
            $table->string('avatar')->nullable();
            $table->decimal('rating', 10)->default(0);
            $table->unsignedInteger('subscribers_count')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hubs');
    }
};
