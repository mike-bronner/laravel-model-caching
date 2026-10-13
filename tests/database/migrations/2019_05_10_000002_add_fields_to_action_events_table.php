<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddFieldsToActionEventsTable extends Migration
{
    public function up()
    {
        Schema::table('action_events', function (Blueprint $table) {
            $table->text('original')->nullable();
            $table->text('changes')->nullable();
        });
    }

    public function down()
    {
        Schema::table('action_events', function (Blueprint $table) {
            $table->dropColumn('original', 'changes');
        });
    }
}
