<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Ast;

use AbeTwoThree\LaravelTsPublish\Ast\Contracts\ExpressionHandler;
use AbeTwoThree\LaravelTsPublish\Facades\LaravelTsPublish;
use ReflectionClass;
use ReflectionProperty;

/**
 * Resolves a `@var` docblock type, a property's or a local variable's, to a TypeScript type plus its FQCN channels.
 *
 * @phpstan-import-type ValueExpressionResult from ExpressionHandler
 * @phpstan-import-type TypeScriptTypeInfo from \AbeTwoThree\LaravelTsPublish\LaravelTsPublish
 *
 * @phpstan-type VarTag = array{string, string|null}
 * @phpstan-type CapturedTag = array{string, string, bool}
 *
 * @internal
 */
final class PropertyDocblockTypeReader
{
    /**
     * Read a property's `@var` type, or null when it has none the type system can use.
     *
     * A `@var` whose tokens cannot be imported also reads null, so the caller can still fall back
     * to the native declaration; see ReflectedTypeAcceptor::accept().
     *
     * @return ValueExpressionResult|null
     */
    public function read(ReflectionProperty $property): ?array
    {
        $docComment = $property->getDocComment();

        if ($docComment === false) {
            return null;
        }

        $declared = $this->extractVarType($docComment);

        if ($declared === null || $declared === '') {
            return null;
        }

        return $this->readDeclared($declared, $property->getDeclaringClass());
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
     * Capture the type expression following `@var`, stopping at the first separator that ends it.
     *
     * Whitespace inside `{}`/`<>`/`()` or around a union operator belongs to the type; any other
     * whitespace starts the `$name` or the prose description. Public so ReceiverClassResolver reads the same full type.
     */
    public function extractVarType(string $docComment): ?string
    {
        return $this->captureTag($docComment, '/(?<![\w-])@var\s+/')[0] ?? null;
    }

    /**
     * Capture an inline `@var` on a local assignment: its type, and the variable it names, null when it names none.
     * Null for a type the docblock resolution cannot read in full: one that does not read to its end, or holds a
     * callable or closure signature, a parenthesized group or an intersection.
     *
     * @return VarTag|null
     */
    public function extractVarTag(string $docComment): ?array
    {
        $tag = $this->captureTag($docComment, '/(?<![\w-])@var\s+/');

        if ($tag === null || $tag[0] === '' || ! $tag[2] || strpbrk($tag[0], '(&') !== false) {
            return null;
        }

        return [$tag[0], preg_match('/^\s*\$([a-zA-Z_\x80-\xff][\w\x80-\xff]*)/', $tag[1], $match) === 1 ? $match[1] : null];
    }

    /**
     * Capture the full type after a line-leading `@return`, `@phpstan-return` or `@psalm-return`, tried in that order.
     *
     * Unlike LaravelTsPublish::extractReturnTypeFromDocblock(), text after a generic's closing `>`, such as `[]`, stays
     * part of the type, so ReceiverClassResolver refuses an array of a class instead of naming the class.
     */
    public function extractReturnType(string $docComment): ?string
    {
        foreach (['@return', '@phpstan-return', '@psalm-return'] as $tag) {
            $type = $this->captureTag($docComment, '/^\s*(?<![\w-])'.preg_quote($tag, '/').'\s+/m')[0] ?? null;

            if ($type !== null && $type !== '') {
                return $type;
            }
        }

        return null;
    }

    /**
     * Capture the type expression after the first match of a tag pattern, stopping at the separator that ends it, with
     * the text that follows it and whether the type read to its end: brackets closed, and no `:` left dangling.
     *
     * @return CapturedTag|null
     */
    private function captureTag(string $docComment, string $tagPattern): ?array
    {
        $content = trim((string) preg_replace(['#^[ \t]*/?\*+/?#m', '#\*+/\s*$#'], '', $docComment));

        if (! preg_match($tagPattern, $content, $match, PREG_OFFSET_CAPTURE)) {
            return null;
        }

        $rest = ltrim(substr($content, (int) $match[0][1] + strlen((string) $match[0][0])));
        $type = '';
        $depth = 0;
        $length = strlen($rest);
        $end = $length;

        for ($i = 0; $i < $length; $i++) {
            $char = $rest[$i];
            $isSpace = ctype_space($char);

            if ($isSpace && $depth === 0 && ! $this->spaceContinuesType($type, substr($rest, $i + 1))) {
                $end = $i;

                break;
            }

            $depth += (int) in_array($char, ['{', '<', '('], true) - (int) in_array($char, ['}', '>', ')'], true);
            $type .= $isSpace ? ' ' : $char;

            // A quoted or unmatched closer drives depth below zero, where the depth-zero space test
            // can never fire again — without this the walk swallows the `$name` and the prose.
            if ($depth < 0) {
                $end = $i + 1;

                break;
            }
        }

        $type = trim($type);

        return [$type, substr($rest, $end), $depth === 0 && ! str_ends_with($type, ':')];
    }

    /**
     * Whether a depth-zero space is inside the type rather than after it.
     */
    private function spaceContinuesType(string $captured, string $remaining): bool
    {
        $captured = rtrim($captured);
        $remaining = ltrim($remaining);

        return str_ends_with($captured, '|') || str_ends_with($captured, '&')
            || str_starts_with($remaining, '|') || str_starts_with($remaining, '&');
    }

    /**
     * Resolve a PHPDoc type string against a class's use-map and namespace.
     *
     * @param  ReflectionClass<object>  $context
     * @return TypeScriptTypeInfo
     */
    private function resolveInfo(ReflectionClass $context, string $declared): array
    {
        $useMap = LaravelTsPublish::parseFileUseStatements($context);
        $namespace = $context->getNamespaceName();

        $infos = [];

        // Union fan-out duplicates LaravelTsPublish::resolveDocblockPartToInfo(), which is protected.
        foreach (LaravelTsPublish::splitPhpDocUnionType($declared) as $part) {
            $part = trim($part);

            if ($part === '') {
                continue;
            }

            $infos[] = LaravelTsPublish::resolveDocblockTypePart($part, $useMap, $namespace);
        }

        if ($infos === []) {
            return LaravelTsPublish::emptyTypeScriptInfo(); // @codeCoverageIgnore
        }

        return count($infos) === 1 ? $infos[0] : LaravelTsPublish::mergeTypeScriptInfos($infos);
    }
}
