<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures;

use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Ui\Person;
use Stringable;
use Workbench\App\Models\Post;
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
     * List elements' members and record values a body may leave unknown inside a key it types otherwise.
     *
     * @return array{rows: list<array{name: string|null, email: string|null}>, byKey: array<string, array{n: int}>}
     */
    public function nestedLists(): array
    {
        return [];
    }

    /**
     * A nested shape and a scalar, either holding members a body may leave unknown.
     *
     * @return array{nested: array{inner: array{a: int, b: string}}, scalar: int}
     */
    public function nestedShapes(): array
    {
        return [];
    }

    /**
     * Shapes beside `null` or a list, holding members a body may leave unknown.
     *
     * @return array{maybe: array{a: int}|null, either: array{a: int}|list<int>}
     */
    public function nestedArms(): array
    {
        return [];
    }

    /**
     * Members naming models, in print order apart from the order a body may spell them in.
     *
     * @return array{owned: list<array{owner: User, n: int}>, pair: array{first: User, second: Post|null}}
     */
    public function nestedClasses(): array
    {
        return [];
    }

    /**
     * @return array<string, array{n: int}>
     */
    public function nestedRecord(): array
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
