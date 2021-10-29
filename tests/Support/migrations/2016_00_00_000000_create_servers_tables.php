<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateServersTables extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('groups', function (Blueprint $table) {
            $table->increments('id');
            $table->string('title');
            $table->string('slug')->index();
            $table->integer('position');
            $table->unsignedBigInteger('parent_id')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('parent_id')->references('id')->on('groups')
                ->cascadeOnDelete()->cascadeOnUpdate();
        });

        Schema::create('environments', function (Blueprint $table) {
            $table->increments('id');
            $table->string('title');
            $table->string('slug')->index();
            $table->text('group_id')->nullable();
            $table->text('subgroup_id')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('group_id')->references('id')->on('groups')
                ->cascadeOnDelete()->cascadeOnUpdate();
            $table->foreign('subgroup_id')->references('id')->on('groups')
                ->cascadeOnDelete()->cascadeOnUpdate();
        });

        Schema::create('servers', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedBigInteger('environment_id')->index();
            $table->string('title');
            $table->mediumText('description');
            $table->string('manufacturer');
            $table->string('notes');
            $table->boolean('finish')->default(false);
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('environment_id')->references('id')->on('environments')
                ->cascadeOnDelete()->cascadeOnUpdate();
        });


        Schema::create('clusters', function (Blueprint $table) {
            $table->increments('id');
            $table->string('title');
            $table->string('slug')->index();
            $table->unsignedBigInteger('parent_id')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('parent_id')->references('id')->on('clusters')
                ->cascadeOnDelete()->cascadeOnUpdate();
        });

        Schema::create('cluster_server', function (Blueprint $table) {
            $table->unsignedBigInteger('cluster_id');
            $table->unsignedBigInteger('server_id');
            $table->boolean('default')->default(false);
            $table->timestamps();

            $table->foreign('cluster_id')->references('id')->on('clusters')
                ->cascadeOnDelete()->cascadeOnUpdate();
            $table->foreign('server_id')->references('id')->on('servers')
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
        Schema::dropIfExists('servers');
        Schema::dropIfExists('environments');
        Schema::dropIfExists('groups');
        Schema::dropIfExists('clusters');
        Schema::dropIfExists('cluster_server');
    }
}
