<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateTransfersTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('transfers', function (Blueprint $table) {
            $table->bigIncrements('tra_number');
            $table->string('tra_account_emitter', 20);
            $table->string('tra_account_receiver', 20);
            $table->unsignedBigInteger('tra_bank')->nullable();
            $table->string('tra_description')->nullable();
            $table->float('tra_amount', 10, 2);
            $table->integer('tra_type')->default(0)->comment('0 mismo banco / 1 otro banco');
            $table->integer('tra_status')->default(0)->comment('0 pendiente / 1 procesada / 2 fallida');
            $table->string('tra_client_ip');
            $table->timestamp('created_at')->default(\DB::raw('CURRENT_TIMESTAMP'));
            $table->timestamp('updated_at')->default(\DB::raw('CURRENT_TIMESTAMP'));
            $table->timestamp('deleted_at')->nullable();
            $table->foreign('tra_account_emitter')->references('aco_number')->on('accounts');
            $table->index('tra_account_receiver');
            $table->foreign('tra_bank')->references('bnk_id')->on('banks');
            $table->index('tra_client_ip');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('transfers');
    }
}
