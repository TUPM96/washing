<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Location extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'telegram_bot_token',
        'telegram_chat_id',
        'slack_success_webhook',
        'slack_error_webhook',
        'latitude',
        'longitude',
    ];

    public function machines()
    {
        return $this->hasMany(Machine::class);
    }

    public function locationAdmins()
    {
        return $this->belongsToMany(TelegramUser::class, 'location_admin', 'location_id', 'telegram_user_id');
    }
}
