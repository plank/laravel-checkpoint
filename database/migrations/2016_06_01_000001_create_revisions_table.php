<?php


use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateRevisionsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('revisions',
            function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->morphs('revisionable');
                $table->unsignedBigInteger('original_revisionable_id');
                $table->boolean('latest')->default(true);
                $table->json('metadata')->nullable();
                $table->unsignedBigInteger('previous_revision_id')->nullable();
                $table->unsignedBigInteger('checkpoint_id')->nullable();
                $table->timestamps();

                $table->index(['revisionable_type', 'original_revisionable_id']);
                $table->index(['original_revisionable_id', 'revisionable_type', 'id']);
                $table->index(['created_at']);
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
        Schema::dropIfExists('revisions');
    }
}
