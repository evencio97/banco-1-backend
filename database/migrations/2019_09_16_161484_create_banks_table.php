<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateBanksTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('banks', function (Blueprint $table) {
            $table->unsignedBigInteger('bnk_id');
            $table->string('bnk_name');
            $table->string('bnk_url');
            $table->string('bnk_key')->unique();
            $table->timestamp('pur_created_at')->default(\DB::raw('CURRENT_TIMESTAMP'));
            $table->timestamp('pur_updated_at')->default(\DB::raw('CURRENT_TIMESTAMP'));
            $table->timestamp('pur_deleted_at')->nullable();
            $table->primary('bnk_id');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('banks');
    }
}
