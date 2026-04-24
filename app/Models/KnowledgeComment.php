<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class KnowledgeComment extends Model
{
    protected $fillable = [
        'knowledge_document_id',
        'user_id',
        'content',
    ];

    public function document()
    {
        return $this->belongsTo(KnowledgeDocument::class, 'knowledge_document_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
