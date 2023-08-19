<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Class Owner
 * 
 * @property int $id
 * @property string $first_name
 * @property string $last_name
 * @property int|null $linked_site
 * @property int|null $linked_department
 * @property int|null $employee_id
 *
 * @package App\Models
 */
class Owner extends Model
{
	protected $table = 'owners';
	public $timestamps = false;

	protected $casts = [
		'linked_site' => 'int',
		'linked_department' => 'int',
		'employee_id' => 'int'
	];

	protected $fillable = [
		'first_name',
		'last_name',
		'linked_site',
		'linked_department',
		'employee_id'
	];
}
