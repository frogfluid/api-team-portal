<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Announcement extends Model
{
    protected $fillable = ['title', 'body', 'type', 'pinned', 'published_at', 'expires_at', 'author_id'];

    protected $casts = ['pinned' => 'boolean', 'published_at' => 'datetime', 'expires_at' => 'datetime'];

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    public function scopeActive($query)
    {
        return $query->whereNotNull('published_at')->where('published_at', '<=', now())
            ->where(fn($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()));
    }

    public function scopePinned($query)
    {
        return $query->where('pinned', true);
    }
}
