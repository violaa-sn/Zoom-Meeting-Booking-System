<?php

namespace App\Models;

use DomainException;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'name',
    'email',
    'account_type',
    'allowed_login_users',
    'participant_capacity',
    'subscription_expires_at',
    'status',
    'is_default',
    'color',
    'sort_order',
])]
class ZoomAccount extends Model
{
    use SoftDeletes;

    public const TYPE_PRO = 'pro';
    public const TYPE_BUSINESS = 'business';

    /** Pilihan tipe akun untuk dropdown. */
    public const TYPES = [
        self::TYPE_PRO => 'Pro',
        self::TYPE_BUSINESS => 'Business',
    ];

    public const STATUS_ACTIVE = 'active';
    public const STATUS_INACTIVE = 'inactive';

    protected function casts(): array
    {
        return [
            'allowed_login_users' => 'integer',
            'participant_capacity' => 'integer',
            'subscription_expires_at' => 'date',
            'is_default' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        // Penjaga soft delete: akun yang masih punya booking pending atau
        // meeting approved yang belum selesai tidak boleh dihapus.
        // Event "deleting" juga dipanggil saat soft delete.
        static::deleting(function (ZoomAccount $account) {
            if ($account->hasActiveBookings()) {
                throw new DomainException(
                    'Akun Zoom tidak bisa dihapus karena masih punya booking pending '
                    .'atau meeting yang belum selesai. Batalkan atau tunggu sampai selesai.'
                );
            }
        });

        // Hanya boleh ada satu akun utama.
        static::saved(function (ZoomAccount $account) {
            if ($account->is_default && $account->wasChanged('is_default')) {
                static::withoutEvents(function () use ($account) {
                    static::query()
                        ->where('id', '!=', $account->id)
                        ->where('is_default', true)
                        ->update(['is_default' => false]);
                });
            }
        });
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }

    public function occurrences(): HasMany
    {
        return $this->hasMany(BookingOccurrence::class);
    }

    /**
     * Ada booking pending, atau meeting approved yang belum berakhir?
     */
    public function hasActiveBookings(): bool
    {
        return $this->bookings()->where('status', Booking::STATUS_PENDING)->exists()
            || $this->occurrences()
                ->where('status', BookingOccurrence::STATUS_APPROVED)
                ->where('ends_at', '>', now())
                ->exists();
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }

    /**
     * Akun yang langganannya masih berlaku sampai tanggal tertentu
     * (tanggal kedaluwarsa dianggap masih berlaku di hari itu).
     */
    public function scopeSubscribedUntil(Builder $query, \DateTimeInterface|string $date): Builder
    {
        return $query->whereDate('subscription_expires_at', '>=', $date);
    }

    /**
     * Urutan daftar di menu Akun Zoom:
     *  1. langganan sudah berakhir,
     *  2. langganan berakhir dalam 30 hari (yang paling dekat dulu),
     *  3. sisanya, akun yang paling baru ditambahkan di atas.
     * Hanya untuk tampilan daftar; kalender dan rekomendasi tetap memakai scopeOrdered.
     */
    public function scopeListOrder(Builder $query): Builder
    {
        $today = now()->startOfDay();
        $todayStr = $today->format('Y-m-d');
        // "< hari ke-31" sama artinya dengan "paling lama 30 hari dari sekarang" (badge amber di view)
        $soonStr = $today->addDays(31)->format('Y-m-d');

        return $query
            ->orderByRaw('CASE WHEN subscription_expires_at < ? THEN 0 WHEN subscription_expires_at < ? THEN 1 ELSE 2 END', [$todayStr, $soonStr])
            ->orderByRaw('CASE WHEN subscription_expires_at < ? THEN subscription_expires_at END ASC', [$soonStr])
            ->orderByDesc('id');
    }

    /** Filter daftar Akun Zoom: search (nama/email), account_type, allowed_login_users, participant_capacity. */
    public function scopeFilter(Builder $query, array $filters): Builder
    {
        $filled = fn (string $key) => isset($filters[$key]) && $filters[$key] !== '';

        return $query
            ->when($filled('search'), function (Builder $q) use ($filters) {
                $term = '%'.addcslashes(trim($filters['search']), '%_\\').'%';
                $q->where(fn (Builder $w) => $w->where('name', 'like', $term)->orWhere('email', 'like', $term));
            })
            ->when($filled('account_type'), fn (Builder $q) => $q->where('account_type', $filters['account_type']))
            ->when($filled('allowed_login_users'), fn (Builder $q) => $q->where('allowed_login_users', (int) $filters['allowed_login_users']))
            ->when($filled('participant_capacity'), fn (Builder $q) => $q->where('participant_capacity', (int) $filters['participant_capacity']));
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('id');
    }
}