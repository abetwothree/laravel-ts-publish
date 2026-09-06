<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Ast;

use AbeTwoThree\LaravelTsPublish\Ast\Contracts\ExpressionHandler;
use AbeTwoThree\LaravelTsPublish\Facades\LaravelTsPublish;
use ReflectionProperty;

/**
 * Resolves a property's `@var` docblock type to a TypeScript type plus its FQCN channels.
 *
 * @phpstan-import-type ValueExpressionResult from ExpressionHandler
 * @phpstan-import-type TypeScriptTypeInfo from \AbeTwoThree\LaravelTsPublish\LaravelTsPublish
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

        return resolve(ReflectedTypeAcceptor::class)->accept($this->resolveInfo($property, $declared));
    }

    /**
     * Capture the type expression following `@var`, stopping at the first separator that ends it.
     *
     * Whitespace inside `{}`/`<>`/`()` or around a union operator belongs to the type; any other
     * whitespace starts the `$name` or the prose description.
     */
    private function extractVarType(string $docComment): ?string
    {
        $content = trim((string) preg_replace(['#^[ \t]*/?\*+/?#m', '#\*+/\s*$#'], '', $docComment));

        if (! preg_match('/(?<![\w-])@var\s+/', $content, $match, PREG_OFFSET_CAPTURE)) {
            return null;
        }

        $rest = ltrim(substr($content, (int) $match[0][1] + strlen((string) $match[0][0])));
        $type = '';
        $depth = 0;

        for ($i = 0, $length = strlen($rest); $i < $length; $i++) {
            $char = $rest[$i];
            $isSpace = ctype_space($char);

            if ($isSpace && $depth === 0 && ! $this->spaceContinuesType($type, substr($rest, $i + 1))) {
                break;
            }

            $depth += (int) in_array($char, ['{', '<', '('], true) - (int) in_array($char, ['}', '>', ')'], true);
            $type .= $isSpace ? ' ' : $char;

            // A quoted or unmatched closer drives depth below zero, where the depth-zero space test
            // can never fire again — without this the walk swallows the `$name` and the prose.
            if ($depth < 0) {
                break;
            }
        }

        return trim($type);
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
     * Resolve a PHPDoc type string against the declaring class's use-map and namespace.
     *
     * @return TypeScriptTypeInfo
     */
    private function resolveInfo(ReflectionProperty $property, string $declared): array
    {
        $declaringClass = $property->getDeclaringClass();
        $useMap = LaravelTsPublish::parseFileUseStatements($declaringClass);
        $namespace = $declaringClass->getNamespaceName();

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
