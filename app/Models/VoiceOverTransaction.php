<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class VoiceOverTransaction extends Model
{
    use HasFactory;

    protected $fillable = ['license_id','type','seconds'];

    public function license()
    {
        return $this->belongsTo(CustomerLicense::class, 'license_id');
    }
}