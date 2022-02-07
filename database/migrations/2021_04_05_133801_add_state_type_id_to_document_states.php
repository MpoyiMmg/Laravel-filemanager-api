<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddStateTypeIdToDocumentStates extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('document_states', function (Blueprint $table) {
            $table->unsignedBigInteger('state_type_id')->nullable();
            $table->foreign('state_type_id')->references('id')->on('state_types');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('document_states', function (Blueprint $table) {
            $table->dropForeign(['state_type_id']);
            $table->dropColumn(['state_type_id']);
        });
    }
}
