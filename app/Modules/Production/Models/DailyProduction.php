<?php

namespace App\Modules\Production\Models;

use App\Modules\Livestock\Models\Livestock;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

class DailyProduction extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'livestock_id',
        'date',
        'egg_count',
        'egg_weight_kg',
        'feed_consumed_kg',
        'water_consumed_liter',
        'mortality',
    ];

    /**
     * The attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'livestock_id' => 'integer',
            'egg_count' => 'integer',
            'egg_weight_kg' => 'decimal:2',
            'feed_consumed_kg' => 'decimal:2',
            'water_consumed_liter' => 'decimal:2',
            'mortality' => 'integer',
        ];
    }

    /**
     * Store the production date as a date-only value.
     */
    protected function date(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => $value ? Carbon::parse($value)->toDateString() : null,
            set: fn ($value) => Carbon::parse($value)->toDateString(),
        );
    }

    /**
     * The flock this daily production belongs to.
     */
    public function livestock(): BelongsTo
    {
        return $this->belongsTo(Livestock::class);
    }

    /**
     * Average egg weight in grams.
     */
    public function averageEggWeight(): float
    {
        if ($this->egg_count <= 0) {
            return 0.0;
        }

        return round(((float) $this->egg_weight_kg * 1000) / $this->egg_count, 1);
    }
}
