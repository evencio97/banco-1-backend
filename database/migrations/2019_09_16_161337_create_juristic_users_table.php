<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateJuristicUsersTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('juristic_users', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('jusr_rif')->unique();
            $table->unsignedBigInteger('jusr_user');
            $table->string('jusr_email');
            $table->string('jusr_company');
            $table->string('jusr_address', 300);
            $table->string('jusr_phone');
            $table->string('password');
            $table->string('q_recovery');
            $table->string('a_recovery');
            $table->string('activation_token');
            $table->integer('active');
            $table->rememberToken();
            $table->timestamp('jusr_created_at')->default(\DB::raw('CURRENT_TIMESTAMP'));
            $table->timestamp('jusr_updated_at')->default(\DB::raw('CURRENT_TIMESTAMP'));
            $table->timestamp('jusr_deleted_at')->nullable();
            $table->index('jusr_company');
            $table->foreign('jusr_user')->references('id')->on('users');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('juristic_users');
    }
}
