<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Subrubric extends Model
{
    protected $fillable = ['name', 'rubric_id'];

    public function rubric()
    {
        return $this->belongsTo(Rubric::class);
    }

    public function productCategories()
    {
        return $this->hasMany(ProductCategory::class);
    }
}
