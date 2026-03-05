<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OkrCheckIn extends Model
{
    public $timestamps = false;

    protected $fillable = ['key_result_id', 'user_id', 'previous_value', 'new_value', 'note'];

    protected $casts = [
        'previous_value' => 'decimal:2',
        'new_value' => 'decimal:2',
        'created_at' => 'datetime',
    ];

    public function keyResult(): BelongsTo
    {
        return $this->belongsTo(KeyResult::class);
    }
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
