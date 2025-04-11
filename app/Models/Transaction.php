<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Transaction extends Model
{
    use HasFactory;

    protected $fillable = [
        'type',
        'third_party',
        'machine_id',
        'transaction_time',
        'amount',
        'actions',
        'description',
    ];

    public function machine()
    {
        return $this->belongsTo(Machine::class);
    }
}
