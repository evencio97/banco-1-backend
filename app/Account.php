<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Account extends Model
{
    use SoftDeletes;

    protected $dates = ['aco_deleted_at'];
    const DELETED_AT = 'aco_deleted_at';
    const CREATED_AT = 'aco_created_at';
    const UPDATED_AT = 'aco_updated_at';

    protected $table = 'accounts';

    protected $primaryKey = 'aco_number';

    protected $fillable = [
        'aco_number', 'aco_user', 'aco_user_table', 'aco_balance',
        'aco_balance_lock', 'aco_type', 'aco_status'
    ];

    protected $casts = [
        'aco_number'  =>  'string'
    ];

    public function transfersMake(){
        return $this->hasMany('App\Transfer', 'tra_account_emitter', 'aco_number')->orderBy('tra_created_at');
    }

    public function transfersReceive(){
        return $this->hasMany('App\Transfer', 'tra_account_receiver', 'aco_number')->orderBy('tra_created_at');
    }
}
