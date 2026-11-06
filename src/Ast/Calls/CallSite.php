<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Ast\Calls;

use AbeTwoThree\LaravelTsPublish\Ast\Analysis\AnalysisScope;
use AbeTwoThree\LaravelTsPublish\Ast\Analysis\HeldCallables;
use AbeTwoThree\LaravelTsPublish\Ast\Analysis\Reading;
use AbeTwoThree\LaravelTsPublish\Ast\Concerns\InspectsAstNodes;
use AbeTwoThree\LaravelTsPublish\Ast\Contracts\ExpressionEngine;
use AbeTwoThree\LaravelTsPublish\Ast\Docblocks\PropertyDocblockTypeReader;
use AbeTwoThree\LaravelTsPublish\Ast\Receivers\DeclaredClasses;
use AbeTwoThree\LaravelTsPublish\Ast\Receivers\ReceiverClassResolver;
use AbeTwoThree\LaravelTsPublish\Ast\Receivers\ReceiverType;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\CallLike;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\NullsafeMethodCall;
use PhpParser\Node\Expr\NullsafePropertyFetch;
use PhpParser\Node\Expr\PropertyFetch;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use ReflectionClass;
use ReflectionMethod;
use ReflectionNamedType;
use ReflectionParameter;
use ReflectionProperty;

/**
 * A call read where it is written, so the method it runs can be read with what the call passes: how each argument
 * binds a parameter whose declaration binds nothing, one declaring an abstract class, interface or union it narrows,
 * or one declaring a concrete model it passes as one no row backs yet.
 *
 * @phpstan-import-type PassedBinding from ParameterBinder
 *
 * @internal
 */
