<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Employee extends Model
{
    protected $fillable = [
        'user_id',
        'legal_name',
        'employee_no',
        'employment_type',
        'department_id',
        'date_of_birth',
        'phone',
        'address',
        'nationality',
        'joined_on',
        'left_on',
        'status',
        'note',
    ];

    protected $casts = [
        'date_of_birth' => 'date',
        'joined_on' => 'date',
        'left_on' => 'date',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
