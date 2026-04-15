<?php

declare(strict_types=1);

namespace Workbench\App\Models;

use AbeTwoThree\LaravelTsPublish\Attributes\TsCasts;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;
use Workbench\App\Enums\MembershipLevel;
use Workbench\App\Enums\Role;
use Workbench\Database\Factories\UserFactory;

/** Application user account */
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'membership_level',
        'phone',
        'avatar',
        'bio',
        'settings',
        'options',
        'last_login_at',
        'last_login_ip',
    ];

    /**
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    #[TsCasts([
        'settings' => '{ theme: "light" | "dark"; notifications: boolean; locale: string } | null',
        'options' => 'Record<string, unknown> | null',
    ])]
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => Role::class,
            'membership_level' => MembershipLevel::class,
            'settings' => 'array',
            'options' => 'array',
            'last_login_at' => 'datetime',
        ];
    }

    public function profile(): HasOne
    {
        return $this->hasOne(Profile::class);
    }

    public function posts(): HasMany
    {
        return $this->hasMany(Post::class);
    }

    public function comments(): HasMany
    {
        return $this->hasMany(Comment::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function addresses(): HasMany
    {
        return $this->hasMany(Address::class);
    }

    public function teams(): BelongsToMany
    {
        return $this->belongsToMany(Team::class)
            ->withPivot('role', 'joined_at')
            ->withTimestamps();
    }

    public function ownedTeams(): HasMany
    {
        return $this->hasMany(Team::class, 'owner_id');
    }

    /** Polymorphic images (avatar gallery, etc.) */
    public function images(): MorphMany
    {
        return $this->morphMany(Image::class, 'imageable');
    }

    /** User name formatted with first letter capitalized */
    protected function name(): Attribute
    {
        return Attribute::make(
            get: fn ($value): string => ucfirst((string) $value),
        );
    }

    /** User initials (e.g. "JD" for "John Doe") */
    protected function initials(): Attribute
    {
        return Attribute::make(
            get: fn (): string => collect(explode(' ', $this->name))
                ->map(fn (string $part) => strtoupper(substr($part, 0, 1)))
                ->implode(''),
        );
    }

    /** Whether the user is a premium member */
    protected function isPremium(): Attribute
    {
        return Attribute::make(
            get: fn (): bool => in_array($this->membership_level, [MembershipLevel::Premium, MembershipLevel::Enterprise]),
        );
    }

    public function nameTitled(): string
    {
        return Str::headline($this->name);
    }

    public static function morphValue(): string
    {
        return (new self)->getMorphClass();
    }
}
