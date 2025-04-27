<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Class Owner
 *
 * @property int $id
 * @property string $first_name
 * @property string $last_name
 * @property string $email
 * @property int|null $employee_id
 * @property int|null $linked_site
 * @property int|null $linked_department
 * @property int|null $is_inactive
 *
 * @package App\Models
 * @method static updateOrCreate(array $array, array $array1)
 */
class Owner extends Model
{
	protected $table = 'owners';
	public $timestamps = false;

	protected $casts = [
		'linked_site' => 'int',
		'linked_department' => 'int',
		'employee_id' => 'int',
        'is_inactive' => 'int'
	];

	protected $fillable = [
		'first_name',
		'last_name',
        'email',
		'linked_site',
		'linked_department',
		'employee_id'
	];
}
