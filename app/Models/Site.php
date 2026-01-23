<?php

declare(strict_types=1);

namespace App\Models;

use App\Utility\Enums\SiteTypeEnum;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Site extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'name',
        'code',
        'location',
        'start_date',
        'end_date',
        'expected_delivery_date',
        'type',
        'work_type',
        'site_manager_id',
        'address',
        'city',
        'state',
        'zip_code',
        'country',
        'contact_person',
        'contact_email',
        'contact_phone',
        'status',
        'description',
    ];

    protected $casts = [
        'status' => 'boolean',
        'start_date' => 'date',
        'end_date' => 'date',
        'expected_delivery_date' => 'date',
        'type' => SiteTypeEnum::class,
    ];

    /**
     * Get site orders
     */
    public function orders()
    {
        return $this->hasMany(Order::class);
    }

    /**
     * Get site stock
     */
    public function stock()
    {
        return $this->hasMany(Stock::class);
    }

    /**
     * Get site manager
     */
    public function siteManager()
    {
        return $this->belongsTo(Moderator::class, 'site_manager_id');
    }

    /**
     * Get site materials (many-to-many)
     * Materials are now products with is_product = 0
     */
    public function materials()
    {
        return $this->belongsToMany(Product::class, 'site_materials')
            ->where('is_product', 0);
    }

    public static function countAssignedSitesForSiteManager($siteManagerId)
    {
        return self::where('site_manager_id', $siteManagerId)->count();
    }
}
