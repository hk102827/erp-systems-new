<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProductVariant extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'product_id',
        'variant_name',
        'variant_value',
        'sku',
        'barcode',
        'cost_price',
        'selling_price',
        'additional_price',
        'is_active',
    ];

    protected $casts = [
        'cost_price' => 'decimal:3',
        'selling_price' => 'decimal:3',
        'additional_price' => 'decimal:3',
        'is_active' => 'boolean',
    ];

    // Relationships
    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function inventory()
    {
        return $this->hasMany(Inventory::class, 'variant_id');
    }
}