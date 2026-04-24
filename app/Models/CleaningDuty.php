<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CleaningDuty extends Model
{
    protected $fillable = [
        'date',
        'assigned_user_ids',
        'assigned_by',
    ];

    protected $casts = [
        'date' => 'date',
        'assigned_user_ids' => 'array',
    ];

    // ── Relationships ──────────────────────────────────

    public function assigner()
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }

    // ── Helpers ─────────────────────────────────────────

    /**
     * Get the User models for all assigned users.
     */
    public function assignedUsers()
    {
        return User::whereIn('id', $this->assigned_user_ids ?? [])->get();
    }

    /**
     * Check if a specific user is assigned.
     */
    public function isUserAssigned(int $userId): bool
    {
        return in_array($userId, $this->assigned_user_ids ?? []);
    }

    // ── Scopes ──────────────────────────────────────────

    public function scopeForDate($query, $date)
    {
        return $query->where('date', $date);
    }
}
