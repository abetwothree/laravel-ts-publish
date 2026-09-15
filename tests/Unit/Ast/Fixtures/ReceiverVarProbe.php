<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures;

use DateTime;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Stringable;
use Workbench\App\Models\User;
use Workbench\App\Services\UrlService;

/**
 * Property declarations the receiver rules read, one shape per property: a `@var` docblock where the
 * shape needs one, a native declared type where that is the shape under test.
 */
final class ReceiverVarProbe
{
    /** A date toTsType() publishes as `string`, which json_encode() writes as a date object. */
    public DateTime $plainDate;

    /** A framework model no published file exists for. */
    public Model $anyModel;

    /** A class whose jsonSerialize() is declared as the string it stringifies to. */
    public Stringable $text;

    /** @var Collection<int, User>|string */
    public $collectionOrString;

    /** @var UrlService | string */
    public $spacedUnion;

    /** @var Collection<int, User>[] */
    public $collectionArray;

    /** @var UrlService */
    public $service;

    /** @var UrlService|null */
    public $maybeService;

    protected ?UrlService $hiddenService = null;
}
