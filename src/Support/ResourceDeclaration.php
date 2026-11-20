<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Support;

use AbeTwoThree\LaravelTsPublish\Dtos\TsResourceDto;

/**
 * How an API resource's declaration opens and closes around its rows and its groups of arms, read once for every
 * template that prints one: an interface, its one group's arms alone, or
 * `export type X = <TsExtends> & { rows } & ( | arm | arm ) & … & (<TsIntersect>);`.
 *
 * @phpstan-import-type PropertiesList from TsResourceDto
 *
 * @internal
 */
final readonly class ResourceDeclaration
{
    /**
     * @param  bool  $interface  whether it prints as an interface: it has no arms and no intersection
     * @param  bool  $armsAlone  whether it prints as its one group's arms alone: no row, extends clause or intersection
     * @param  string  $open  what follows `export type X = ` where it prints as a type
     * @param  string|null  $close  the line after its rows, or null where none follows them
     * @param  string  $end  what follows its last group of arms: its intersections, then `;`
     */
    private function __construct(
        public bool $interface,
        public bool $armsAlone,
        public string $open,
        public ?string $close,
        private string $end,
    ) {}

    /**
     * The declaration a resource prints, from its extends clauses, its rows, its groups of arms and its intersections.
     *
     * @param  list<string>  $tsExtends
     * @param  PropertiesList  $properties
     * @param  list<string>  $variants
     * @param  list<string>  $intersections
     */
    public static function of(array $tsExtends, array $properties, array $variants, array $intersections): self
    {
        $rows = $properties !== [];
        $arms = $variants !== [];
        $interface = ! $arms && $intersections === [];
        $intersected = implode(' & ', array_map(static fn (string $type): string => '('.$type.')', $intersections));
        $end = $intersected === '' ? ';' : ' & '.$intersected.';';

        return new self(
            interface: $interface,
            armsAlone: count($variants) === 1 && ! $rows && $tsExtends === [] && $intersections === [],
            open: implode('', array_map(static fn (string $extends): string => $extends.' & ', $tsExtends))
                .($rows ? '{' : ($arms ? '(' : $intersected.';')),
            close: match (true) {
                $interface => '}',
                $rows => '}'.($arms ? ' & (' : $end),
                default => null,
            },
            end: $end,
        );
    }

    /**
     * The line that closes one group of arms: the next group's opener, or the intersections and `;` after the last.
     */
    public function closeArms(bool $last): string
    {
        return ')'.($last ? $this->end : ' & (');
    }
}
