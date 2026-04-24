<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Emoji reaction on a chat message.
 *
 * Table schema (see
 * database/migrations/2026_03_03_140000_create_message_reactions_table.php
 * in the web app):
 *   - message_id, user_id, emoji (string 8), timestamps
 *   - unique (message_id, user_id, emoji)
 */
class MessageReaction extends Model
{
    protected $fillable = ['message_id', 'user_id', 'emoji'];

    public function message(): BelongsTo
    {
        return $this->belongsTo(Message::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
