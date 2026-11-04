<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Ast\Handlers\Members;

use AbeTwoThree\LaravelTsPublish\Ast\Analysis\AnalysisScope;
use AbeTwoThree\LaravelTsPublish\Ast\Analysis\RuntimeClass;
use AbeTwoThree\LaravelTsPublish\Ast\Contracts\ExpressionEngine;
use AbeTwoThree\LaravelTsPublish\Ast\Contracts\ExpressionHandler;
use AbeTwoThree\LaravelTsPublish\Ast\Members\SubjectPropertyTypeResolver;
use AbeTwoThree\LaravelTsPublish\Ast\Receivers\ReceiverClassResolver;
use AbeTwoThree\LaravelTsPublish\Ast\Receivers\ReceiverType;
use AbeTwoThree\LaravelTsPublish\Ast\Values\EnumValueTypes;
use AbeTwoThree\LaravelTsPublish\Ast\Values\ReflectedTypeAcceptor;
use AbeTwoThree\LaravelTsPublish\Ast\Values\ValueResolver;
use AbeTwoThree\LaravelTsPublish\Ast\Values\ValueResult;
use AbeTwoThree\LaravelTsPublish\Cache\DependencyRecorder;
use AbeTwoThree\LaravelTsPublish\Facades\TsTypeString;
use AbeTwoThree\LaravelTsPublish\ModelAttributeResolver;
use Illuminate\Database\Eloquent\Model;
use PhpParser\ConstExprEvaluationException;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\ClassConstFetch;
use PhpParser\Node\Expr\ConstFetch;
use PhpParser\Node\Expr\Match_;
use PhpParser\Node\Expr\NullsafePropertyFetch;
use PhpParser\Node\Expr\PropertyFetch;
use PhpParser\Node\Expr\Ternary;
use PhpParser\Node\Expr\Throw_;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Identifier;
use PhpParser\Node\MatchArm;
use ReflectionClass;
use UnitEnum;

/**
 * `<receiver>->prop` and `<receiver>?->prop` typed from the property on the receiver's PHP class, or off an enum case
 * itself as the literal it sends; the rules are in docs/components/receiver-types.md § Property access on a receiver.
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

        $name = $expr->name->toString();
        $caseMember = $this->caseMember($expr->var, $name, $scope);

        if ($caseMember !== null) {
            return $expr instanceof NullsafePropertyFetch
                ? [...$caseMember, 'type' => TsTypeString::withNullArm($caseMember['type'])]
                : $caseMember;
        }

        $receiver = resolve(ReceiverClassResolver::class)->resolve($expr->var, $scope);

        if ($receiver === null) {
            return null;
        }

        $attributeless = $receiver->attributeless && ! in_array($name, $receiver->filled, true);
        $results = [];

        foreach ($receiver->classes as $class) {
            // The answer, a decline included, follows the class's declarations, which reflection reads without parsing.
            DependencyRecorder::recordClass($class);
            $result = is_a($class, Model::class, true)
                ? $this->modelMember($class, $name, $scope, $attributeless)
                : EnumValueTypes::member($class, $name, $scope->carriesImports)
                    ?? $this->reflectedProperty($class, $name, $receiver->declaredOnly);

            // One untypable arm would make the union a lie; decline so dispatch reaches the floor.
            if ($result === null) {
                return null;
            }

            $results[] = $result;
        }

        $merged = count($results) === 1
            ? $results[0]
            : ValueResult::unionResults($results);

        if ($expr instanceof NullsafePropertyFetch || $receiver->shortCircuits) {
            $merged['type'] = TsTypeString::withNullArm($merged['type']);
        }

        return $merged;
    }

    /**
     * A member read off cases themselves: a case, or a ternary or `match` whose every arm is one, `null` or a
     * `throw`, each case sending its own literal; null for any other receiver, a member a case lacks, or no case.
     *
     * @return ValueExpressionResult|null
     */
    private function caseMember(Expr $receiver, string $name, AnalysisScope $scope): ?array
    {
        $arms = match (true) {
            $receiver instanceof Ternary => [$receiver->if ?? $receiver->cond, $receiver->else],
            $receiver instanceof Match_ => array_map(fn (MatchArm $arm): Expr => $arm->body, $receiver->arms),
            default => null,
        };

        if ($arms === null) {
            return $this->ownCaseMember($receiver, $name, $scope);
        }

        $results = [];

        foreach ($arms as $arm) {
            // A `throw` arm sends nothing, and a `null` one is skipped as ReceiverClassResolver skips it: only `?->`
            // adds its null.
            if ($arm instanceof Throw_ || ($arm instanceof ConstFetch && $arm->name->toLowerString() === 'null')) {
                continue;
            }

            $result = $this->caseMember($arm, $name, $scope);

            if ($result === null) {
                return null;
            }

            $results[] = $result;
        }

        return $results === [] ? null : ValueResult::unionResults($results);
    }

    /**
     * The member one `E::Case` sends, read off the case its constant expression evaluates to.
     *
     * @return ValueExpressionResult|null
     */
    private function ownCaseMember(Expr $receiver, string $name, AnalysisScope $scope): ?array
    {
        if (! $receiver instanceof ClassConstFetch) {
            return null;
        }

        try {
            $case = resolve(ValueResolver::class)->evaluateConstantExpression($receiver, $scope);
        } catch (ConstExprEvaluationException) {
            return null;
        }

        return $case instanceof UnitEnum ? EnumValueTypes::caseMember($case, $name) : null;
    }

    /**
     * One model's attribute (accessor → cast → column) or, failing that, its relation; a column a model with no
     * attributes reads is null.
     *
     * @param  class-string<Model>  $modelFqcn
     * @return ValueExpressionResult|null
     */
    private function modelMember(string $modelFqcn, string $name, AnalysisScope $scope, bool $attributeless): ?array
    {
        $resolver = resolve(ModelAttributeResolver::class);
        $attribute = resolve(ReflectedTypeAcceptor::class)
            ->accept($resolver->resolveAttribute($modelFqcn, $name, $scope->carriesImports, $scope->casesAsLiterals));

        if ($attribute !== null) {
            return $attributeless && $this->readsNullWithoutAttributes($resolver, $modelFqcn, $name)
                ? [...$attribute, 'type' => TsTypeString::withNullArm($attribute['type'])]
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
     * One non-model class's public instance property, by the rules that type a method's return on the same receiver,
     * a receiver spelled `self`, `static` or `parent` reading one a subclass can redeclare by its declaration alone.
     *
     * @param  class-string  $class
     * @return ValueExpressionResult|null
     */
    private function reflectedProperty(string $class, string $name, bool $declaredOnly): ?array
    {
        $reflection = new ReflectionClass($class);
        $property = $reflection->hasProperty($name) ? $reflection->getProperty($name) : null;

        // An outside read of a non-public or static property goes to __get(), never to the declaration.
        if ($property === null || ! $property->isPublic() || $property->isStatic()) {
            return null;
        }

        $properties = resolve(SubjectPropertyTypeResolver::class);
        $result = $declaredOnly && ReceiverType::overridable($reflection, $property)
            ? $properties->declaredType($reflection, $property)
            : $properties->resolve($reflection, $name, RuntimeClass::assumed($reflection));

        return $result !== null && ValueResult::namesOnlyPublishedModels($result) ? $result : null;
    }
}
