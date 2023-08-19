<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;


/**
 * Class Configuration
 *
 * @property int $id
 * @property string $name
 * @property string $sValue
 * @property int $iValue
 * @property boolean $bValue
 * @property string $type
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @package App\Models
 *
 * @method whereId(int $int)
 * @method whereName(string $string)
 * @method whereDeleted(int $int)
 *
 */
class Configuration extends Model
{
    use HasFactory;

    protected $table = "configuration";
    protected $fillable = ["type"];

     protected $casts =
     [
         "iValue" => 'integer',
         "bValue" => 'boolean'
     ];

}
