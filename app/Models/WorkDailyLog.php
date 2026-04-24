<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class WorkDailyLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'work_date',
        'started_at',
        'ended_at',
        'break_minutes',
        'worked_minutes',
        'note',
        'status',
        'submitted_at',
        // T9 — detailed remote-work reporting + 4-action review
        'deliverables',
        'time_blocks',
        'communication_log',
        'review_status',
        'reviewed_by',
        'reviewed_at',
        'review_note',
        'is_revised',
    ];

    protected $casts = [
        'work_date' => 'date',
        'started_at' => 'datetime',
        'ended_at' => 'datetime',
        'submitted_at' => 'datetime',
        'time_blocks' => 'array',
        'reviewed_at' => 'datetime',
        'is_revised' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function attachments(): MorphMany
    {
        return $this->morphMany(Attachment::class, 'attachable');
    }

    // ─── Scopes ──────────────────────────────────────────────────────────

    public function scopeForMonth(Builder $q, int $year, int $month): Builder
    {
        $start = Carbon::create($year, $month, 1)->startOfMonth()->toDateString();
        $end   = Carbon::create($year, $month, 1)->endOfMonth()->toDateString();

        return $q->whereBetween('work_date', [$start, $end]);
    }

    public function scopeForUser(Builder $q, int $userId): Builder
    {
        return $q->where('user_id', $userId);
    }

    /**
     * Limit to logs that fall on an approved remote-work day for the same user.
     */
    public function scopeRemote(Builder $q): Builder
    {
        return $q->whereExists(function ($sub) {
            $sub->from('remote_work_requests as rwr')
                ->select(DB::raw(1))
                ->whereColumn('rwr.user_id', 'work_daily_logs.user_id')
                ->where('rwr.status', 'approved')
                ->whereColumn('rwr.start_date', '<=', 'work_daily_logs.work_date')
                ->whereColumn('rwr.end_date', '>=', 'work_daily_logs.work_date');
        });
    }

    // ─── Derived state ───────────────────────────────────────────────────

    /**
     * Whether the log's work_date is covered by an approved RemoteWorkRequest.
     * Mirrors AttendanceRecord::isRemoteDay.
     */
    public function isRemote(): bool
    {
        if (!$this->user_id || !$this->work_date) {
            return false;
        }

        $date = $this->work_date instanceof Carbon
            ? $this->work_date
            : Carbon::parse($this->work_date);

        return RemoteWorkRequest::query()
            ->forUser((int) $this->user_id)
            ->approved()
            ->coversDate($date)
            ->exists();
    }

    /**
     * A log can be "returned" only once. After a revision is submitted and
     * reviewed again, the reviewer may no longer return it.
     */
    public function canBeReturned(): bool
    {
        return !$this->is_revised;
    }

    /**
     * Translated label for the review_status column.
     */
    public function getReviewStatusLabelAttribute(): string
    {
        return match ($this->review_status) {
            'approved' => __('Approved'),
            'flagged'  => __('Flagged as Suspicious'),
            'rejected' => __('Rejected - Hours Zeroed'),
            'returned' => __('Returned for Revision'),
            default    => __('Pending'),
        };
    }
}
