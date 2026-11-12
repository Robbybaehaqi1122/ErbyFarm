<?php

namespace App\Modules\Finance\Models;

use App\Modules\Farm\Models\Farm;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

class Expense extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'farm_id',
        'category',
        'amount',
        'date',
        'description',
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
            'amount' => 'decimal:2',
        ];
    }

    /**
     * Store the transaction date as a date-only value.
     */
    protected function date(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => $value ? Carbon::parse($value)->toDateString() : null,
            set: fn ($value) => Carbon::parse($value)->toDateString(),
        );
    }

    /**
     * The farm this expense belongs to (null = general farm expense).
     */
    public function farm(): BelongsTo
    {
        return $this->belongsTo(Farm::class);
    }

    /**
     * The categories allowed for an expense.
     *
     * @return list<string>
     */
    public static function categories(): array
    {
        return [
            'pakan',
            'obat_vaksin',
            'operasional',
            'gaji',
            'lain_lain',
        ];
    }
}
