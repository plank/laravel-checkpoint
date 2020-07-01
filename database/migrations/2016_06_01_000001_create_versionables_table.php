<?php


use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateVersionablesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('versionables',
            function (Blueprint $table) {
                $table->id();
                $table->morphs('versionable');
                $table->unsignedBigInteger('version_id')->index();
                $table->unsignedBigInteger('previous_version_id')->nullable();
                $table->uuid('shared_key')->nullable();
                $table->json('meta')->nullable();
                $table->timestamps();

                $table->foreign('version_id')
                    ->references('id')
                    ->on('versions')
                    ->onUpdate('cascade')
                    ->onDelete('cascade');
            }
        );
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('versionables');
    }
}
