<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateAccountsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('accounts', function (Blueprint $table) {
            $table->string('aco_number', 20);
            $table->unsignedBigInteger('aco_user');
            $table->string('aco_user_table');
            $table->float('aco_balance', 10, 2);
            $table->float('aco_balance_lock', 10, 2)->default(0)->comment('saldo bloqueado');
            $table->string('aco_type');
            $table->integer('aco_status')->default(1)->comment('0 bloqueada / 1 activa');
            $table->timestamp('aco_created_at')->default(\DB::raw('CURRENT_TIMESTAMP'));
            $table->timestamp('aco_updated_at')->default(\DB::raw('CURRENT_TIMESTAMP'));
            $table->timestamp('aco_deleted_at')->nullable();
            $table->primary('aco_number');            
            $table->index('aco_user');
            $table->index('aco_type');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('accounts');
    }
}
