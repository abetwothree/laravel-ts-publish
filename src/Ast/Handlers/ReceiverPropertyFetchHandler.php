<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Ast\Handlers;

use AbeTwoThree\LaravelTsPublish\Ast\AnalysisScope;
use AbeTwoThree\LaravelTsPublish\Ast\Contracts\ExpressionEngine;
use AbeTwoThree\LaravelTsPublish\Ast\Contracts\ExpressionHandler;
use AbeTwoThree\LaravelTsPublish\Ast\ReceiverClassResolver;
use AbeTwoThree\LaravelTsPublish\Ast\ReflectedTypeAcceptor;
use AbeTwoThree\LaravelTsPublish\Ast\StringSerialization;
use AbeTwoThree\LaravelTsPublish\Ast\SubjectPropertyTypeResolver;
use AbeTwoThree\LaravelTsPublish\Ast\ValueResult;
use AbeTwoThree\LaravelTsPublish\Facades\TsTypeString;
use AbeTwoThree\LaravelTsPublish\ModelAttributeResolver;
use Illuminate\Database\Eloquent\Model;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\NullsafePropertyFetch;
use PhpParser\Node\Expr\PropertyFetch;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Identifier;
use ReflectionClass;

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
        $results = [];

        foreach ($receiver->classes as $class) {
            $result = is_a($class, Model::class, true)
                ? $this->modelMember($class, $name)
                : $this->reflectedProperty($class, $name);

            // One untypable arm would make the union a lie; decline so dispatch reaches the floor.
            if ($result === null) {
                return null;
            }

            $results[] = $result;
        }

        $merged = count($results) === 1
            ? $results[0]
            : ValueResult::mergeUnion(array_values(array_unique(array_column($results, 'type'))), $results);

        if (($expr instanceof NullsafePropertyFetch || $receiver->shortCircuits)
            && ! in_array('null', TsTypeString::splitTopLevelUnion($merged['type']), true)
        ) {
            $merged['type'] .= ' | null';
        }

        return $merged;
    }

    /**
     * One model's attribute (accessor → cast → column) or, failing that, its relation.
     *
     * @param  class-string<Model>  $modelFqcn
     * @return ValueExpressionResult|null
     */
    private function modelMember(string $modelFqcn, string $name): ?array
    {
        $resolver = resolve(ModelAttributeResolver::class);
        $attribute = resolve(ReflectedTypeAcceptor::class)->accept($resolver->resolveAttribute($modelFqcn, $name));

        if ($attribute !== null) {
            return $attribute;
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
     * One non-model class's declared property, by the rules that type a method's return on the same receiver.
     *
     * @param  class-string  $class
     * @return ValueExpressionResult|null
     */
    private function reflectedProperty(string $class, string $name): ?array
    {
        $result = resolve(SubjectPropertyTypeResolver::class)->resolve(new ReflectionClass($class), $name);

        if ($result === null || ! ValueResult::namesOnlyPublishedModels($result)) {
            return null;
        }

        // This class's own property, never the whole expression: one arm of a union holding a plain string
        // names no class, which would otherwise read as "nothing here is a false string" for every arm.
        $held = resolve(ReceiverClassResolver::class)->memberProperty($class, $name);

        return $held !== null && array_any($held->classes, StringSerialization::isFalseString(...)) ? null : $result;
    }
}
