<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Bill extends Model
{
    use SoftDeletes;

    protected $dates = ['bil_deleted_at'];
    const DELETED_AT = 'bil_deleted_at';
    const CREATED_AT = 'bil_created_at';
    const UPDATED_AT = 'bil_updated_at';

    protected $table = 'bills';

    protected $primaryKey = 'bil_id';

    protected $fillable = [
        'bil_emitter', 'bil_receiver', 'bil_transfer',
        'bil_ref_code', 'bil_description', 'bil_amount',
        'bil_paydate', 'bil_expdate', 'bil_status'
    ];

    protected $casts = [
        'bil_emitter'  =>  'string',
        'bil_receiver'  =>  'string'
    ];
}
