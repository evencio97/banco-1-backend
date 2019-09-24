<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Purchases extends Model
{
    use SoftDeletes;

    protected $dates = ['pur_deleted_at'];
    const DELETED_AT = 'pur_deleted_at';
    const CREATED_AT = 'pur_created_at';
    const UPDATED_AT = 'pur_updated_at';

    protected $table = 'purchases';

    protected $primaryKey = 'pur_id';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'pur_creditcard','pur_bank', 'pur_description', 
        'pur_business', 'pur_amount', 'pur_status', 
        'pur_client_ip',
    ];

    protected $casts = [
        'pur_creditcard'  =>  'string'
    ];
}
