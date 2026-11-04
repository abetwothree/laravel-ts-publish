<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures;

use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Ui\Person;
use Stringable;
use Workbench\App\Models\User;
use Workbench\App\Packages\Ui\Chart;
use Workbench\App\Packages\Ui\Panel;
use Workbench\App\ValueObjects\OpaqueHandle;

final class ReturnShapeFixture
{
    /**
     * `CustomThing` names no PHP type, standing for a type the consuming app declares as a global.
     *
     * @return array{known: string, handle: OpaqueHandle, maybe?: int, custom?: CustomThing}
     */
    public function shaped(): array
    {
        return [];
    }

    /**
     * `Stringable` is a class, whose import the string-only shape map cannot carry.
     *
     * @return array{text: Stringable, amount: int|float, spare: resource|callable, mode: 'draft'|'live'}
     */
    public function spelled(): array
    {
        return [];
    }

    /**
     * Overridden classes, whose imports the shape carries, beside a published model, which its fill queues.
     *
     * @return array{chart: Chart, nested: array{panel: Panel, n: int}, owner: User}
     */
    public function overridden(): array
    {
        return [];
    }

    /**
     * An override spelled like the model beside it, which keeps both tokens apart.
     *
     * @return array{either: Person|User}
     */
    public function spelledLikeModel(): array
    {
        return [];
    }

    /**
     * Members for keys the body reads vague; `mixed` is vague itself.
     *
     * @return array{list: list<string>, record: array<string, int>, vague: mixed, precise: int}
     */
    public function vague(): array
    {
        return [];
    }

    /**
     * @return array<string, string>|array<string, int>
     */
    public function records(): array
    {
        return [];
    }

    /**
     * @return array<string, Panel>
     */
    public function overriddenRecord(): array
    {
        return [];
    }

    /**
     * @return array<string, string>
     */
    public function record(): array
    {
        return [];
    }

    public function undocumented(): array // @phpstan-ignore missingType.iterableValue
    {
        return [];
    }
}
