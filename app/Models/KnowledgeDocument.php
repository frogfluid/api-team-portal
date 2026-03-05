<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KnowledgeDocument extends Model
{
    protected $fillable = [
        'title',
        'description',
        'category',
        'file_path',
        'file_name',
        'file_size',
        'file_type',
        'uploaded_by',
    ];

    protected $casts = ['file_size' => 'integer'];

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function getFileSizeFormattedAttribute(): string
    {
        $bytes = $this->file_size;
        if ($bytes >= 1048576)
            return round($bytes / 1048576, 1) . ' MB';
        if ($bytes >= 1024)
            return round($bytes / 1024, 1) . ' KB';
        return $bytes . ' B';
    }

    public static function categories(): array
    {
        return [
            'policies' => 'Policies & Guidelines',
            'procedures' => 'Procedures',
            'templates' => 'Templates',
            'training' => 'Training Materials',
            'general' => 'General',
        ];
    }
}
