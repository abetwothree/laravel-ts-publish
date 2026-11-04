<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Ast\Composition;

use AbeTwoThree\LaravelTsPublish\Ast\Analysis\MethodAnalysis;
use AbeTwoThree\LaravelTsPublish\Ast\Values\ValueResult;
use AbeTwoThree\LaravelTsPublish\Dtos\Contracts\Datable;
use AbeTwoThree\LaravelTsPublish\Facades\TsNaming;
use AbeTwoThree\LaravelTsPublish\Facades\TsTypeString;
use AbeTwoThree\LaravelTsPublish\Support\ClassTokenQueue;
use AbeTwoThree\LaravelTsPublish\Support\ComposedClassTypes;
use AbeTwoThree\LaravelTsPublish\Support\ImportMaps;
use AbeTwoThree\LaravelTsPublish\Support\ImportNameRegistry;
use AbeTwoThree\LaravelTsPublish\Support\TolkiTypes;
use AbeTwoThree\LaravelTsPublish\Support\TsTypeString as TsTypeStringService;
use AbeTwoThree\LaravelTsPublish\Transformers\Concerns\BuildsImportMaps;
use AbeTwoThree\LaravelTsPublish\Transformers\Concerns\ResolvesImportConflicts;
use Closure;

/**
 * Names one file's imports across every type it prints, a route file's pages or the shared data, as a resource's.
 *
 * @phpstan-import-type TypesImportMap from Datable
 * @phpstan-import-type TokenFqcns from MethodAnalysis
 *
 * @phpstan-type TokenQueue = list<class-string|null>
 * @phpstan-type TokenQueues = array{classes: TokenQueue, consts: TokenQueue}
 * @phpstan-type PageQueues = array{classes: list<TokenQueue>, consts: list<TokenQueue>}
 * @phpstan-type FileMember = array{type: string, classes: TokenQueue, consts: TokenQueue}
 * @phpstan-type MemberPiece = array{text: string, source: array-key|null}
 * @phpstan-type PrintedMember = array{key: string, type: string, source: array-key, pieces?: list<MemberPiece>}
 * @phpstan-type ComposedFile = array{types: list<string>, typeImports: TypesImportMap, valueImports: TypesImportMap}
 * @phpstan-type Piece = array{
 *     text: string,
 *     typed: bool,
 *     classes: list<class-string>,
 *     consts: list<class-string>,
 *     declared?: TokenFqcns,
 * }
 * @phpstan-type SoleClasses = array<string, class-string>
 * @phpstan-type Token = array{string, int, bool}
 * @phpstan-type DeclaredToken = array{token: string, offset: int, slot: class-string|null, own: bool, read: bool}
 * @phpstan-type DeclaredArm = array{string, TokenFqcns}
 *
 * @internal
 */
final class FileImportNames
{
    use BuildsImportMaps;
    use ResolvesImportConflicts;

    /** The name a `typeof` reads, which is a const's and never a class channel's type. */
    private const string AFTER_TYPEOF = '/(?<![A-Za-z0-9_$.])typeof\s+\K[A-Za-z_$\x80-\xff][A-Za-z0-9_$\x80-\xff]*/';

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
     * An object type joined from the members its printer printed, as a file member with the queues of that text.
     *
     * @param  list<PrintedMember>  $members  each key as its printer printed it, in print order
     * @param  list<string>  $brought  the names the file's custom imports bring, which no class of the analysis spells
     * @return FileMember
     */
    public static function objectMember(MethodAnalysis $analysis, array $members, string $before = '', array $brought = []): array
    {
        $printed = array_map(static fn (array $member): string => $member['key'].$member['type'], $members);
        $text = $printed === [] ? TsTypeStringService::EMPTY_OBJECT : '{ '.implode(', ', $printed).' }';

        return ['type' => $before.$text, ...self::objectQueues($analysis, $members, $before, $brought)];
    }

