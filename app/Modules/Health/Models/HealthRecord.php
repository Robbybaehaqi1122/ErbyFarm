<?php

namespace App\Modules\Health\Models;

use App\Modules\Livestock\Models\Livestock;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

class HealthRecord extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'livestock_id',
        'treatment_type',
        'medicine_name',
        'dosage',
        'date',
        'notes',
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
        ];
    }

    /**
     * Store the treatment date as a date-only value.
     */
    protected function date(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => $value ? Carbon::parse($value)->toDateString() : null,
            set: fn ($value) => Carbon::parse($value)->toDateString(),
        );
    }

    /**
     * The flock (livestock) this health record belongs to.
     */
    public function livestock(): BelongsTo
    {
        return $this->belongsTo(Livestock::class);
    }

    /**
     * The treatment types allowed for a health record.
     *
     * @return list<string>
     */
    public static function treatmentTypes(): array
    {
        return [
            'vaksinasi',
            'pengobatan',
            'vitamin',
            'culling',
        ];
    }
}
