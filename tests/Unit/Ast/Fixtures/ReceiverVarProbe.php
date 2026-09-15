<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures;

use Illuminate\Support\Collection;
use Workbench\App\Models\User;
use Workbench\App\Services\UrlService;

/** Property declarations `ReceiverClassResolver` reads through `@var`, one shape per property. */
final class ReceiverVarProbe
{
    /** @var Collection<int, User>|string */
    public $collectionOrString;

    /** @var UrlService | string */
    public $spacedUnion;

    /** @var UrlService */
    public $service;

    /** @var UrlService|null */
    public $maybeService;

    protected ?UrlService $hiddenService = null;
}
