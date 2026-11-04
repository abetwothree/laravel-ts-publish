<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Ast\Docblocks;

use AbeTwoThree\LaravelTsPublish\Ast\Contracts\ExpressionHandler;
use AbeTwoThree\LaravelTsPublish\Ast\Values\ReflectedTypeAcceptor;
use AbeTwoThree\LaravelTsPublish\Facades\LaravelTsPublish;
use AbeTwoThree\LaravelTsPublish\Support\TypeStrings\ScanDialect;
use AbeTwoThree\LaravelTsPublish\Support\TypeStrings\TypeScanner;
use ReflectionClass;
use ReflectionProperty;

/**
 * Resolves a property's docblock type, or a local variable's `@var`, to a TypeScript type plus its FQCN channels.
 *
 * @phpstan-import-type ValueExpressionResult from ExpressionHandler
 * @phpstan-import-type TypeScriptTypeInfo from \AbeTwoThree\LaravelTsPublish\LaravelTsPublish
 *
 * @phpstan-type VarTag = array{string, string|null, bool}
 * @phpstan-type RelativeClasses = array<string, class-string>
 * @phpstan-type PropertyTag = array{type: string, context: ReflectionClass<object>}
 *
 * @internal
 */
final class PropertyDocblockTypeReader
{
    /**
     * Read a property's docblock type, as tag() finds it, or null when it has none the type system can use.
     *
     * A type whose tokens cannot be imported also reads null, so the caller can still fall back
     * to the native declaration; see ReflectedTypeAcceptor::accept().
     *
     * @return ValueExpressionResult|null
     */
    public function read(ReflectionProperty $property): ?array
    {
        $tag = $this->tag($property);

        return $tag === null ? null : $this->readDeclared($tag['type'], $tag['context']);
    }

    /**
     * The type a property's docblock gives it, with the class or trait whose file wrote it: its own `@var`, else, for a
     * promoted property, its constructor's `@param`, as PHPStan reads them. Every reader of a property's type uses it.
     *
     * @return PropertyTag|null
     */
    public function tag(ReflectionProperty $property): ?array
    {
        $docComment = $property->getDocComment();
        $type = $docComment === false ? null : DocblockTags::type($docComment, 'var');

        if ($type === null && ! $property->isPromoted()) {
            return null;
        }

        $context = DeclaringFile::ofProperty($property);

        // Reflection gives a promoted property its parameter's own docblock, never the constructor's.
        if ($type === null) {
            $constructor = (string) $context->getConstructor()?->getDocComment();
            $type = DocblockTags::named($constructor, 'param')[$property->getName()] ?? null;
        }

        return $type === null ? null : ['type' => $type, 'context' => $context];
    }

    /**
     * Read a `@var` type written in a class's file, or null when it has none the type system can use.
     *
     * @param  ReflectionClass<object>  $context  the class or trait whose file's imports and namespace resolve it
     * @return ValueExpressionResult|null
     */
    public function readDeclared(string $declared, ReflectionClass $context): ?array
    {
        return resolve(ReflectedTypeAcceptor::class)->accept($this->resolveInfo($context, $declared));
    }

    /**
     * Capture an inline `@phpstan-var`, `@psalm-var` or `@var` on a local assignment: its type in PHPStan's order, the
     * variable the first tag naming one names, null when none does, and whether it spells a `self`, `static` or
     * `parent`. Null unless the type is a VarTypeWhitelist form that resolves with no `unknown` part but callables'.
     *
     * Each `self`, `static` or `parent` in the type comes back as the class $relative maps it to, the one the body
     * holding the tag binds it to; one the map lacks stays as written, which no reading accepts.
     *
     * @param  ReflectionClass<object>  $context  the class or trait whose file's imports and namespace resolve it
     * @param  RelativeClasses  $relative  the class each of `self`, `static` and `parent` names, keyed by that name
     * @return VarTag|null
     */
    public function extractVarTag(string $docComment, ReflectionClass $context, array $relative): ?array
    {
        $declared = DocblockTags::type($docComment, 'var');

        if ($declared === null) {
            return null;
        }

        $type = $this->spellRelativeClasses($declared, $relative);

        // A tag no reading accepts, such as an interface's, still names the receiver its local holds.
        if (! $this->readsInFull($type, $context, unreadAccepted: true)) {
            return null;
        }

        return [$type, array_key_first(DocblockTags::named($docComment, 'var')), $type !== $declared];
    }

