<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('catalog_books', function (Blueprint $table) {
            $table->id();
            $table->char('isbn', 13)->nullable()->unique();
            $table->string('title');
            $table->string('subtitle')->nullable();
            $table->json('authors');
            $table->string('publisher')->nullable();
            $table->unsignedSmallInteger('published_year')->nullable();
            $table->unsignedInteger('page_count')->nullable();
            $table->string('cover_url', 500)->nullable();
            $table->text('description')->nullable();
            $table->string('source', 32);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('catalog_books');
    }
};
