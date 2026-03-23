<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;

class RequestTypeWmsWarehouse extends Model
{
    protected $table = 'request_type_wms_warehouses';

    protected $fillable = [
        'request_type_id',
        'wms_warehouse_id',
    ];

    /**
     * Get the request type that owns the mapping.
     */
    public function requestType()
    {
        // Поскольку в проекте используется в основном фасад DB, 
        // оставим базовую связь, хотя ее можно не использовать.
        return DB::table('request_types')->where('id', $this->request_type_id)->first();
    }
}
