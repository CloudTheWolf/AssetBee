<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Class HardwareVendor
 *
 * @property int $id
 * @property string $name
 *
 * @package App\Models
 *
 * @method whereId(int $int)
 * @method whereName(string $string)
 *
 */
class HardwareVendor extends Model
{
    protected $table = 'hardware_vendors';

    protected $fillable = [
        'name'
    ];

}
