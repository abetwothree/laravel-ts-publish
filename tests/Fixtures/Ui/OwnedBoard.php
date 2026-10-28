<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Ui;

use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Models\InheritingChildModel;
use Workbench\App\Models\User;
use Workbench\App\Packages\Ui\Board;

/**
 * A value object beside a published model and a list of it, which its shape cannot import, a model with an inherited
 * override, a value object whose members carry overrides, and a documented list of strings.
 */
final class OwnedBoard
{
    public function __construct(
        public User $owner,
        /** @var list<User> */
        public array $owners = [],
        public InheritingChildModel $node = new InheritingChildModel,
        public Board $board = new Board,
        /** @var list<string> */
        public array $tags = [],
    ) {}
}
