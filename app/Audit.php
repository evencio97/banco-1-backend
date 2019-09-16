<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Audit extends Model
{
    use SoftDeletes;

    protected $dates = ['adt_deleted_at'];
    const DELETED_AT = 'adt_deleted_at';
    const CREATED_AT = 'adt_created_at';
    const UPDATED_AT = 'adt_updated_at';

    protected $table = 'audit';

    protected $primaryKey = 'adt_id';

    protected $fillable = [
        'adt_user', 'adt_target', 'adt_user_table',
        'adt_target_table', 'adt_type', 'adt_client_ip'
    ];

    public static function saveAudit($user_id, $user_table, $element_id, $element_table, $type, $ip){
		$audit=new Audit;
			$audit->adt_user=$user_id;
			$audit->adt_user_table=$user_table;
			$audit->adt_target=$element_id;
			$audit->adt_target_table=$element_table;
			$audit->adt_type=$type;
			$audit->adt_client_ip=$ip;
			$audit->save();
			return;
    }
}
