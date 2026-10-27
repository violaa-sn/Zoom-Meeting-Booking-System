<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['booking_id', 'day_of_week'])]
class BookingDay extends Model
{
    protected function casts(): array
    {
        return [
            // 0 = Minggu, 1 = Senin, ... 6 = Sabtu
            'day_of_week' => 'integer',
        ];
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }
}