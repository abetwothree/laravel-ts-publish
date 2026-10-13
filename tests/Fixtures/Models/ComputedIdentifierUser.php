<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Models;

use Workbench\App\Models\User;

/** An auth model whose identifier is computed from its key, so Auth::id() sends that string and not the key. */
class ComputedIdentifierUser extends User
{
    /** Sends a prefixed key as the identifier. */
    public function getAuthIdentifier(): string
    {
        return 'user-'.$this->getKey();
    }
}
