<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LeaveQuota extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'year',
        'annual_total',
        'annual_used',
        'sick_total',
        'sick_used',
    ];

    protected $casts = [
        'annual_total' => 'decimal:2',
        'annual_used' => 'decimal:2',
        'sick_total' => 'decimal:2',
        'sick_used' => 'decimal:2',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
