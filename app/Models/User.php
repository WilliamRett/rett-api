<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Tymon\JWTAuth\Contracts\JWTSubject;

class User extends Authenticatable implements JWTSubject
{
   use HasFactory, Notifiable;

    protected $fillable = ['name','email','password'];
    protected $hidden = ['password','remember_token'];

    /**
     * Relationship to the Collaborators model.
     *
     * @return HasMany
     *
     */
    public function collaborators(): HasMany
    {
        return $this->hasMany(Collaborator::class);
    }

    public function getJWTIdentifier()
    {
        return $this->getKey();
    }

    public function getJWTCustomClaims(): array
    {
        return [];
    }
}
