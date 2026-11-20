<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Ast\Effects\Trust;

use AbeTwoThree\LaravelTsPublish\Ast\Analysis\AnalysisMemo;
use AbeTwoThree\LaravelTsPublish\Ast\Docblocks\DeclaringFile;
use AbeTwoThree\LaravelTsPublish\Ast\Members\FrameworkClasses;
use AbeTwoThree\LaravelTsPublish\Ast\Parsing\MethodLocator;
use PhpParser\Node\Expr\PropertyFetch;
use PhpParser\Node\Identifier;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\Stmt\Unset_;
use PhpParser\NodeFinder;
use ReflectionClass;

/**
 * The properties the app's own code may unset on `$this`: once unset, PHP reaches a declared property through the
 * runtime class's magic methods, as it does one the class lacks. The literal-getter trust and the dispatch walk both
 * read it.
 *
 * @phpstan-type Unsets = array{names: list<string>, any: bool}
 *
 * @internal
 */
final class UnsetProperties
{
    /**
     * Whether a method the app declares on the runtime class or an ancestor below the framework may unset
     * `$this->{name}`: by that name, quoted or not, on any object, or by one it computes, which may be any.
     *
     * @param  ReflectionClass<object>  $runtime
     */
    public static function unsets(ReflectionClass $runtime, string $name): bool
    {
        $unsets = self::unsetsOf($runtime);

        return $unsets['any'] || in_array($name, $unsets['names'], true);
    }

    /**
     * What the app's methods unset, read once a publish run per runtime class.
     *
     * @param  ReflectionClass<object>  $runtime
     * @return Unsets
     */
    private static function unsetsOf(ReflectionClass $runtime): array
    {
        return resolve(AnalysisMemo::class)->rememberFact(
            'unset-properties:'.$runtime->getName(),
            static fn (): array => self::scanned($runtime),
        );
    }

    /**
     * The names each method the app declares on the runtime class or an ancestor below the framework unsets on any
     * object, beside whether one unsets a name it computes.
     *
     * @param  ReflectionClass<object>  $runtime
     * @return Unsets
     */
    private static function scanned(ReflectionClass $runtime): array
    {
        $locator = resolve(MethodLocator::class);
        $names = [];
        $any = false;
        $class = $runtime;

        // A method the class overrides may still run, through `parent::`, so each ancestor's own methods count.
        while ($class !== false && ! FrameworkClasses::is($class->getName())) {
            foreach ($class->getMethods() as $method) {
                $body = $method->getDeclaringClass()->getName() === $class->getName()
                    && ! FrameworkClasses::is(DeclaringFile::ofMethod($method)->getName())
                    ? $locator->locate($class->getName(), $method->getName())?->method->stmts
                    : null;

                foreach (new NodeFinder()->findInstanceOf($body ?? [], Unset_::class) as $unset) {
                    foreach ($unset->vars as $var) {
                        // A local may hold `$this`, so a property unset on any object counts.
                        $name = $var instanceof PropertyFetch ? $var->name : null;

                        if ($name instanceof Identifier || $name instanceof String_) {
                            $names[] = $name instanceof String_ ? $name->value : $name->toString();
                        } elseif ($name !== null) {
                            $any = true;
                        }
                    }
                }
            }

            $class = $class->getParentClass();
        }

        return ['names' => array_values(array_unique($names)), 'any' => $any];
    }
}
