<?php

namespace App\Models;

use App\Enum\ImportTaskType;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

/**
 * @property integer $id
 * @property string $path
 * @property string $type
 * @property boolean $done
 * @property Carbon $created_at
 * @property Carbon|null $updated_at
 *
 * @method static whereDone(integer $int)
 * @method static whereType(ImportTaskType $string)
 *
 */
class ImportTask extends Model
{

    protected $table = 'import_tasks';

    protected $casts = [
        'done' => 'boolean'
    ];
    /**
     * @var array
     */
    protected $fillable = [
        'path',
        'type',
        'done',
        'created_at',
        'updated_at'
    ];
}
