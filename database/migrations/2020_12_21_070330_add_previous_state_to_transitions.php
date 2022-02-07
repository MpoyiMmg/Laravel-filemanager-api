<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddPreviousStateToTransitions extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('transitions', function (Blueprint $table) {
            $table->unsignedBigInteger('previousState');
            $table->foreign('previousState')->references('id')->on('states');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('transitions', function (Blueprint $table) {
            $table->dropForeign(['previousState']);
            $table->dropColumn(['previousState']);
        });
    }
}
