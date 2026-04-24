<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Carbon\Carbon;

class RemoteWorkRequest extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id','region','start_date','end_date',
        'reason','deliverables','work_environment',
        'status','approved_by','approved_at','rejection_reason',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date'   => 'date',
        'approved_at'=> 'datetime',
    ];

    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function approver(): BelongsTo { return $this->belongsTo(User::class, 'approved_by'); }

    public function scopeForUser($q, int $userId) { return $q->where('user_id', $userId); }
    public function scopeApproved($q) { return $q->where('status','approved'); }
    public function scopeCoversDate($q, Carbon $date) {
        $iso = $date->toDateString();
        return $q->whereDate('start_date', '<=', $iso)->whereDate('end_date', '>=', $iso);
    }

    public function isDomestic(): bool { return $this->region === 'domestic'; }
    public function isOverseas(): bool { return $this->region === 'overseas'; }
    public function isPending(): bool { return $this->status === 'pending'; }

    public function getRegionLabelAttribute(): string
    {
        return $this->region === 'overseas' ? __('Remote - Overseas') : __('Remote - Domestic');
    }

    public function getStatusLabelAttribute(): string
    {
        return match($this->status) {
            'approved' => __('Approved'),
            'rejected' => __('Rejected'),
            default    => __('Pending'),
        };
    }
}
