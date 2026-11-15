<?php

use Estante\Catalog\Domain\MatchKey;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('catalog_books', function (Blueprint $table) {
            // Not unique: different works can share a title and a surname.
            $table->string('match_key')->nullable()->index()->after('isbn');
        });

        DB::table('catalog_books')->select(['id', 'title', 'authors'])->orderBy('id')->each(function (object $book) {
            DB::table('catalog_books')->where('id', $book->id)->update([
                'match_key' => MatchKey::of($book->title, json_decode($book->authors, true) ?? [])?->value,
            ]);
        });
    }

    public function down(): void
    {
        Schema::table('catalog_books', function (Blueprint $table) {
            $table->dropColumn('match_key');
        });
    }
};
