<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Ast\Handlers;

use AbeTwoThree\LaravelTsPublish\Ast\Analysis\AnalysisScope;
use AbeTwoThree\LaravelTsPublish\Ast\Contracts\ExpressionEngine;
use AbeTwoThree\LaravelTsPublish\Ast\Contracts\ExpressionHandler;
use AbeTwoThree\LaravelTsPublish\Ast\Members\SubjectPropertyTypeResolver;
use AbeTwoThree\LaravelTsPublish\Ast\Receivers\ReceiverClassResolver;
use AbeTwoThree\LaravelTsPublish\Ast\Values\EnumValueTypes;
use AbeTwoThree\LaravelTsPublish\Ast\Values\ReflectedTypeAcceptor;
use AbeTwoThree\LaravelTsPublish\Ast\Values\ValueResult;
use AbeTwoThree\LaravelTsPublish\Cache\DependencyRecorder;
use AbeTwoThree\LaravelTsPublish\Facades\TsTypeString;
use AbeTwoThree\LaravelTsPublish\ModelAttributeResolver;
use Illuminate\Database\Eloquent\Model;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\NullsafePropertyFetch;
use PhpParser\Node\Expr\PropertyFetch;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Identifier;
use ReflectionClass;
use UnitEnum;

/**
 * `<receiver>->prop` and `<receiver>?->prop` typed from the property on the receiver's PHP class;
 * the rules are in docs/components/receiver-types.md § Property access on a receiver.
 *
 * Registered immediately before ReceiverMethodCallHandler, so every specific handler keeps priority.
 *
 * @phpstan-import-type ValueExpressionResult from ExpressionHandler
 *
 * @internal
 */
final class ReceiverPropertyFetchHandler implements ExpressionHandler
{
    /** @return list<class-string<Expr>> */
    public function nodeClasses(): array
    {
        return [PropertyFetch::class, NullsafePropertyFetch::class];
    }

    /** @return ValueExpressionResult|null */
    public function resolve(Expr $expr, AnalysisScope $scope, ExpressionEngine $engine): ?array
    {
        if (! ($expr instanceof PropertyFetch || $expr instanceof NullsafePropertyFetch) || ! $expr->name instanceof Identifier) {
            return null;
        }

        // A `$this->prop` leaf is ThisPropertyHandler's, which reads the subject's own declaration first.
        if ($expr->var instanceof Variable && $expr->var->name === 'this') {
            return null;
        }

        $receiver = resolve(ReceiverClassResolver::class)->resolve($expr->var, $scope);

        if ($receiver === null) {
            return null;
        }

        $name = $expr->name->toString();
        $attributeless = $receiver->attributeless && ! in_array($name, $receiver->filled, true);
        $results = [];

        foreach ($receiver->classes as $class) {
            // The answer, a decline included, follows the class's declarations, which reflection reads without parsing.
            DependencyRecorder::recordClass($class);
            $result = is_a($class, Model::class, true)
                ? $this->modelMember($class, $name, $scope->carriesImports, $attributeless)
                : $this->enumMember($class, $name, $scope->carriesImports) ?? $this->reflectedProperty($class, $name);

            // One untypable arm would make the union a lie; decline so dispatch reaches the floor.
            if ($result === null) {
                return null;
            }

            $results[] = $result;
        }

        $merged = count($results) === 1
            ? $results[0]
            : ValueResult::unionResults($results);

        if (($expr instanceof NullsafePropertyFetch || $receiver->shortCircuits)
            && ! in_array('null', TsTypeString::splitTopLevelUnion($merged['type']), true)
        ) {
            $merged['type'] .= ' | null';
        }

        return $merged;
    }

    /**
     * One model's attribute (accessor → cast → column) or, failing that, its relation; a column a model with no
     * attributes reads is null.
     *
     * @param  class-string<Model>  $modelFqcn
     * @return ValueExpressionResult|null
     */
    private function modelMember(string $modelFqcn, string $name, bool $carriesImports, bool $attributeless): ?array
    {
        $resolver = resolve(ModelAttributeResolver::class);
        $attribute = resolve(ReflectedTypeAcceptor::class)->accept($resolver->resolveAttribute($modelFqcn, $name, $carriesImports));

        if ($attribute !== null) {
            return $attributeless && $this->readsNullWithoutAttributes($resolver, $modelFqcn, $name)
                ? [...$attribute, 'type' => ValueResult::withNullArm($attribute['type'])]
                : $attribute;
        }

        $relation = $resolver->resolveRelation($modelFqcn, $name);

        if ($relation['type'] === 'unknown') {
            return null;
        }

        /** @var ValueExpressionResult $result */
        $result = [...ValueResult::unknown(), 'type' => $relation['type']];

        if ($relation['modelFqcn'] !== null) {
            $result['modelFqcn'] = $relation['modelFqcn'];
        }

        if ($relation['morphFqcns'] !== []) {
            $result['embeddedModelFqcns'] = $relation['morphFqcns'];
        }

        return $result;
    }

    /**
     * Whether a model with no attributes reads a column as null: one its new instance holds no default for, which no
     * accessor or cast class computes; a built-in or enum cast reads a missing value as null, a cast class may not.
     *
     * @param  class-string<Model>  $modelFqcn
     */
    private function readsNullWithoutAttributes(ModelAttributeResolver $resolver, string $modelFqcn, string $name): bool
    {
        $column = $resolver->getAttributes($modelFqcn)?->firstWhere('name', $name);
        $instance = $resolver->getInstance($modelFqcn);
        $cast = $column['cast'] ?? null;
        $castClass = $cast === null ? null : explode(':', $cast, 2)[0];

        return $column !== null
            && $column['type'] !== null
            && $instance !== null
            && ! array_key_exists($name, $instance->getAttributes())
            && ! in_array($cast, ['accessor', 'attribute'], true)
            && ($castClass === null || ! class_exists($castClass) || enum_exists($castClass));
    }

    /**
     * An enum's `value` or `name`, or the enum interfaces', as EnumValueTypes publishes them; null for any other read.
     *
     * @param  class-string  $class
     * @return ValueExpressionResult|null
     */
    private function enumMember(string $class, string $name, bool $carriesImports): ?array
    {
        if (! is_a($class, UnitEnum::class, true)) {
            return null;
        }

        return match ($name) {
            'value' => EnumValueTypes::value($class, $carriesImports),
            'name' => EnumValueTypes::name($class, $carriesImports),
            default => null,
        };
    }

    /**
     * One non-model class's public instance property, by the rules that type a method's return on the same receiver.
     *
     * @param  class-string  $class
     * @return ValueExpressionResult|null
     */
    private function reflectedProperty(string $class, string $name): ?array
    {
        $reflection = new ReflectionClass($class);
        $property = $reflection->hasProperty($name) ? $reflection->getProperty($name) : null;

        // An outside read of a non-public or static property goes to __get(), never to the declaration.
        if ($property === null || ! $property->isPublic() || $property->isStatic()) {
            return null;
        }

        $result = resolve(SubjectPropertyTypeResolver::class)->resolve($reflection, $name);

        return $result !== null && ValueResult::namesOnlyPublishedModels($result) ? $result : null;
    }
}
