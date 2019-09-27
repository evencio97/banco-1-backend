<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class AuthClient extends Model
{
    protected $dates = ['deleted_at'];

    protected $table = 'auth_clients';

    protected $primaryKey = 'id';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'user_id',
        'client_url',
        'secret',
        'ip',
        'revoked'
    ];

    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array
     */
    protected $hidden = [
        'secret'
    ];
}
