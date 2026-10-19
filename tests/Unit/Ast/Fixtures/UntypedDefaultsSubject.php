<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures;

use Workbench\App\Enums\Status;

/**
 * A model-less subject whose properties carry only untyped defaults, so the default is the only thing that can type
 * them.
 */
final class UntypedDefaultsSubject
{
    public $slot;

    protected $extensions = ['png', 'jpg'];

    protected $limit = 10;

    protected $mixed = ['a', 1];

    protected $pending;

    protected $stage = Status::Draft;

    protected $limits = ['daily' => 5, 'codes' => ['north']];

    protected $notes = [];

    protected $positions = [1 => 'a', 2 => 'b'];
}
