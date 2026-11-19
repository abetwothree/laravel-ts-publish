<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Ast\Effects\Trust;

use AbeTwoThree\LaravelTsPublish\Ast\Analysis\AnalysisMemo;
use AbeTwoThree\LaravelTsPublish\Ast\Docblocks\DeclaringFile;
use AbeTwoThree\LaravelTsPublish\Ast\Members\FrameworkClasses;
use AbeTwoThree\LaravelTsPublish\Ast\Parsing\MethodLocator;
use AbeTwoThree\LaravelTsPublish\Ast\Parsing\ReadPath;
use PhpParser\Node\Expr\PropertyFetch;
use PhpParser\Node\Identifier;
use PhpParser\Node\Stmt\Unset_;
use PhpParser\NodeFinder;
use ReflectionClass;

/**
 * The properties the app's own code unsets on `$this`: once unset, PHP reaches a declared property through the runtime
 * class's magic methods, as it does one the class lacks. The literal-getter trust and the dispatch walk both read it.
 *
 * @internal
 */
final class UnsetProperties
{
    /**
     * Whether a method the app declares on the runtime class or an ancestor below the framework unsets `$this->{name}`.
     *
     * @param  ReflectionClass<object>  $runtime
     */
    public static function unsets(ReflectionClass $runtime, string $name): bool
    {
        return in_array($name, self::names($runtime), true);
    }

    /**
     * The names the app's methods unset on `$this`, read once a publish run per runtime class.
     *
     * @param  ReflectionClass<object>  $runtime
     * @return list<string>
     */
    private static function names(ReflectionClass $runtime): array
    {
        return resolve(AnalysisMemo::class)->rememberFact(
            'unset-properties:'.$runtime->getName(),
            static fn (): array => self::scanned($runtime),
        );
    }

    /**
     * The names each method the app declares on the runtime class or an ancestor below the framework unsets on `$this`.
     *
     * @param  ReflectionClass<object>  $runtime
     * @return list<string>
     */
    private static function scanned(ReflectionClass $runtime): array
    {
        $locator = resolve(MethodLocator::class);
        $names = [];
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
                        if ($var instanceof PropertyFetch && ReadPath::isThis($var->var) && $var->name instanceof Identifier) {
                            $names[] = $var->name->toString();
                        }
                    }
                }
            }

            $class = $class->getParentClass();
        }

        return array_values(array_unique($names));
    }
}
