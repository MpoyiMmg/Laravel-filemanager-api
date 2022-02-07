<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddAdditionalFieldsToStates extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('states', function (Blueprint $table) {
            $table->unsignedBigInteger('metadata_id')->nullable();
            $table->foreign('metadata_id')->references('id')->on('metadata');
            $table->string('operator')->nullable();
            $table->string('metadataValue')->nullable();
            $table->unsignedBigInteger('transition_if_true')->nullable();
            $table->foreign('transition_if_true')->references('id')->on('transitions');
            $table->unsignedBigInteger('transition_if_false')->nullable();
            $table->foreign('transition_if_false')->references('id')->on('transitions');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('states', function (Blueprint $table) {
            //
        });
    }
}
