<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class CreditCardPayment extends Model
{
    use SoftDeletes;

    protected $dates = ['deleted_at'];
    const DELETED_AT = 'deleted_at';
    const CREATED_AT = 'created_at';
    const UPDATED_AT = 'updated_at';

    protected $table = 'credit_card_payments';

    protected $primaryKey = 'ccp_id';

    protected $fillable = [
        'ccp_creditcard', 'ccp_account', 'ccp_description',
        'ccp_amount', 'ccp_status', 'ccp_client_ip'
    ];

    protected $casts = [
        'ccp_creditcard'  =>  'string',
        'ccp_account'  =>  'string'
    ];
}
