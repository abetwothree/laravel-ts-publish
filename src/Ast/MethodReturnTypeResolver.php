<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Ast;

use AbeTwoThree\LaravelTsPublish\Ast\Concerns\BuildsInlineObjectTypes;
use AbeTwoThree\LaravelTsPublish\Ast\Contracts\ExpressionHandler;
use AbeTwoThree\LaravelTsPublish\Facades\LaravelTsPublish;
use AbeTwoThree\LaravelTsPublish\Facades\TsTypeString;
use Illuminate\Database\Eloquent\Model;
use ReflectionClass;

/**
 * A method's return type: the declaration first, then the shape its literal body spells when the
 * declaration is too vague to publish.
 *
 * The rules are in docs/components/receiver-types.md § Following a method's return type. A container
 * singleton, so the re-entrancy guard below is shared by every call site rather than per instance. That
 * guard is deliberate defence-in-depth and changes no result today: AstEngine::analyzeMethod() cuts the
 * same cycle itself, so this one only stops a cycle before it reaches the engine.
 *
 * @phpstan-import-type ValueExpressionResult from ExpressionHandler
 *
 * @internal
 */
final class MethodReturnTypeResolver
{
    use BuildsInlineObjectTypes;

    /** @var array<string, true> class@method bodies currently being analyzed */
    private array $analyzing = [];

    /**
     * Reflect a method's return type; when that is vague or rejected, analyze the method body once.
     *
     * @param  class-string  $class
     * @return ValueExpressionResult|null
     */
    public function resolve(string $class, string $methodName): ?array
    {
        if (! method_exists($class, $methodName)) {
            return null;
        }

        $reflected = resolve(ReflectedTypeAcceptor::class)
            ->accept(LaravelTsPublish::methodOrDocblockReturnTypes(new ReflectionClass($class), $methodName));

        if ($reflected !== null && ! TsTypeString::isVagueTsType($reflected['type'])) {
            return $reflected;
        }

        return $this->bodyType($class, $methodName) ?? $reflected;
    }

    /**
     * The inline object a method's literal return body spells, or null when it spells nothing importable.
     *
     * @param  class-string  $class
     * @return ValueExpressionResult|null
     */
    private function bodyType(string $class, string $methodName): ?array
    {
        $key = $class.'@'.$methodName;

        if (isset($this->analyzing[$key])) {
            return null;
        }

        $this->analyzing[$key] = true;

        try {
            $analysis = resolve(AstEngine::class)->analyzeMethod(
                $class,
                $methodName,
                is_a($class, Model::class, true) ? $class : null,
                carriesImports: false,
            );
        } finally {
            unset($this->analyzing[$key]);
        }

        if ($analysis->properties === []) {
            return null;
        }

        $type = $this->buildInlineObjectType($analysis);

        // The inline type carries no FQCN channel, so a token needing an import could never be emitted with one.
        return TsTypeString::shapeValueHasUnimportableToken($type) ? null : [...ValueResult::unknown(), 'type' => $type];
    }
}
