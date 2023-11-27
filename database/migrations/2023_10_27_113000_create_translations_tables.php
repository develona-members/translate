<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateTranslationsTables extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $c = config('translate.texts_db', 'mysql');
        Schema::connection($c)->create('translations_source', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->text('content')->nullable();
            $table->boolean('active')->default(true);
            $table->text('path')->nullable();
            $table->datetime('parsed_at')->nullable();
            $table->timestamps();
        });

        Schema::connection($c)->create('translations_langs', function (Blueprint $table) {
            $table->id();
            $table->char('lang', 2)->default('en');
            $table->foreignId('source_id');
            $table->text('translated')->nullable();
            $table->datetime('revised_at')->nullable();
            $table->timestamps();
            $table->unique(['source_id', 'lang']);
            $table->foreign('source_id')->references('id')->on('translations_source')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        $c = config('translate.texts_db', 'mysql');
        Schema::connection($c)->dropIfExists('translations_langs');
        Schema::connection($c)->dropIfExists('translations_source');
    }
}
