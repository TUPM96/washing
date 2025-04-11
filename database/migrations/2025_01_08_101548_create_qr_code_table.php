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
        Schema::create('qr_code', function (Blueprint $table) {
            $table->id();
            $table->string('content');
            $table->string('bankAccount');
            $table->string('bankCode');
            $table->string('userBankName');
            $table->longText('qr')->nullable();
            $table->string('terminalCode');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('qr_code');
    }
};
