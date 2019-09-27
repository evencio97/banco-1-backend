<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class CreditCard extends Model
{
    use SoftDeletes;

    protected $dates = ['cc_deleted_at'];
    const DELETED_AT = 'cc_deleted_at';
    const CREATED_AT = 'cc_created_at';
    const UPDATED_AT = 'cc_updated_at';

    protected $table = 'credit_cards';

    protected $primaryKey = 'cc_number';

    protected $fillable = [
        'cc_user', 'cc_exp_date', 'cc_cvv',
        'cc_balance', 'cc_limit', 'cc_interests', 'cc_number',
        'cc_minimum_payment', 'cc_paydate', 'cc_status'
    ];

    protected $casts = [
        'cc_number'  =>  'string'
    ];

    protected $hidden = [
        'cc_cvv'
    ];
}
