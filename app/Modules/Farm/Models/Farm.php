<?php

namespace App\Modules\Farm\Models;

use App\Modules\Finance\Models\Expense;
use App\Modules\Livestock\Models\Livestock;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Farm extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'capacity',
        'status',
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
            'capacity' => 'integer',
        ];
    }

    /**
     * The livestock (flocks) housed in this farm.
     */
    public function livestocks(): HasMany
    {
        return $this->hasMany(Livestock::class);
    }

    /**
     * The expenses attributed to this farm.
     */
    public function expenses(): HasMany
    {
        return $this->hasMany(Expense::class);
    }

    /**
     * The statuses allowed for a farm.
     *
     * @return list<string>
     */
    public static function statuses(): array
    {
        return [
            'active',
            'maintenance',
            'inactive',
        ];
    }
}
