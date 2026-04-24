<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MonthlyMessageComment extends Model
{
    protected $fillable = [
        'monthly_message_id',
        'author_id',
        'body',
    ];

    public function message(): BelongsTo
    {
        return $this->belongsTo(MonthlyMessage::class, 'monthly_message_id');
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }
}
