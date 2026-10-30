<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Ast\Composition;

use AbeTwoThree\LaravelTsPublish\Ast\Analysis\MethodAnalysis;
use AbeTwoThree\LaravelTsPublish\Ast\Values\ValueResult;
use AbeTwoThree\LaravelTsPublish\Dtos\Contracts\Datable;
use AbeTwoThree\LaravelTsPublish\Facades\JsEmitter;
use AbeTwoThree\LaravelTsPublish\Facades\TsNaming;
use AbeTwoThree\LaravelTsPublish\Facades\TsTypeString;
use AbeTwoThree\LaravelTsPublish\Support\ClassTokenQueue;
use AbeTwoThree\LaravelTsPublish\Support\ComposedClassTypes;
use AbeTwoThree\LaravelTsPublish\Support\ImportMaps;
use AbeTwoThree\LaravelTsPublish\Support\ImportNameRegistry;
use AbeTwoThree\LaravelTsPublish\Support\TolkiTypes;
use AbeTwoThree\LaravelTsPublish\Transformers\Concerns\BuildsImportMaps;
use AbeTwoThree\LaravelTsPublish\Transformers\Concerns\ResolvesImportConflicts;
use Closure;

/**
 * Names the imports of a file that prints several types no one analysis holds, such as a route file's page types or the
 * shared data, so two classes of one name alias apart across all of them, as an API resource's imports do.
 *
 * Each type comes with the class behind each of its tokens that spells a class name, in token order. A whole printed
 * type also spells names no class of its own stands behind, a cast's or a composed type's, so such a token queues null.
 *
 * A key's token its own classes do not claim takes the one class of its name the analysis holds anywhere, unless a
 * custom import brings the name: an import-less cast that spells a class its key's value embedded means that class.
 *
 * @phpstan-import-type TypesImportMap from Datable
 *
 * @phpstan-type TokenQueue = list<class-string|null>
 * @phpstan-type TokenQueues = array{classes: TokenQueue, consts: TokenQueue}
 * @phpstan-type FileMember = array{type: string, classes: TokenQueue, consts: TokenQueue}
 * @phpstan-type ComposedFile = array{types: list<string>, typeImports: TypesImportMap, valueImports: TypesImportMap}
 * @phpstan-type Piece = array{text: string, typed: bool, classes: list<class-string>, consts: list<class-string>}
 * @phpstan-type SoleClasses = array<string, class-string>
 *
 * @internal
 */
final class FileImportNames
{
    use BuildsImportMaps;
    use ResolvesImportConflicts;

    /** AnalysisComposer's, so a file names a same-named class as an API resource names it. */
    private const SKIP_SEGMENTS = ['Models', 'Enums', 'Http', 'Resources', 'App'];

    /** @var array<string, string> FQCN => TypeScript const name; formatConstImportName() reads it. */
    protected array $enumConstMap = [];

    /** @var list<string> each member's type, its tokens respelled under the names the file gives them */
    private array $types = [];

    /**
     * @param  list<FileMember>  $members
     */
    private function __construct(protected string $namespacePath, private readonly array $members) {}

    /**
     * Name every class the members spell under one name each across the file, and respell the members with them.
     *
     * @param  list<FileMember>  $members  every type the file prints, each with the class behind each class token and
     *                                     the enum behind each `typeof` token, in token order
     * @param  TypesImportMap  $customImports  the names the file's custom imports bring, reserved before any alias
     * @return ComposedFile each member's type with its aliases, in input order, and the imports they spell
     */
    public static function compose(array $members, array $customImports, string $fromNamespacePath): array
    {
        return new self($fromNamespacePath, $members)->composed($customImports);
    }

    /**
     * The token queues of an object type printed from one analysis's keys, each key's type laid beside the classes its
     * import channels hold; the keys and the text before the object queue null for a class name they spell.
     *
     * @param  array<array-key, string>  $printed  key => the type printed for it, in print order
     * @param  list<string>  $brought  the names the file's custom imports bring, which no class of the analysis spells
     * @return TokenQueues
     */
    public static function objectQueues(MethodAnalysis $analysis, array $printed, string $before = '', array $brought = []): array
    {
        $untyped = static fn (string $text): array => ['text' => $text, 'typed' => false, 'classes' => [], 'consts' => []];
        $pieces = $before === '' ? [] : [$untyped($before)];

        foreach ($printed as $key => $type) {
            $pieces[] = $untyped(JsEmitter::validJsObjectKey((string) $key, allowIndexSignature: true));
            $pieces[] = [
                'text' => $type,
                'typed' => true,
                'classes' => $analysis->tokenFqcns((string) $key),
                'consts' => $analysis->wrappedEnumFqcns((string) $key),
            ];
        }

        $classes = self::soleClasses('classes', $analysis->allTokenFqcns(), $brought);
        $consts = self::soleClasses('consts', $analysis->allWrappedEnumFqcns(), $brought);

        return ['classes' => self::queue($pieces, 'classes', $classes), 'consts' => self::queue($pieces, 'consts', $consts)];
    }

