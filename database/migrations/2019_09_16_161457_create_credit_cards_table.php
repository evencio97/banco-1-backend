<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateCreditCardsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('credit_cards', function (Blueprint $table) {
            $table->unsignedBigInteger('cc_number');
            $table->unsignedBigInteger('cc_user');
            $table->date('cc_exp_date');
            $table->integer('cc_cvv');
            $table->float('cc_balance', 10, 2)->default(0);
            $table->float('cc_limit', 10, 2);
            $table->integer('cc_interests')->comment('porcentaje');
            $table->float('cc_minimum_payment', 10, 2);
            $table->date('cc_paydate')->nullable();
            $table->integer('cc_status')->default(1)->comment('0 no activa / 1 activa / 2 bloqueada');
            $table->timestamp('cc_created_at')->default(\DB::raw('CURRENT_TIMESTAMP'));
            $table->timestamp('cc_updated_at')->default(\DB::raw('CURRENT_TIMESTAMP'));
            $table->timestamp('cc_deleted_at')->nullable();
            $table->primary('cc_number');
            $table->foreign('cc_user')->references('id')->on('users');
            $table->index('cc_cvv');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('credit_cards');
    }
}
