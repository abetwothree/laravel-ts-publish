<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Models;

use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\ValueObjects\LooseName;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\ValueObjects\NumberedName;
use Workbench\App\Models\User;
use Workbench\App\ValueObjects\CartTotals;

/** A user on the `users` table whose methods retype a user parameter over `$this` in a branch, then read its name. */
class ThisTaggedUser extends User
{
    protected $table = 'users';

    /** The name of the user passed, or of this user, which a `static` tag names. */
    public function staticName(User $a)
    {
        if ($this->id > 0) {
            /** @var static $a */
            $a = $this;
        }

        return $a->name;
    }

    /** The name of the user passed, or of this user, which a tag names as a value object. */
    public function totalsName(User $a)
    {
        if ($this->id > 0) {
            /** @var CartTotals $a */
            $a = $this;
        }

        return $a->name;
    }

    /** The name of a value, or of this user, which a tag names beside a value object no declaration types. */
    public function unionName()
    {
        $a = new NumberedName;

        if ($this->id > 0) {
            /** @var static|LooseName $a */
            $a = $this;
        }

        return $a->name;
    }
}