    /**
     * Respell each member's class tokens under the names the registries gave their classes.
     */
    protected function rewriteTypeReferences(): void
    {
        $this->types = array_map(fn (array $member): string => self::respell(
            $member['type'],
            $member['classes'],
            'classes',
            fn (string $fqcn, string $spelled): string => $spelled === self::typeName($fqcn)
                ? $this->localImportName($fqcn, $spelled)
                : $this->siblingImportNames[$fqcn][$spelled] ?? $spelled,
        ), $this->members);
    }

    /**
     * The name each class's token is spelled with: a `@tolki/types` class's, an enum's type or a resource's or model's.
     */
    private static function typeName(string $fqcn): string
    {
        return TolkiTypes::MAP[$fqcn]
            ?? (enum_exists($fqcn) ? ValueResult::enumTypeName($fqcn) : TsNaming::resourceTypeName($fqcn));
    }

    /**
     * Every name the classes' tokens on a channel are spelled with: each class's type name and a backed enum's `…Kind`,
     * or each enum's const.
     *
     * @param  'classes'|'consts'  $channel
     * @param  list<class-string>  $fqcns
     * @return list<string>
     */
    private static function namesOf(string $channel, array $fqcns): array
    {
        $names = $channel === 'classes'
            ? array_merge([], ...array_map(
                static fn (string $fqcn): array => [self::typeName($fqcn), ...ValueResult::enumSiblingNames($fqcn)],
                $fqcns,
            ))
            : array_map(ValueResult::enumConstName(...), $fqcns);

        return array_values(array_unique($names));
    }

    /**
     * The pattern a channel's tokens are read with: a const only after `typeof`.
     *
     * @param  'classes'|'consts'  $channel
     * @param  non-empty-list<string>  $names
     */
    private static function patternOf(string $channel, array $names): string
    {
        return TsTypeString::queuedTokenPattern($names, $channel === 'classes' ? '' : 'typeof\s+\K');
    }

    /**
     * Each name the classes on a channel are spelled with that one class alone holds and no custom import brings.
     *
     * @param  'classes'|'consts'  $channel
     * @param  list<class-string>  $fqcns
     * @param  list<string>  $brought
     * @return SoleClasses
     */
    private static function soleClasses(string $channel, array $fqcns, array $brought): array
    {
        $byName = [];

        foreach ($fqcns as $fqcn) {
            foreach (self::namesOf($channel, [$fqcn]) as $name) {
                $byName[$name][] = $fqcn;
            }
        }

        $sole = array_filter($byName, static fn (array $named): bool => count($named) === 1);

        return array_diff_key(array_map(static fn (array $named): string => $named[0], $sole), array_flip($brought));
    }

    /**
     * The class behind each token of the pieces that spells a name some piece's classes claim, or null where none does.
     *
     * The names come from the claimed tokens alone, as respell() reads them back from the queue's classes.
     *
     * @param  list<Piece>  $pieces
     * @param  'classes'|'consts'  $channel
     * @param  SoleClasses  $sole  the class a typed piece's token takes when the piece's own classes lack its name
     * @return TokenQueue
     */
    private static function queue(array $pieces, string $channel, array $sole): array
    {
        $claimed = [];

        foreach ($pieces as $piece) {
            $fallback = $piece['typed'] ? $sole : [];
            $names = array_values(array_unique([...self::namesOf($channel, $piece[$channel]), ...array_keys($fallback)]));
            $claimed = [...$claimed, ...self::walk($piece['text'], $channel, $piece[$channel], $names, $fallback)];
        }

        $names = self::namesOf($channel, array_values(array_filter($claimed)));
        $queue = [];

        foreach ($pieces as $piece) {
            $fallback = $piece['typed'] ? $sole : [];
            $queue = [...$queue, ...self::walk($piece['text'], $channel, $piece[$channel], $names, $fallback)];
        }

        return $queue;
    }

    /**
     * The class behind each token of the names in the text: the piece's own class of its name, else the fallback's,
     * else null. No class the package publishes is generic, so a token a `<` follows is never the fallback's.
     *
     * @param  'classes'|'consts'  $channel
     * @param  list<class-string>  $fqcns
     * @param  list<string>  $names
     * @param  SoleClasses  $fallback
     * @return TokenQueue
     */
    private static function walk(string $text, string $channel, array $fqcns, array $names, array $fallback): array
    {
        if ($names === [] || preg_match_all(self::patternOf($channel, $names), $text, $matches, PREG_OFFSET_CAPTURE) === 0) {
            return [];
        }

        $own = $channel === 'classes'
            ? new ClassTokenQueue($fqcns, self::typeName(...), ValueResult::enumSiblingNames(...))
            : new ClassTokenQueue($fqcns, ValueResult::enumConstName(...));

        return array_map(static function (array $match) use ($own, $fallback, $text): ?string {
            [$token, $at] = $match;
            $generic = substr($text, $at + strlen($token), 1) === '<';

            return $own->take($token)[0] ?? ($generic ? null : $fallback[$token] ?? null);
        }, $matches[0]);
    }

