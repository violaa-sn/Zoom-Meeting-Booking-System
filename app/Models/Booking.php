<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'user_id',
    'zoom_account_id',
    'approved_by',
    'purpose',
    'purpose_other',
    'participant_count',
    'starts_on',
    'start_time',
    'end_time',
    'recurrence_frequency',
    'repeat_interval',
    'recurrence_end_type',
    'recurrence_ends_on',
    'recurrence_ends_after',
    'status',
    'rejection_reason',
    'approved_at',
    'cancelled_at',
])]
class Booking extends Model
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_REJECTED = 'rejected';
    public const STATUS_CANCELLED = 'cancelled';

    public const RECURRENCE_NONE = 'none';
    public const RECURRENCE_DAY = 'day';
    public const RECURRENCE_WEEK = 'week';
    public const RECURRENCE_MONTH = 'month';
    public const RECURRENCE_YEAR = 'year';

    public const END_ON = 'on';
    public const END_AFTER = 'after';

    public const PURPOSES = ['rapat', 'presentasi', 'pelatihan', 'wawancara', 'diskusi', 'lainnya'];

    protected function casts(): array
    {
        return [
            'participant_count' => 'integer',
            'starts_on' => 'date',
            'repeat_interval' => 'integer',
            'recurrence_ends_on' => 'date',
            'recurrence_ends_after' => 'integer',
            'approved_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function zoomAccount(): BelongsTo
    {
        // withTrashed: riwayat booking tetap bisa menampilkan akun yang sudah di-soft delete
        return $this->belongsTo(ZoomAccount::class)->withTrashed();
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /** Hari "Repeats on" (hanya untuk pengulangan mingguan). */
    public function days(): HasMany
    {
        return $this->hasMany(BookingDay::class);
    }

    public function occurrences(): HasMany
    {
        return $this->hasMany(BookingOccurrence::class);
    }

    public function notifications(): HasMany
    {
        return $this->hasMany(BookingNotification::class);
    }

    public function isRecurring(): bool
    {
        return $this->recurrence_frequency !== self::RECURRENCE_NONE;
    }

    /** end_time <= start_time berarti meeting melewati tengah malam. */
    public function crossesMidnight(): bool
    {
        return $this->end_time <= $this->start_time;
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    public function scopeApproved(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_APPROVED);
    }

    /**
     * Filter untuk menu Booking (daftar admin maupun "booking saya").
     * Semua key opsional; yang kosong diabaikan.
     *
     * Key yang didukung:
     *  - status           : pending|approved|rejected|cancelled
     *  - zoom_account_id  : id akun Zoom
     *  - department_id    : departemen peminjam
     *  - user_id          : peminjam tertentu
     *  - purpose          : salah satu dari PURPOSES
     *  - recurring        : '1' = hanya yang berulang, '0' = hanya yang sekali
     *  - date_from/date_to: rentang berdasarkan starts_on (tanggal mulai seri)
     *  - search           : cari nama/username peminjam atau keterangan "lainnya"
     */
    public function scopeFilter(Builder $query, array $filters): Builder
    {
        $filled = fn (string $key) => isset($filters[$key]) && $filters[$key] !== '' && $filters[$key] !== null;

        return $query
            ->when($filled('status'), fn (Builder $q) => $q->where('status', $filters['status']))
            ->when($filled('zoom_account_id'), fn (Builder $q) => $q->where('zoom_account_id', $filters['zoom_account_id']))
            ->when($filled('user_id'), fn (Builder $q) => $q->where('user_id', $filters['user_id']))
            ->when($filled('purpose'), fn (Builder $q) => $q->where('purpose', $filters['purpose']))
            ->when($filled('department_id'), fn (Builder $q) => $q->whereHas(
                'user',
                fn (Builder $u) => $u->where('department_id', $filters['department_id'])
            ))
            ->when($filled('recurring'), fn (Builder $q) => $filters['recurring'] === '1' || $filters['recurring'] === 1 || $filters['recurring'] === true
                ? $q->where('recurrence_frequency', '!=', self::RECURRENCE_NONE)
                : $q->where('recurrence_frequency', self::RECURRENCE_NONE))
            ->when($filled('date_from'), fn (Builder $q) => $q->whereDate('starts_on', '>=', $filters['date_from']))
            ->when($filled('date_to'), fn (Builder $q) => $q->whereDate('starts_on', '<=', $filters['date_to']))
            ->when($filled('search'), function (Builder $q) use ($filters) {
                $term = '%'.addcslashes(trim($filters['search']), '%_\\').'%';

                $q->where(function (Builder $w) use ($term) {
                    $w->where('purpose_other', 'like', $term)
                        ->orWhereHas('user', fn (Builder $u) => $u
                            ->where('name', 'like', $term)
                            ->orWhere('username', 'like', $term));
                });
            });
    }
}