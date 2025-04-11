<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TelegramUser extends Model
{
    use HasFactory;

    protected $table = 'telegram_users';

    protected $fillable = [
        'telegram_id',
        'first_name',
        'last_name',
        'username',
        'is_admin',
    ];

    public function locations()
    {
        return $this->belongsToMany(Location::class, 'location_admin', 'telegram_user_id', 'location_id');
    }
}
