<?php

namespace App;

use Laravel\Passport\HasApiTokens;
use Illuminate\Notifications\Notifiable;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;

class JuristicUser extends Authenticatable
{
    use SoftDeletes;

    protected $dates = ['jusr_deleted_at'];
    const DELETED_AT = 'jusr_deleted_at';
    const CREATED_AT = 'jusr_created_at';
    const UPDATED_AT = 'jusr_updated_at';

    protected $table = 'juristic_users';

    protected $primaryKey = 'id';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'jusr_rif','jusr_user', 'jusr_company',
        'jusr_address', 'jusr_phone',
        'password', 'activation_token'
    ];

    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array
     */
    protected $hidden = [
        'password', 'remember_token', 'activation_token',
    ];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'jusr_rif' => 'string'
    ];
}
