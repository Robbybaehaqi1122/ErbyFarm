<?php

namespace App\Modules\Livestock\Models;

use App\Modules\Farm\Models\Farm;
use App\Modules\Health\Models\HealthRecord;
use App\Modules\Production\Models\DailyProduction;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Livestock extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'farm_id',
        'initial_population',
        'current_population',
        'entry_date',
        'age_weeks',
        'status',
    ];

    /**
     * The attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'farm_id' => 'integer',
            'initial_population' => 'integer',
            'current_population' => 'integer',
            'entry_date' => 'date',
            'age_weeks' => 'integer',
        ];
    }

    /**
     * The farm (kandang) this flock lives in.
     */
    public function farm(): BelongsTo
    {
        return $this->belongsTo(Farm::class);
    }

    /**
     * The daily production records for this flock.
     */
    public function dailyProductions(): HasMany
    {
        return $this->hasMany(DailyProduction::class);
    }

    /**
     * The health and treatment records for this flock.
     */
    public function healthRecords(): HasMany
    {
        return $this->hasMany(HealthRecord::class);
    }

    /**
     * The statuses allowed for a livestock record.
     *
     * @return list<string>
     */
    public static function statuses(): array
    {
        return [
            'active',
            'culled',
            'empty',
        ];
    }

    /**
     * The number of surviving chickens as a percentage of the initial flock.
     */
    public function survivalRate(): float
    {
        if ($this->initial_population <= 0) {
            return 0.0;
        }

        return round(($this->current_population / $this->initial_population) * 100, 1);
    }
}
