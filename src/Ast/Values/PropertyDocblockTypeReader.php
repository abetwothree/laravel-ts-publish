<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Ast\Values;

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
 * @phpstan-type CapturedTag = array{string, string, int}
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
     * Null unless the type is a VarTypeWhitelist form that resolves with no `unknown` part but its callables'.
     *
     * @param  ReflectionClass<object>  $context  the class or trait whose file's imports and namespace resolve it
     * @return VarTag|null
     */
    public function extractVarTag(string $docComment, ReflectionClass $context): ?array
    {
        $tag = $this->captureTag($docComment, '/(?<![\w-])@var\s+/');

        // A tag no reading accepts, such as an interface's, still names the receiver its local holds.
        if ($tag === null || ! $this->readsInFull($tag[0], $context, unreadAccepted: true)) {
            return null;
        }

        return [$tag[0], preg_match('/^\s*\$([a-zA-Z_\x80-\xff][\w\x80-\xff]*)/', $tag[1], $match) === 1 ? $match[1] : null];
    }

    /**
     * Capture the type a method's `@phpstan-param`, `@psalm-param` or `@param` tag gives one parameter, tried in that
     * order. Null unless the type is a VarTypeWhitelist form that resolves with no `unknown` part but its callables'.
     *
     * @param  ReflectionClass<object>  $context  the class or trait whose file's imports and namespace resolve it
     */
    public function extractParamTag(string $docComment, string $name, ReflectionClass $context): ?string
    {
        foreach (['@phpstan-param', '@psalm-param', '@param'] as $tagName) {
            $offset = 0;
            $pattern = '/(?<![\w-])'.preg_quote($tagName, '/').'\s+/';

            while (($tag = $this->captureTag($docComment, $pattern, $offset)) !== null) {
                [$type, $rest, $offset] = $tag;

                if (preg_match('/^\s*&?\s*(?:\.\.\.)?\$'.preg_quote($name, '/').'(?![\w\x80-\xff])/', $rest) !== 1) {
                    continue;
                }

                return $this->readsInFull($type, $context, unreadAccepted: false) ? $type : null;
            }
        }

        return null;
    }

    /**
     * Capture the full type after a line-leading `@return`, `@phpstan-return` or `@psalm-return`, tried in that order.
     *
     * Text after a generic's closing `>`, such as `[]`, stays part of the type, so ReceiverClassResolver refuses an
     * array of a class instead of naming the class. LaravelTsPublish::extractReturnTypeFromDocblock() reads through it.
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
     * Capture the type expression after the first match of a tag pattern at or past an offset, stopping at the
     * separator that ends it, with the text that follows it and the offset a next search starts from.
     *
     * @return CapturedTag|null
     */
    private function captureTag(string $docComment, string $tagPattern, int $offset = 0): ?array
    {
        $content = trim((string) preg_replace(['#^[ \t]*/?\*+/?#m', '#\*+/\s*$#'], '', $docComment));

        if (! preg_match($tagPattern, $content, $match, PREG_OFFSET_CAPTURE, $offset)) {
            return null;
        }

        $next = (int) $match[0][1] + strlen((string) $match[0][0]);
        $rest = ltrim(substr($content, $next));
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

        return [$type, substr($rest, $end), $next];
    }

    /**
     * Whether a depth-zero space is inside the type rather than after it.
     */
    private function spaceContinuesType(string $captured, string $remaining): bool
    {
        $captured = rtrim($captured);
        $remaining = ltrim($remaining);

        // A `&` before a parameter name marks a by-reference `@param`, not an intersection. A `:` after a `)` opens a
        // callable's return type.
        return str_ends_with($captured, '|') || str_ends_with($captured, '&')
            || str_starts_with($remaining, '|')
            || (str_starts_with($remaining, '&') && preg_match('/^&\s*(?:\.\.\.)?\$/', $remaining) !== 1)
            || preg_match('/\)\s*:$/', $captured) === 1
            || (str_ends_with($captured, ')') && str_starts_with($remaining, ':'));
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
