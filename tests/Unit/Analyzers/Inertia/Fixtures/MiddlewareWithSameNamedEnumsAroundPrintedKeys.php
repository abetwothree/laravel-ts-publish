<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Unit\Analyzers\Inertia\Fixtures;

use AbeTwoThree\LaravelTsPublish\Attributes\TsCasts;
use Illuminate\Http\Request;
use Inertia\Middleware;
use Workbench\App\Enums\Status;
use Workbench\Crm\Enums\Status as CrmStatus;

/** Shares two same-named enums on either side of a key Laravel renumbers and a key a cast makes optional. */
final class MiddlewareWithSameNamedEnumsAroundPrintedKeys extends Middleware
{
    /**
     * The shared props.
     *
     * @return array<array-key, mixed>
     */
    #[TsCasts(['note' => ['type' => 'string', 'optional' => true]])]
    public function share(Request $request): array
    {
        return ['a' => Status::Draft, 7 => 'seven', 'note' => 'x', 'b' => CrmStatus::Lead];
    }
}
