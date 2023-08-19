<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

/**
 * Class Site
 *
 * @property int $id
 * @property string $site_name
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property int|null $deleted
 *
 * @package App\Models
 *
 * @method whereId(int $int)
 * @method whereSiteName(string $string)
 * @method whereDeleted(int $int)
 *
 */
class Site extends Model
{
	protected $table = 'sites';

	protected $casts = [
		'deleted' => 'boolean'
	];

	protected $fillable = [
		'site_name',
		'deleted'
	];
}
