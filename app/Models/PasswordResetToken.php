<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/***
 * PasswordResetToken Class
 *
 * @property string $email
 * @property string $token
 * @property Carbon $created_at
 * *
 * @package App\Models
 *
 * @method whereEmail(string $string)
 *
 */
class PasswordResetToken extends Model
{
    use HasFactory;

    protected $table = 'password_reset_tokens';
    protected $primaryKey = 'email';
    public $timestamps = false;

    protected $casts = [
        'token' => 'hashed',
        'created_at' => 'datetime'
    ];

    protected $hidden = [
      'token'
    ];

    protected $fillable = [
        'email',
        'token'
    ];
}
