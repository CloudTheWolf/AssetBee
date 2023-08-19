<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Class Department
 * 
 * @property int $id
 * @property string $name
 * @property int|null $owner_id
 *
 * @package App\Models
 */
class Department extends Model
{
	protected $table = 'departments';
	public $timestamps = false;

	protected $casts = [
		'owner_id' => 'int'
	];

	protected $fillable = [
		'name',
		'owner_id'
	];
}
