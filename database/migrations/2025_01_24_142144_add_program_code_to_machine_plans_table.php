<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('machine_plans', function (Blueprint $table) {
            $table->unsignedInteger('program_code')->after('minute')->default(1);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('machine_plans', function (Blueprint $table) {
            $table->dropColumn('program_code');
        });
    }
};