    /**
     * The type with each token the queue names respelled by `$localName`, one entry per token in order; a null entry,
     * or a class whose names lack the token, leaves the token as written.
     *
     * A per-name queue, as aliasPropertyType() reads, cannot skip a token no class stands behind: its occurrence would
     * take the next class of its name.
     *
     * @param  TokenQueue  $queue
     * @param  'classes'|'consts'  $channel
     * @param  Closure(class-string, string): string  $localName
     */
    private static function respell(string $type, array $queue, string $channel, Closure $localName): string
    {
        $names = self::namesOf($channel, array_values(array_filter($queue)));

        if ($names === []) {
            return $type;
        }

        $at = 0;

        return preg_replace_callback(
            self::patternOf($channel, $names),
            static function (array $match) use ($queue, $channel, $localName, &$at): string {
                $fqcn = $queue[$at++] ?? null;

                return $fqcn !== null && in_array($match[0], self::namesOf($channel, [$fqcn]), true)
                    ? $localName($fqcn, $match[0])
                    : $match[0];
            },
            $type,
        ) ?? $type;
    }

    /**
     * Resolve every name, respell the members, and build the imports their spellings need.
     *
     * @param  TypesImportMap  $customImports
     * @return ComposedFile
     */
    private function composed(array $customImports): array
    {
        $this->types = array_column($this->members, 'type');
        $classes = $this->queued('classes');
        $consts = $this->queued('consts');

        $enumTypeNames = [];
        $typeNames = [];

        foreach ($classes as $fqcn) {
            if (isset(TolkiTypes::MAP[$fqcn])) {
                $customImports[TolkiTypes::MODULE][] = TolkiTypes::MAP[$fqcn];
            } elseif (enum_exists($fqcn)) {
                $enumTypeNames[$fqcn] = ValueResult::enumTypeName($fqcn);
            } else {
                $typeNames[$fqcn] = TsNaming::resourceTypeName($fqcn);
            }
        }

        foreach ([...array_keys($enumTypeNames), ...$consts] as $enum) {
            $this->enumConstMap[$enum] ??= ValueResult::enumConstName($enum);
        }

        $this->resolveImportNames($enumTypeNames, $typeNames, $customImports);

        $this->types = array_map(fn (string $type, array $member): string => self::respell(
            $type,
            $member['consts'],
            'consts',
            fn (string $enum, string $spelled): string => $this->constImportAliases[$enum] ?? $spelled,
        ), $this->types, $this->members);

        $typeImports = $this->collectModularTypeImports($enumTypeNames + $typeNames, $this->types);

        return [
            'types' => $this->types,
            'typeImports' => $this->deduplicateAndSortImports(
                $this->spelledOnly(ComposedClassTypes::resolveImports($customImports, $this->namespacePath, $typeImports)),
            ),
            'valueImports' => $this->deduplicateAndSortImports($this->collectModularValueImports($consts)),
        ];
    }

    /**
     * Run the type registry and its sibling const registry over the members' classes, as AnalysisComposer runs them.
     *
     * @param  array<class-string, string>  $enumTypeNames
     * @param  array<class-string, string>  $typeNames
     * @param  TypesImportMap  $customImports
     */
    private function resolveImportNames(array $enumTypeNames, array $typeNames, array $customImports): void
    {
        $types = new ImportNameRegistry(self::SKIP_SEGMENTS);
        $consts = new ImportNameRegistry(self::SKIP_SEGMENTS);

        foreach ($enumTypeNames + $typeNames as $fqcn => $name) {
            $types->register($fqcn, $name);
        }

        foreach ($this->enumConstMap as $fqcn => $name) {
            $consts->register($fqcn, $name);
        }

        $this->registerEnumKinds(
            $types,
            $enumTypeNames,
            array_map(static fn (array $member): array => ['type' => $member['type']], $this->members),
            array_map(static fn (array $member): array => array_values(array_filter($member['classes'])), $this->members),
        );

        $this->applyImportNameRegistries(
            $types,
            $consts,
            $enumTypeNames + $typeNames,
            array_map(static fn (array $names): array => array_map(ImportMaps::localName(...), $names), $customImports),
        );
    }

    /**
     * Every class the members queue on one channel, once each, in first-seen order.
     *
     * @param  'classes'|'consts'  $channel
     * @return list<class-string>
     */
    private function queued(string $channel): array
    {
        return array_values(array_unique(array_filter(array_merge([], ...array_column($this->members, $channel)))));
    }

    /**
     * Drop each imported name no member spells, and any path left empty, so the file carries no dead import.
     *
     * @param  TypesImportMap  $imports
     * @return TypesImportMap
     */
    private function spelledOnly(array $imports): array
    {
        foreach ($imports as $path => $names) {
            $kept = array_values(array_filter(
                $names,
                fn (string $name): bool => TsTypeString::typeNameOccursIn(ImportMaps::localName($name), ...$this->types),
            ));

            if ($kept === []) {
                unset($imports[$path]);
            } else {
                $imports[$path] = $kept;
            }
        }

        return $imports;
    }
}