    /**
     * The type a method's `@phpstan-param`, `@psalm-param` or `@param` tag gives one parameter, as DocblockTags reads
     * it. Null unless the type is a VarTypeWhitelist form that resolves with no `unknown` part but its callables'.
     *
     * @param  ReflectionClass<object>  $context  the class or trait whose file's imports and namespace resolve it
     */
    public function extractParamTag(string $docComment, string $name, ReflectionClass $context): ?string
    {
        $type = DocblockTags::named($docComment, 'param')[$name] ?? null;

        return $type !== null && $this->readsInFull($type, $context, unreadAccepted: false) ? $type : null;
    }

    /**
     * The whole type a docblock's `@phpstan-return`, `@psalm-return` or `@return` tag spells, as DocblockTags reads it.
     *
     * Text after a generic's closing `>`, such as `[]`, stays part of the type, so ReceiverClassResolver refuses an
     * array of a class instead of naming the class. LaravelTsPublish::extractReturnTypeFromDocblock() reads through it.
     */
    public function extractReturnType(string $docComment): ?string
    {
        return DocblockTags::type($docComment, 'return');
    }

    /**
     * A type with each `self`, `static` and `parent` it names spelled as the fully qualified class $relative maps it
     * to; a string literal the type scanner reads, a shape's key and a `self::` constant keep the word, as does a
     * conditional type's `? self :` arm, so the tag declines as it did before.
     *
     * @param  RelativeClasses  $relative
     */
    private function spellRelativeClasses(string $type, array $relative): string
    {
        if ($relative === []) {
            return $type;
        }

        $spans = TypeScanner::literalSpans($type, ScanDialect::PhpDoc);
        $inLiteral = static fn (int $at): bool => array_any(
            $spans,
            static fn (array $span): bool => $at >= $span[0] && $at < $span[1],
        );

        return preg_replace_callback(
            '/(?<![\w\x80-\xff\\\\$-])(?:self|static|parent)(?![\w\x80-\xff\\\\-]|\s*\??\s*:)/i',
            fn (array $word): string => ! $inLiteral($word[0][1]) && isset($relative[strtolower($word[0][0])])
                ? '\\'.$relative[strtolower($word[0][0])]
                : $word[0][0],
            $type,
            flags: PREG_OFFSET_CAPTURE,
        ) ?? $type;
    }

    /**
     * Whether a tag's type is a VarTypeWhitelist form whose reading holds an `unknown` only where a callable stands.
     *
     * @param  ReflectionClass<object>  $context
     * @param  bool  $unreadAccepted  the answer when the reading is null, as for a type that only names a receiver
     */
    private function readsInFull(string $type, ReflectionClass $context, bool $unreadAccepted): bool
    {
        $leaves = VarTypeWhitelist::for($context)->unknownLeaves($type);

        if ($leaves === null) {
            return false;
        }

        $read = $this->readDeclared($type, $context);

        return $read === null ? $unreadAccepted : preg_match_all('/\bunknown\b/', $read['type']) === $leaves;
    }

    /**
     * Resolve a PHPDoc type string against a class's use-map, namespace and type aliases.
     *
     * @param  ReflectionClass<object>  $context
     * @return TypeScriptTypeInfo
     */
    private function resolveInfo(ReflectionClass $context, string $declared): array
    {
        $useMap = LaravelTsPublish::parseFileUseStatements($context);
        $namespace = $context->getNamespaceName();

        $infos = [];

        // Union fan-out duplicates LaravelTsPublish::resolveDocblockTypeStringAgainst(), which is protected.
        foreach (LaravelTsPublish::splitPhpDocUnionType($declared) as $part) {
            $part = trim($part);

            if ($part === '') {
                continue;
            }

            $infos[] = LaravelTsPublish::resolveDocblockTypePartOrAlias($part, $useMap, $namespace, $context);
        }

        if ($infos === []) {
            return LaravelTsPublish::emptyTypeScriptInfo(); // @codeCoverageIgnore
        }

        return count($infos) === 1 ? $infos[0] : LaravelTsPublish::mergeTypeScriptInfos($infos);
    }
}
