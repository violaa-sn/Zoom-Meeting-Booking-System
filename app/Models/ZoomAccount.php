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

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('id');
    }
}