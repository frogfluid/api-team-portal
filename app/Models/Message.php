<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Message extends Model
{
    protected $fillable = [
        'channel_id',
        'user_id',
        'content',
        'revoked_at',
        'revoked_by',
        'reply_to_id',
        'mentioned_user_ids',
        // TeamChat Medium tier (Wave 4)
        'pinned_at',
        'pinned_by_user_id',
        'link_metadata',
    ];

    protected $casts = [
        'revoked_at' => 'datetime',
        'mentioned_user_ids' => 'array',
        // TeamChat Medium tier (Wave 4)
        'pinned_at' => 'datetime',
        'link_metadata' => 'array',
    ];

    public function replyTo(): BelongsTo
    {
        return $this->belongsTo(Message::class, 'reply_to_id');
    }

    public function replies(): HasMany
    {
        return $this->hasMany(Message::class, 'reply_to_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function channel(): BelongsTo
    {
        return $this->belongsTo(Channel::class);
    }

    public function revoker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'revoked_by');
    }

    public function pinnedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'pinned_by_user_id');
    }

    public function attachments(): MorphMany
    {
        return $this->morphMany(Attachment::class, 'attachable');
    }

    public function stars(): HasMany
    {
        return $this->hasMany(MessageStar::class);
    }

    public function reactions(): HasMany
    {
        return $this->hasMany(MessageReaction::class);
    }
}