    /**
     * The token queues of `$before` and the printed members' text, a key's own text queuing null.
     *
     * @param  list<PrintedMember>  $members  each key as its printer printed it, in print order
     * @param  list<string>  $brought  the names the file's custom imports bring, which no class of the analysis spells
     * @return TokenQueues
     */
    public static function objectQueues(MethodAnalysis $analysis, array $members, string $before = '', array $brought = []): array
    {
        $untyped = static fn (string $text): array => ['text' => $text, 'typed' => false, 'classes' => [], 'consts' => []];
        $pieces = $before === '' ? [] : [$untyped($before)];

        foreach ($members as $member) {
            $pieces[] = $untyped($member['key']);

            // A member several keys print reads each key's own classes in its own text.
            foreach ($member['pieces'] ?? [['text' => $member['type'], 'source' => $member['source']]] as $piece) {
                if ($piece['source'] === null) {
                    $pieces[] = $untyped($piece['text']);

                    continue;
                }

                $source = (string) $piece['source'];
                $declared = $analysis->declaredTokenFqcns[$source] ?? null;
                $pieces[] = [
                    'text' => $piece['text'],
                    'typed' => true,
                    'classes' => $analysis->tokenFqcns($source),
                    'consts' => $analysis->wrappedEnumFqcns($source),
                    ...($declared === null ? [] : ['declared' => $declared]),
                ];
            }
        }

        // A class a cast displaced is carried off every channel, but another key's text may still spell it.
        $held = array_values(array_unique([...$analysis->allTokenFqcns(), ...$analysis->allCarriedFqcns()]));
        $classes = self::soleClasses('classes', $held, $brought);
        $consts = self::soleClasses('consts', $analysis->allWrappedEnumFqcns(), $brought);

        return ['classes' => self::queue($pieces, 'classes', $classes), 'consts' => self::queue($pieces, 'consts', $consts)];
    }

    /**
     * The token queues of a printed type from the classes and enums it spells, each listed once.
     *
     * @param  list<class-string>  $classes
     * @param  list<class-string>  $consts
     * @return TokenQueues
     */
    public static function typeQueues(string $type, array $classes, array $consts): array
    {
        $piece = ['text' => $type, 'typed' => false, 'classes' => $classes, 'consts' => $consts];

        return ['classes' => self::queue([$piece], 'classes', []), 'consts' => self::queue([$piece], 'consts', [])];
    }

    /**
     * The name each class's token is spelled with: a `@tolki/types` class's, an enum's type or a resource's or model's.
     */
    public static function typeName(string $fqcn): string
    {
        return TolkiTypes::MAP[$fqcn]
            ?? (enum_exists($fqcn) ? ValueResult::enumTypeName($fqcn) : TsNaming::resourceTypeName($fqcn));
    }

    /**
     * Every name a channel's classes are spelled with: a type name and a backed enum's `…Kind`, or an enum's const.
     *
     * @param  'classes'|'consts'  $channel
     * @param  list<class-string>  $fqcns
     * @return list<string>
     */
    public static function namesOf(string $channel, array $fqcns): array
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
     * Each token in a type of a declaration's own names, the names of the classes its slots hold, and of `$names`, in
     * print order. An own name's token takes the next slot, an unread one's in a literal or after `typeof` too, and
     * any other token takes none: the one reading of `tokenFqcns` every builder and reader of it shares.
     *
     * @param  TokenFqcns  $slots
     * @param  list<string>  $names
     * @return list<DeclaredToken>
     */
    public static function declaredTokens(string $type, array $slots, array $names = []): array
    {
        $own = self::ownNames($slots);
        $read = array_values(array_unique([...$own, ...$names]));

        if ($read === []) {
            return [];
        }

        $at = 0;
        $tokens = [];

        foreach (self::tokens('classes', $read, $type) as [$token, $offset, $isRead]) {
            $isOwn = in_array($token, $own, true);
            $slot = $isOwn ? $slots[$at++] ?? null : null;
            $tokens[] = ['token' => $token, 'offset' => $offset, 'slot' => $slot, 'own' => $isOwn, 'read' => $isRead];
        }

        return $tokens;
    }

