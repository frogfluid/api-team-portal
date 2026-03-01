<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TaskOwnerHistory extends Model
{
    protected $table = 'task_owner_histories';

    protected $fillable = [
        'task_id',
        'from_owner_id',
        'to_owner_id',
        'changed_by',
        'note',
        'changed_at',
    ];

    protected $casts = [
        'changed_at' => 'datetime',
    ];

    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    public function fromOwner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'from_owner_id');
    }

    public function toOwner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'to_owner_id');
    }

    public function changer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}
