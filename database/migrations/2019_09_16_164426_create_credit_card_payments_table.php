<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateCreditCardPaymentsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('credit_card_payments', function (Blueprint $table) {
            $table->bigIncrements('ccp_id');
            $table->unsignedBigInteger('ccp_creditcard');
            $table->unsignedBigInteger('ccp_account');
            $table->string('ccp_description', 300)->nullable();
            $table->float('ccp_amount', 10, 2);
            $table->integer('ccp_status')->default(0)->comment('0 procesando / 1 aprobado / 2 fallida');
            $table->string('ccp_client_ip');
            $table->timestamp('ccp_created_at')->default(\DB::raw('CURRENT_TIMESTAMP'));
            $table->timestamp('ccp_updated_at')->default(\DB::raw('CURRENT_TIMESTAMP'));
            $table->timestamp('ccp_deleted_at')->nullable();
            $table->foreign('ccp_creditcard')->references('cc_number')->on('credit_cards');
            $table->foreign('ccp_account')->references('aco_number')->on('accounts');
            $table->index('ccp_client_ip');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('credit_card_payments');
    }
}
