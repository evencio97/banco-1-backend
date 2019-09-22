<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreatePurchasesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('purchases', function (Blueprint $table) {
            $table->bigIncrements('pur_id');
            $table->string('pur_creditcard', 20);
            $table->unsignedBigInteger('pur_bank')->nullable();
            $table->string('pur_description', 300)->nullable();
            $table->string('pur_business');
            $table->float('pur_amount', 10, 2);
            $table->integer('pur_status')->default(0)->comment('0 pendiente / 1 procesada / 2 fallida');
            $table->string('pur_client_ip');
            $table->timestamp('pur_created_at')->default(\DB::raw('CURRENT_TIMESTAMP'));
            $table->timestamp('pur_updated_at')->default(\DB::raw('CURRENT_TIMESTAMP'));
            $table->timestamp('pur_deleted_at')->nullable();
            $table->foreign('pur_creditcard')->references('cc_number')->on('credit_cards');
            $table->foreign('pur_bank')->references('bnk_id')->on('banks');
            $table->index('pur_client_ip');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('purchases');
    }
}
