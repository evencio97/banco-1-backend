<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateAuditTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('audit', function (Blueprint $table) {
            $table->bigIncrements('adt_id');
            $table->unsignedBigInteger('adt_user');
            $table->unsignedBigInteger('adt_target');
            $table->string('adt_user_table');
            $table->string('adt_target_table');
            $table->string('adt_type')->comment('tipo operacion');
            $table->string('adt_client_ip');
            $table->timestamp('adt_created_at')->default(\DB::raw('CURRENT_TIMESTAMP'));
            $table->timestamp('adt_updated_at')->default(\DB::raw('CURRENT_TIMESTAMP'));
            $table->timestamp('adt_deleted_at')->nullable();
            $table->index('adt_user');
            $table->index('adt_type');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('audit');
    }
}
