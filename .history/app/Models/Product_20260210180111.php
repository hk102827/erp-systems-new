<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'product_name',
        'sku',
        'barcode',
        'category_id',
        'description',
        'unit',
        'weight',
        'dimensions',
        'color',
        'cost_price',
        'selling_price',
        'profit_margin',
        'has_variants',
        'low_stock_alert',
        'is_active',
        'created_by',
    ];

    protected $casts = [
        'cost_price' => 'decimal:3',
        'selling_price' => 'decimal:3',
        'profit_margin' => 'decimal:2',
        'weight' => 'decimal:2',
        'has_variants' => 'boolean',
        'is_active' => 'boolean',
    ];

    // Auto-calculate profit margin before saving
    protected static function boot()
    {
        parent::boot();

        static::saving(function ($product) {
            if ($product->selling_price > 0 && $product->cost_price > 0) {
                $product->profit_margin = (($product->selling_price - $product->cost_price) / $product->selling_price) * 100;
            }
        });
    }

    // Relationships
    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function images()
    {
        return $this->hasMany(ProductImage::class);
    }

    public function primaryImage()
    {
        return $this->hasOne(ProductImage::class)->where('is_primary', true);
    }

    public function variants()
    {
        return $this->hasMany(ProductVariant::class);
    }

 public function inventory()
{
    return $this->hasMany(Inventory::class, 'product_id');
}


    public function movements()
    {
        return $this->hasMany(InventoryMovement::class);
    }

    public function discounts()
    {
        return $this->hasMany(ProductDiscount::class);
    }

    public function activeDiscounts()
    {
        return $this->hasMany(ProductDiscount::class)
            ->where('is_active', true)
            ->whereDate('start_date', '<=', now())
            ->whereDate('end_date', '>=', now());
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // Helper: Get total stock across all branches
    public function getTotalStockAttribute()
    {
        return $this->inventory()->sum('quantity');
    }

    // Helper: Get available stock across all branches
    public function getAvailableStockAttribute()
    {
        return $this->inventory()->sum('available_quantity');
    }

    
}