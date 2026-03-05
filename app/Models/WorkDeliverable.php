<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkDeliverable extends Model
{
    protected $fillable = ['user_id', 'title', 'url', 'description', 'type', 'task_id', 'project_id'];

    public const TYPES = ['design', 'code', 'document', 'video', 'presentation', 'other'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function getTypeMetaAttribute(): array
    {
        $meta = [
            'design' => ['label' => 'Design', 'color' => '#8B5CF6'],
            'code' => ['label' => 'Code', 'color' => '#3B82F6'],
            'document' => ['label' => 'Document', 'color' => '#F59E0B'],
            'video' => ['label' => 'Video', 'color' => '#F43F5E'],
            'presentation' => ['label' => 'Presentation', 'color' => '#14B8A6'],
            'other' => ['label' => 'Other', 'color' => '#6B7280'],
        ];
        return $meta[$this->type] ?? $meta['other'];
    }
}
