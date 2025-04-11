<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MachinePlan extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'program_code',
        'price',
        'minute',
        'machine_id',
        'note',
    ];

    /**
     * Get the machine that owns the plan.
     */
    public function machine()
    {
        return $this->belongsTo(Machine::class);
    }
}
