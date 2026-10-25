<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Ast\Composition;

use AbeTwoThree\LaravelTsPublish\Ast\Analysis\MethodAnalysis;
use AbeTwoThree\LaravelTsPublish\Ast\Values\EnumValueTypes;
use AbeTwoThree\LaravelTsPublish\Ast\Values\ValueResult;
use AbeTwoThree\LaravelTsPublish\Support\ClassTokenQueue;

/**
 * Spells apart a `…Kind` alias two same-named enums share, for a file that aliases no import: a route file, a metadata
 * companion or the shared-data file would import one name from two modules. Each such token becomes its enum's literal
 * case-name union, which needs no import.
 *
 * @phpstan-type SpelledKinds = array<string, class-string>
 * @phpstan-type SharedKinds = array<string, array<class-string, true>>
 *
 * @internal
 */
final class SharedEnumKinds
{
    /**
     * Respell, in the analysis's property types, each `…Kind` token two of its enums spell, and name the one enum
     * behind each `…Kind` the types still spell.
     *
     * @return SpelledKinds `…Kind` name => the enum behind its tokens
     */
    public static function spellApart(MethodAnalysis $analysis): array
    {
        /** @var SharedKinds $spelledBy `…Kind` name => the enums behind its tokens */
        $spelledBy = [];

        foreach ($analysis->properties as $property) {
            foreach (self::tokens($analysis, $property['name'])->spellings($property['type']) as [$enum, $spelled]) {
                if (in_array($spelled, ValueResult::enumSiblingNames($enum), true)) {
                    $spelledBy[$spelled][$enum] = true;
                }
            }
        }

        $shared = self::shared($spelledBy);

        if ($shared !== []) {
            foreach ($analysis->properties as $index => $property) {
                $analysis->properties[$index]['type'] = self::tokens($analysis, $property['name'])->rewrite(
                    $property['type'],
                    static fn (string $enum, string $spelled, string $next): string => isset($shared[$spelled][$enum])
                        ? self::literal($enum, $next)
                        : $spelled,
                );
            }
        }

        return array_map(
            static fn (array $enums): string => array_keys($enums)[0],
            array_diff_key($spelledBy, $shared),
        );
    }

    /**
     * The `…Kind` names the given name maps put different enums behind, as spellApart() finds them within one analysis,
     * for a file whose types each spellApart() read alone.
     *
     * @param  list<SpelledKinds>  $kinds
     * @return SharedKinds
     */
    public static function sharedAcross(array $kinds): array
    {
        $spelledBy = [];

        foreach ($kinds as $spelled) {
            foreach ($spelled as $kind => $enum) {
                $spelledBy[$kind][$enum] = true;
            }
        }

        return self::shared($spelledBy);
    }

    /**
     * Respell each `…Kind` token of a type that sharedAcross() lists as the literal case-name union of its own enum.
     *
     * @param  SpelledKinds  $kinds  the type's own `…Kind` names, as spellApart() named them
     * @param  SharedKinds  $shared
     */
    public static function spellShared(string $type, array $kinds, array $shared): string
    {
        foreach (array_intersect_key($kinds, $shared) as $kind => $enum) {
            $type = self::spellAsLiteral($type, $kind, $enum);
        }

        return $type;
    }

    /**
     * The `…Kind` names more than one enum is behind.
     *
     * @param  SharedKinds  $spelledBy
     * @return SharedKinds
     */
    private static function shared(array $spelledBy): array
    {
        return array_filter($spelledBy, static fn (array $enums): bool => count($enums) > 1);
    }

    /**
     * Respell each `$kind` token in the type as the enum's literal case-name union.
     *
     * @param  class-string  $enum
     */
    private static function spellAsLiteral(string $type, string $kind, string $enum): string
    {
        return new ClassTokenQueue([$enum], static fn (): string => $kind)->rewrite(
            $type,
            static fn (string $class, string $spelled, string $next): string => self::literal($class, $next),
        );
    }

    /**
     * The enum classes a property's tokens spell, in the order AnalysisComposer queues them, each read by both names.
     */
    private static function tokens(MethodAnalysis $analysis, int|string $property): ClassTokenQueue
    {
        return ValueResult::enumTokens([
            ...(isset($analysis->enumResources[$property]) ? [$analysis->enumResources[$property]] : []),
            ...(isset($analysis->directEnumFqcns[$property]) ? [$analysis->directEnumFqcns[$property]] : []),
            ...($analysis->inlineEnumFqcns[$property] ?? []),
        ]);
    }

    /**
     * The enum's case-name union, parenthesized where a `[]` follows it.
     */
    private static function literal(string $enum, string $next): string
    {
        $union = EnumValueTypes::nameUnion($enum);

        return $next === '[' && str_contains($union, ' | ') ? '('.$union.')' : $union;
    }
}
