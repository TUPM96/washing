<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::table('telegram_users', function (Blueprint $table) {
            $table->string('first_name')->nullable()->change();
            $table->string('last_name')->nullable();
        });
    }

    public function down()
    {
        Schema::table('telegram_users', function (Blueprint $table) {
            $table->string('first_name')->nullable(false)->change();
            $table->dropColumn('last_name');
        });
    }
};