final readonly class CallSite
{
    use InspectsAstNodes;

    /**
     * Read a call in the scope and engine it is written in.
     */
    public function __construct(
        private CallLike $call,
        private AnalysisScope $scope,
        private ExpressionEngine $engine,
    ) {}

    /**
     * The memo key suffix for what a call binds, so two calls that bind alike share one answer; empty when the call
     * binds nothing, so it keeps the method's own key.
     *
     * @param  array<string, PassedBinding>  $passed
     */
    public static function signature(array $passed): string
    {
        return $passed === [] ? '' : '@args:'.hash('xxh128', serialize($passed));
    }

    /**
     * How what the caller reads reaches the client, and so what the call returns there, which the body it runs takes.
     */
    public function reading(): Reading
    {
        return $this->engine->reading()->calledFrom($this->scope);
    }

    /**
     * Whether the call writes any argument a parameter could take, a first-class callable's `...` being none.
     */
    public function passesArguments(): bool
    {
        return ! $this->call->isFirstClassCallable() && $this->call->getArgs() !== [];
    }

    /**
     * How this call's arguments bind each parameter of the method it runs whose declaration binds nothing, or which an
     * argument narrows, read in the caller's scope; an unpacked or first-class call, and an argument nothing can type,
     * bind nothing.
     *
     * @param  bool  $valueRead  the call is read as a value, not spread in place: a first-class callable binds nothing
     * @return array<string, PassedBinding>
     */
    public function passedTo(ReflectionMethod $method, bool $valueRead = false): array
    {
        if (! $this->passesArguments()) {
            return [];
        }

        $arguments = CallArguments::for($this->call, $method);

        if ($arguments->hasUnpack()) {
            return [];
        }

        $passed = [];

        foreach ($method->getParameters() as $parameter) {
            $argument = $arguments->named($parameter->getName());
            $binding = $argument === null || $this->passesUncalledCallable($argument->value, $valueRead)
                ? null
                : $this->bindingFor($parameter, $method, $argument->value);

            if ($binding !== null) {
                $passed[$parameter->getName()] = $binding;
            }
        }

        return $passed;
    }

    /**
     * How one argument binds its parameter: whatever it holds where the declaration binds nothing, else the narrower
     * classes it holds where the declaration names an abstract class, interface or union, or a model no row backs yet
     * where it names that concrete model; null otherwise.
     *
     * A `@param` collection generic gives way to the collection the argument holds, naming only an element it leaves
     * unknown.
     *
     * A guarded argument leaves a declaration to read as it does with nothing passed, but any argument passed inside a
     * callback no reading reaches without the guard keeps the guard across any declaration, whatever it is read off.
     *
     * @return PassedBinding|null
     */
    private function bindingFor(ReflectionParameter $parameter, ReflectionMethod $method, Expr $argument): ?array
    {
        $binder = resolve(ParameterBinder::class);
        $documented = $binder->documentedCollection($parameter, $method);

        // A docblock holds what its author wrote, so the collection the argument holds outranks it where it is known.
        if ($documented !== null) {
            $receiver = resolve(ReceiverClassResolver::class)->resolve($argument, $this->scope);

            return $binder->passedCollection(
                $parameter,
                $documented,
                $this->engine->resolve($argument),
                $receiver,
                $this->scope->readsUnreadCallback(),
                $this->boundsArgument($argument),
            );
        }

        $takes = $binder->takesPassedValue($parameter, $method);
        $declared = $takes ? null : $binder->narrowableClasses($parameter);
        $model = $takes || $declared !== null ? null : $binder->declaredModel($parameter);

        if (! $takes && $declared === null && $model === null) {
            return null;
        }

        $receiver = resolve(ReceiverClassResolver::class)->resolve($argument, $this->scope);
        $unread = $this->scope->readsUnreadCallback();

        // PHP holds the declared model there whatever the argument reads as, so the guard rides on it.
        if ($unread && $model !== null) {
            $held = $receiver !== null && $receiver->within([$model]) ? $receiver : ReceiverType::of($model);
            $binding = $binder->passedBinding($this->engine->resolve($argument), $held, refines: true);

            return $binding === null ? null : [...$binding, 'guard' => 'unread'];
        }

        // A declared model binds as a row that exists; only an argument no row backs yet reads differently, as a new
        // declared model whichever of its classes it holds, since passedBinding() marks only one class unsaved.
        if ($model !== null) {
            if ($receiver === null || $receiver->guarded || ! $receiver->unsaved || ! $receiver->within([$model])) {
                return null;
            }

            $held = count($receiver->classes) === 1 ? $receiver : new ReceiverType([$model], unsaved: true);

            return $binder->passedBinding($this->engine->resolve($argument), $held, refines: true);
        }

        if ($declared !== null && (($receiver?->guarded === true && ! $unread) || ! $binder->narrows($declared, $receiver))) {
            return null;
        }

        $binding = $binder->passedBinding($this->engine->resolve($argument), $receiver, refines: $declared !== null);

        return $unread && $binding !== null ? [...$binding, 'guard' => 'unread'] : $binding;
    }

    /**
     * Whether an argument's classes are only what a declaration names, which a subclass's object may stand for: a
     * method's or macro's return other than its own class, a typed or documented property, or an inline `@var` its
     * value names no class beside; the one write a local sees is followed. Anything else names the class it is.
     */
    private function boundsArgument(Expr $argument): bool
    {
        $receivers = resolve(ReceiverClassResolver::class);

        // The bound keeps a local that reads its own write from walking without end.
        for ($step = 0; $step < 16; $step++) {
            if ($argument instanceof Variable) {
                $span = $this->scope->declaredAt($argument);
                $next = $span === null ? $this->scope->soleWriteSeenBy($argument) : $span['expr'];

                if ($span !== null && $receivers->resolve($span['expr'], $this->scope) === null) {
                    return true;
                }

                if ($next === null) {
                    return false;
                }

                $argument = $next;

                continue;
            }

            if ($argument instanceof PropertyFetch || $argument instanceof NullsafePropertyFetch) {
                return $this->declaresProperty($argument);
            }

            if ((! $argument instanceof MethodCall && ! $argument instanceof NullsafeMethodCall && ! $argument instanceof StaticCall)
                || ! $argument->name instanceof Identifier
            ) {
                return false;
            }

            $bounds = $this->callBounds($argument, $argument->name->toString());

            if ($bounds !== null || $argument instanceof StaticCall) {
                return $bounds === true;
            }

            $argument = $argument->var;
        }

        return false;
    }

    /**
     * Whether a call's classes are only its method's or macro's declared return: true for a class other than its own,
     * null for a method's `static` or `$this`, which is as exact as the receiver, false for a call nothing declares and
     * for a macro returning its own class.
     */
    private function callBounds(MethodCall|NullsafeMethodCall|StaticCall $call, string $method): ?bool
    {
        $classes = match (true) {
            $call instanceof StaticCall => $this->staticCallClasses($call),
            $call->var instanceof Variable && $call->var->name === 'this' => [$this->scope->thisMemberClass($method, true)->getName()],
            default => resolve(ReceiverClassResolver::class)->resolve($call->var, $this->scope)?->classes,
        };
        $declaring = array_values(array_filter(
            $classes ?? [],
            fn (string $class): bool => method_exists($class, $method) && DeclaredClasses::returnType($class, $method) !== null,
        ));

        if ($declaring === []) {
            return $this->macroDeclaresClass($classes ?? [], $method);
        }

        return array_all($declaring, fn (string $class): bool => DeclaredClasses::returnsOwnClass($class, $method)) ? null : true;
    }

    /**
     * Whether a macro a class answers the call with declares a return, natively or by `@return`, other than `static`,
     * `self` or `$this`, which name the class it runs on.
     *
     * @param  list<string>  $classes
     */
    private function macroDeclaresClass(array $classes, string $method): bool
    {
        $macros = resolve(MacroCallResolver::class);

        return array_any($classes, function (string $class) use ($macros, $method): bool {
            $function = class_exists($class) ? $macros->reflect($macros->macroFor(new ReflectionClass($class), $method)) : null;
            $native = $function === null ? null : DeclaredClasses::reflectedReturnType($function);
            $declared = $native instanceof ReflectionNamedType
                ? $native->getName()
                : resolve(PropertyDocblockTypeReader::class)->extractReturnType((string) $function?->getDocComment());

            return $declared !== null && preg_match('/^\??(?:static|self|\$this)\b/i', ltrim($declared, '\\')) !== 1;
        });
    }

    /**
     * Whether a property read's classes are only the property's declaration: a typed or documented property the class
     * declares, not a model's attribute or relation, which a read names as it is.
     */
    private function declaresProperty(PropertyFetch|NullsafePropertyFetch $fetch): bool
    {
        if (! $fetch->name instanceof Identifier) {
            return false;
        }

        $name = $fetch->name->toString();
        $classes = $fetch->var instanceof Variable && $fetch->var->name === 'this'
            ? [$this->scope->thisMemberClass($name, false)->getName()]
            : resolve(ReceiverClassResolver::class)->resolve($fetch->var, $this->scope)?->classes;

        return array_any($classes ?? [], function (string $class) use ($name): bool {
            if (! property_exists($class, $name)) {
                return false;
            }

            $property = new ReflectionProperty($class, $name);

            return $property->hasType() || resolve(PropertyDocblockTypeReader::class)->tag($property, new ReflectionClass($class)) !== null;
        });
    }

    /**
     * The class a static call names, `self`, `static` and `parent` read as the scope binds them; null for a `$var::`.
     *
     * @return list<string>|null
     */
    private function staticCallClasses(StaticCall $call): ?array
    {
        $class = $this->scope->staticCallClass($call)?->getName()
            ?? ($call->class instanceof Name && ! $call->class->isSpecialClassName() ? $call->class->toString() : null);

        return $class === null ? null : [$class];
    }

    /**
     * Whether an argument may hold a closure, or on a value read a first-class callable, whose value hangs on whether
     * the method calls it: Laravel sends `{}` for one held and what it returns for one called; Inertia calls each, so
     * it sends the latter. A spread reads a held first-class callable as the Closure it creates, which is `{}`.
     */
    private function passesUncalledCallable(Expr $argument, bool $valueRead): bool
    {
        return $this->reading()->encodes() && HeldCallables::mayHold($argument, $this->scope, $valueRead);
    }
}
