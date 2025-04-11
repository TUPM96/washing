<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LocationAdmin extends Model
{
    use HasFactory;

    protected $table = 'location_admin';

    protected $fillable = [
        'location_id',
        'telegram_user_id',
    ];

    public function location()
    {
        return $this->belongsTo(Location::class, 'location_id', 'id');
    }

    public function telegramUser()
    {
        return $this->belongsTo(TelegramUser::class, 'telegram_user_id', 'id');
    }
}
