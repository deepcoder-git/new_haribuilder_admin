<?php

declare(strict_types=1);

namespace App\Models;

use App\Utility\Traits\HasSlug;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Supplier extends Model
{
    use HasFactory;
    use SoftDeletes;
    use HasSlug;

    protected $fillable = [
        'name',
        'slug',
        'supplier_type',
        'email',
        'phone',
        'address',
        'description',
        'tin_number',
        'status',
    ];

    protected $casts = [
        'status' => 'boolean',
    ];
}



