<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Bank extends Model
{
    use SoftDeletes;

    protected $dates = ['bnk_deleted_at'];
    const DELETED_AT = 'bnk_deleted_at';
    const CREATED_AT = 'bnk_created_at';
    const UPDATED_AT = 'bnk_updated_at';

    protected $table = 'banks';

    protected $primaryKey = 'bnk_id';

    protected $fillable = [
        'bnk_name', 'bnk_url', 'bnk_key'
    ];
}
