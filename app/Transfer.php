<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Transfer extends Model
{
    use SoftDeletes;

    protected $dates = ['tra_deleted_at'];
    const DELETED_AT = 'tra_deleted_at';
    const CREATED_AT = 'tra_created_at';
    const UPDATED_AT = 'tra_updated_at';

    protected $table = 'transfers';

    protected $primaryKey = 'tra_number';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'tra_account_emitter', 'tra_account_receiver', 'tra_bank',
        'tra_description', 'tra_amount', 'tra_type', 
        'tra_status', 'tra_client_ip'
    ];
}
