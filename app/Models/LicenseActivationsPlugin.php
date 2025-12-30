<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LicenseActivationsPlugin extends Model
{
    use HasFactory;

    protected $table = 'license_activations_plugin';

    protected $fillable = [
        'license_key',
        'device_id',
        'product_name',
        'activated_at',
        'last_seen_at',
        'revoked',
    ];

    protected $casts = [
        'activated_at' => 'datetime',
        'last_seen_at' => 'datetime',
        'revoked' => 'boolean',
    ];

    public $timestamps = false; // Because we are managing activated_at/last_seen_at manually or via default

    public function license()
    {
        return $this->belongsTo(CustomerLicense::class, 'license_key', 'license_key');
    }
}
