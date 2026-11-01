<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name', 'username', 'email', 'password',
        'category', 'graduation_year', 'current_class', 'house',
    ];

    protected $appends = ['is_admin', 'is_editor'];

    public function isAdmin(): bool
    {
        if ($this->role === 'admin' || $this->category === 'admin') return true;
        $ids = \App\Support\Roles::adminIdentifiers();
        return in_array(strtolower((string) $this->username), $ids, true)
            || in_array(strtolower((string) $this->email), $ids, true);
    }

    public function isEditor(): bool
    {
        return $this->isAdmin() || $this->role === 'editor' || $this->category === 'editorial';
    }

    public function getIsAdminAttribute(): bool { return $this->isAdmin(); }
    public function getIsEditorAttribute(): bool { return $this->isEditor(); }

    public function submissions() { return $this->hasMany(Submission::class); }

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }
}
