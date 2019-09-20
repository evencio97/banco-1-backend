<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class PasswordReset extends Model
{
    const CREATED_AT = 'created_at';
    const UPDATED_AT = 'updated_at';
    const DELETED_AT = 'deleted_at';
    protected $fillable = [
        'token', 'email'
    ];

    protected $table = 'password_resets';
	 
	protected $primaryKey = 'id';
}
