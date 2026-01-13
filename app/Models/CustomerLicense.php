<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class CustomerLicense extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'order_id','license_key','owner','email','phone','edition','payment_status','product_name',
        'tenor_days','is_activated','activation_date_utc','expires_at_utc','machine_id',
        'max_seats','max_video','features','vo_seconds_remaining','status','delivery_status','delivery_log',
        'version','last_used','device_id','max_seats_shopee_scrap','used_seats_shopee_scrap',
        'max_seat_upload_tiktok','used_seat_upload_tiktok'
    ];

    protected $casts = [
        'is_activated' => 'boolean',
        'activation_date_utc' => 'datetime',
        'expires_at_utc' => 'datetime',
        'last_used' => 'datetime',
    ];

    public function voiceOverTransactions()
    {
        return $this->hasMany(VoiceOverTransaction::class, 'license_id');
    }

    public function licenseActivations()
    {
        return $this->hasMany(LicenseActivationsPlugin::class, 'license_id');
    }
}