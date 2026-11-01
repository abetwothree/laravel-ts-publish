<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures;

/** Declares the label() the classes below reach under aliases. */
trait AliasedLabelTrait
{
    /** The label. */
    public function label(): string
    {
        return 'from trait';
    }
}

/** Passes AliasedLabelTrait's label() on under a protected alias of its own. */
trait ReAliasesLabel
{
    use AliasedLabelTrait {
        label as protected midLabel;
    }
}

/** Reaches label() through two aliases, one in each trait it uses. */
class UsesNestedTraitAlias
{
    use ReAliasesLabel {
        midLabel as public tag;
    }
}

/** Declares its own tag() over the alias of the same name, which PHP then never dispatches. */
class ShadowsTraitAlias
{
    use AliasedLabelTrait {
        label as tag;
    }

    /** The class's own label. */
    public function tag(): string
    {
        return 'own';
    }
}
