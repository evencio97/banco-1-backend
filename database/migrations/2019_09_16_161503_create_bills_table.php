<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateBillsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('bills', function (Blueprint $table) {
            $table->bigIncrements('bil_id');
            $table->string('bil_emitter');
            $table->string('bil_receiver');
            $table->unsignedBigInteger('bil_transfer')->nullable();
            $table->string('bil_ref_code');
            $table->string('bil_description')->nullable();
            $table->float('bil_amount', 10, 2);
            $table->date('bil_paydate')->nullable();
            $table->date('bil_expdate');
            $table->integer('bil_status')->default(0)->comment('0 pendiente / 1 pagada');
            $table->timestamp('bil_created_at')->default(\DB::raw('CURRENT_TIMESTAMP'));
            $table->timestamp('bil_updated_at')->default(\DB::raw('CURRENT_TIMESTAMP'));
            $table->timestamp('bil_deleted_at')->nullable();
            $table->foreign('bil_transfer')->references('tra_number')->on('transfers');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('bills');
    }
}
