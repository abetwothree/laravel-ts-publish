<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Models;

use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Casts\NullableGetSerializedLabelCast;
use Workbench\App\Models\User;

/** An auth model identified by its not-null email, cast to a serializing caster whose get() may return null. */
class LabelIdentifierUser extends User
{
    protected $table = 'users';

    /** Names the column the guard identifies the user by. */
    public function getAuthIdentifierName(): string
    {
        return 'email';
    }

    /**
     * The parent's casts, with the email read as a label.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [...parent::casts(), 'email' => NullableGetSerializedLabelCast::class];
    }
}
