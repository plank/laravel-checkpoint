<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateCmsTables extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('pages', function (Blueprint $table) {
            $table->increments('id');
            $table->string('template');
            $table->string('title');
            $table->string('slug')->index();
            $table->integer('position');
            $table->unsignedBigInteger('parent_id')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('parent_id')->references('id')->on('pages')
                ->cascadeOnDelete()->cascadeOnUpdate();
        });

        Schema::create('blocks', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedBigInteger('page_id')->index();
            $table->nullableMorphs('calloutable');
            $table->string('template');
            $table->string('title');
            $table->json('content');
            $table->integer('span');
            $table->integer('position');
            $table->string('status')->default('draft');
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('page_id')->references('id')->on('pages')
                ->cascadeOnDelete()->cascadeOnUpdate();
        });

    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('pages');
        Schema::dropIfExists('blocks');
    }
}
