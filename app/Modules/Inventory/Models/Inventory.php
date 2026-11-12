<?php

namespace App\Modules\Inventory\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Inventory extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'item_name',
        'category',
        'stock_quantity',
        'unit',
        'minimum_stock',
    ];

    /**
     * The attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'stock_quantity' => 'decimal:2',
            'minimum_stock' => 'decimal:2',
        ];
    }

    /**
     * Stock status: out, low, or safe.
     */
    public function stockStatus(): string
    {
        $stock = (float) $this->stock_quantity;

        if ($stock <= 0) {
            return 'out';
        }

        if ($stock <= (float) $this->minimum_stock) {
            return 'low';
        }

        return 'safe';
    }

    /**
     * The categories allowed for an inventory item.
     *
     * @return list<string>
     */
    public static function categories(): array
    {
        return [
            'pakan',
            'obat',
            'vitamin',
            'perlengkapan',
        ];
    }
}
