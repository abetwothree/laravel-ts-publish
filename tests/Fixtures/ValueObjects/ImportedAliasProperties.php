<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\ValueObjects;

use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Models\TypeAliasShapeModel;

/**
 * Properties, plain and promoted, whose `@var` names an alias imported from another class, whole or inside a list.
 *
 * @phpstan-import-type Row from TypeAliasShapeModel
 */
final class ImportedAliasProperties
{
    /** @var Row */
    public array $plain = ['a' => 1];

    /**
     * Declare the promoted alias-typed properties.
     *
     * @param  Row  $promoted
     * @param  list<Row>  $promotedList
     */
    public function __construct(
        /** @var Row */
        public array $promoted = ['a' => 1],
        /** @var list<Row> */
        public array $promotedList = [['a' => 1]],
    ) {}
}
