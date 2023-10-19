<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Rubric extends Model
{
    protected $fillable = ['name'];

    public function subrubrics()
    {
        return $this->hasMany(Subrubric::class);
    }
}
