<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures;

use Workbench\App\Enums\Role;
use Workbench\App\Models\User;

final class DeclaredProps
{
    use Stamped;

    /** @var list<string> */
    public array $tags = [];

    /** @var array{label: string, count: int} */
    public array $summary = [];

    /** @var User|null */
    public ?object $owner = null;

    public Role $role = Role::Admin;

    public function __construct(
        public int $id = 0,
        public ?string $note = null,
    ) {}
}
