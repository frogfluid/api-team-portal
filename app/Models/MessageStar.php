<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Pivot-style model for the TeamChat per-user star feature.
 *
 * Table schema (see
 * database/migrations/2026_04_17_100100_create_message_stars_table.php
 * in the web app):
 *   - user_id, message_id, starred_at
 *
 * The table has no Laravel timestamps — only `starred_at` (defaulting to
 * CURRENT_TIMESTAMP), so `$timestamps` is disabled here.
 */
class MessageStar extends Model
{
    protected $fillable = ['user_id', 'message_id', 'starred_at'];

    protected $casts = [
        'starred_at' => 'datetime',
    ];

    public $timestamps = false;

    public function message(): BelongsTo
    {
        return $this->belongsTo(Message::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
