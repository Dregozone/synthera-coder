<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * An app-wide setting chosen in the UI, overriding its config default.
 *
 * @property int $id
 * @property string $key
 * @property mixed $value
 */
class Setting extends Model
{
    protected $fillable = [
        'key',
        'value',
    ];

    protected function casts(): array
    {
        return [
            'value' => 'json',
        ];
    }
}
