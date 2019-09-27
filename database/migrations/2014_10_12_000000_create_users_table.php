<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateUsersTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('users', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('user_ci')->unique();
            $table->string('first_name');
            $table->string('middle_name');
            $table->string('first_surname');
            $table->string('second_surname');
            $table->string('email')->unique();
            $table->integer('type')->default(1);
            $table->string('q_recovery');
            $table->string('a_recovery');
            $table->string('password');
            $table->string('address', 300);
            $table->string('phone');
            $table->integer('active')->default(0);
            $table->string('activation_token');
            $table->rememberToken();
            $table->timestamp('created_at')->default(\DB::raw('CURRENT_TIMESTAMP'));
            $table->timestamp('updated_at')->default(\DB::raw('CURRENT_TIMESTAMP'));
            $table->timestamp('deleted_at')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('users');
    }
}
