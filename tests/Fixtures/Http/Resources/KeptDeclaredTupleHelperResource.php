<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

/** The declared-tuple resource, preserving keys. */
final class KeptDeclaredTupleHelperResource extends DeclaredTupleHelperResource
{
    public $preserveKeys = true;
}
