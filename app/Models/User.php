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
        'category', 'graduation_year', 'admission_year', 'current_class', 'house',
        'show_in_directory', 'digest_opt_in', 'notify_email',
        'bio', 'avatar_path', 'pronouns',
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
    public function businesses() { return $this->hasMany(Business::class); }
    public function bookmarks() { return $this->hasMany(Bookmark::class); }
    public function tags() { return $this->hasMany(PageTag::class); }

    /** Listed on public set/house pages and /members/{username}. */
    public function scopeDirectory($q)
    {
        return $q->where('show_in_directory', true)->whereNotNull('email_verified_at');
    }

    public function profileVisibleTo(?User $viewer): bool
    {
        return ($this->show_in_directory && $this->hasVerifiedEmail())
            || ($viewer && ($viewer->id === $this->id || $viewer->isEditor()));
    }

    /** Small public card (never email). */
    public function card(): array
    {
        return $this->only(['id', 'name', 'username', 'category', 'admission_year', 'house', 'bio', 'pronouns'])
            + [
                'public' => (bool) $this->show_in_directory,
                'avatar' => $this->avatar_path ? app(\App\Storage\MagazineStorage::class)->url($this->avatar_path) : null,
            ];
    }

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
            'is_demo' => 'boolean',
            'show_in_directory' => 'boolean',
            'digest_opt_in' => 'boolean',
            'notify_email' => 'boolean',
        ];
    }
}
