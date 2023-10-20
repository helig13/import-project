<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    protected $fillable = [
        'category_id', 'model_code',  'manufacturer_id', 'product_name',
        'description', 'price', 'warranty', 'availability'
    ];

    public function category()
    {
        return $this->belongsTo(ProductCategory::class, 'category_id');
    }
    public function manufacturer()
    {
        return $this->belongsTo(Manufacturer::class);
    }
}
