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
 * @internal
 */
final class SharedEnumKinds
{
    /**
     * Respell, in the analysis's property types, each `…Kind` token two of its enums spell, and name the one enum
     * behind each `…Kind` the types still spell.
     *
     * @return array<string, class-string> `…Kind` name => the enum behind its tokens
     */
    public static function spellApart(MethodAnalysis $analysis): array
    {
        /** @var array<string, array<class-string, true>> $spelledBy `…Kind` name => the enums behind its tokens */
        $spelledBy = [];

        foreach ($analysis->properties as $property) {
            foreach (self::tokens($analysis, $property['name'])->spellings($property['type']) as [$enum, $spelled]) {
                if (in_array($spelled, ValueResult::enumSiblingNames($enum), true)) {
                    $spelledBy[$spelled][$enum] = true;
                }
            }
        }

        $shared = array_filter($spelledBy, static fn (array $enums): bool => count($enums) > 1);

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
     * Respell each `$kind` token in the type as the enum's literal case-name union.
     *
     * @param  class-string  $enum
     */
    public static function spellAsLiteral(string $type, string $kind, string $enum): string
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
        return new ClassTokenQueue(
            [
                ...(isset($analysis->enumResources[$property]) ? [$analysis->enumResources[$property]] : []),
                ...(isset($analysis->directEnumFqcns[$property]) ? [$analysis->directEnumFqcns[$property]] : []),
                ...($analysis->inlineEnumFqcns[$property] ?? []),
            ],
            ValueResult::enumTypeName(...),
            ValueResult::enumSiblingNames(...),
        );
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