    /**
     * Each top-level arm of a declaration's type, beside the slots its own names' tokens take, in print order.
     *
     * @param  TokenFqcns  $slots
     * @return list<DeclaredArm>
     */
    public static function declaredArms(string $type, array $slots): array
    {
        $own = self::ownNames($slots);
        $arms = [];
        $at = 0;

        foreach (TsTypeString::splitTopLevelUnion($type) as $arm) {
            $count = $own === [] ? 0 : count(self::tokens('classes', $own, $arm));
            $arms[] = [$arm, array_slice($slots, $at, $count)];
            $at += $count;
        }

        return $arms;
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
     * The names a declaration's tokens take slots for: those of the classes its slots hold.
     *
     * @param  TokenFqcns  $slots
     * @return list<string>
     */
    private static function ownNames(array $slots): array
    {
        return self::namesOf('classes', array_values(array_unique(array_filter($slots))));
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
     * The class behind each token of a name the pieces' classes claim, read with the names respell() reads, else null.
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
            $claimed = [...$claimed, ...self::walk($piece, $channel, $names, $fallback)];
        }

        $names = self::namesOf($channel, array_values(array_filter($claimed)));
        $queue = [];

        foreach ($pieces as $piece) {
            $fallback = $piece['typed'] ? $sole : [];
            $queue = [...$queue, ...self::walk($piece, $channel, $names, $fallback)];
        }

        return $queue;
    }

    /**
     * The class behind each token of the names in the text: the piece's own class of its name, else the fallback's.
     *
     * A piece whose text a declaration wrote reads each token of its own classes' names from that declaration in order,
     * since a per-name queue cannot hold the null an override spelled like a model's name needs.
     *
     * @param  Piece  $piece
     * @param  'classes'|'consts'  $channel
     * @param  list<string>  $names
     * @param  SoleClasses  $fallback
     * @return TokenQueue
     */
    private static function walk(array $piece, string $channel, array $names, array $fallback): array
    {
        $queue = [];

        if ($channel === 'classes' && isset($piece['declared'])) {
            foreach (self::declaredTokens($piece['text'], $piece['declared'], $names) as $token) {
                if ($token['read'] && in_array($token['token'], $names, true)) {
                    $queue[] = $token['own'] ? $token['slot'] : $fallback[$token['token']] ?? null;
                }
            }

            return $queue;
        }

        $tokens = $names === [] ? [] : self::tokens($channel, $names, $piece['text']);

        $own = $channel === 'classes'
            ? new ClassTokenQueue($piece[$channel], self::typeName(...), ValueResult::enumSiblingNames(...))
            : new ClassTokenQueue($piece[$channel], ValueResult::enumConstName(...));

        foreach ($tokens as [$token, , $read]) {
            if ($read) {
                $queue[] = $own->take($token)[0] ?? $fallback[$token] ?? null;
            }
        }

        return $queue;
    }

    /**
     * Each token of the names in the text, its offset, and whether the channel reads it.
     *
     * A literal's or an index signature key's text is a value or a key, and the name after `typeof` is a const's.
     *
     * @param  'classes'|'consts'  $channel
     * @param  non-empty-list<string>  $names
     * @return list<Token>
     */
    private static function tokens(string $channel, array $names, string $text): array
    {
        if (preg_match_all(self::patternOf($channel, $names), $text, $matches, PREG_OFFSET_CAPTURE) === 0) {
            return [];
        }

        $unread = TsTypeString::literalSpans($text);

        if ($channel === 'classes' && preg_match_all(self::AFTER_TYPEOF, $text, $consts, PREG_OFFSET_CAPTURE) > 0) {
            foreach ($consts[0] as [$const, $at]) {
                $unread[] = [$at, $at + strlen($const)];
            }
        }

        return array_map(static fn (array $match): array => [
            $match[0],
            $match[1],
            ! array_any($unread, static fn (array $span): bool => $match[1] >= $span[0] && $match[1] < $span[1]),
        ], $matches[0]);
    }

    /**
     * The type with its Nth read token respelled for the Nth entry.
     *
     * aliasPropertyType()'s per-name queue cannot skip a null.
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

        $respelled = '';
        $from = 0;
        $at = 0;

        foreach (self::tokens($channel, $names, $type) as [$token, $offset, $read]) {
            if (! $read) {
                continue;
            }

            $fqcn = $queue[$at++] ?? null;
            $spelled = $fqcn !== null && in_array($token, self::namesOf($channel, [$fqcn]), true)
                ? $localName($fqcn, $token)
                : $token;
            $respelled .= substr($type, $from, $offset - $from).$spelled;
            $from = $offset + strlen($token);
        }

        return $respelled.substr($type, $from);
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
        $types = new ImportNameRegistry(ImportNameRegistry::RESOURCE_SKIP_SEGMENTS);
        $consts = new ImportNameRegistry(ImportNameRegistry::RESOURCE_SKIP_SEGMENTS);

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
