<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Carbon\Carbon;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use App\Notifications\PasswordReset;

/**
 * Class User
 *
 * @property int $id
 * @property string $name
 * @property string $email
 * @property Carbon|null $email_verified_at
 * @property string $password
 * @property string|null $remember_token
 * @property int|null $role
 * @property int|null $disabled
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * *
 * @package App\Models
 *
 * @method whereId(int $int)
 * @method whereEmail(string $string)
 * @method whereName(string $string)
 * @method whereRole(int $int)
 * @method whereDisabled(int $int)
 *
 */
class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

	protected $table = 'users';

	protected $casts = [
		'email_verified_at' => 'datetime',
        'password' => 'hashed',
        'disabled' => 'boolean'
	];

	protected $hidden = [
		'password',
		'remember_token'
	];

	protected $fillable = [
		'name',
		'email',
		'email_verified_at',
		'password',
		'remember_token',
        'role',
        'disabled'
	];

    public function getRoleAttribute()
    {
        switch ($this->attributes['role']) {
            case 1:
                return 'User';
            case 2:
                return 'Super User';
            case 3:
                return 'Administrator';
            default:
                return 'Unknown';
        }
    }

}
