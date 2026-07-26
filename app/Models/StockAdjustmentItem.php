<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUlids;

class StockAdjustmentItem extends Model
{
    use HasUlids;
    protected $guarded = ['id'];

    public function adjustment() { return $this->belongsTo(StockAdjustment::class, 'stock_adjustment_id'); }
    public function product() { return $this->belongsTo(Product::class); }
}