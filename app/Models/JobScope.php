<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class JobScope extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'has_external_output',
    ];

    protected $casts = [
        'has_external_output' => 'boolean',
    ];

    /**
     * Users that have this job scope.
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class);
    }
}
