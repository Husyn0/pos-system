<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DailySalesSummary extends Model
{
    public $timestamps = false;
    protected $fillable = [
        'tenant_id', 'branch_id', 'summary_date', 'gross_sales', 'net_sales',
        'discount_total', 'tax_total', 'orders_count', 'avg_order_value', 'top_item_id',
    ];
    protected $casts = ['summary_date' => 'date'];
}
