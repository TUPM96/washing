<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MachineHistory extends Model
{
    use HasFactory;

    protected $fillable = [
        'machine_id',
        'status',
        'changed_at',
    ];

    public function machine()
    {
        return $this->belongsTo(Machine::class);
    }
}
