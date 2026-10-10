<?php

declare(strict_types=1);

use AbeTwoThree\LaravelTsPublish\Ast\Concerns\BuildsInlineObjectTypes;
use AbeTwoThree\LaravelTsPublish\Ast\MethodAnalysis;

test('a nested shape prints each key as TypeScript reads it', function (array $property, string $expected) {
    $builder = new class
    {
        use BuildsInlineObjectTypes;

        /** The inline object type the trait builds. */
        public function build(MethodAnalysis $analysis): string
        {
            return $this->buildInlineObjectType($analysis);
        }
    };

    expect($builder->build(new MethodAnalysis(properties: [[...$property, 'description' => '']])))->toBe($expected);
})->with([
    'an optional identifier' => [['name' => 'price_tag', 'type' => 'number', 'optional' => true], '{ price_tag?: number }'],
    'a key that is not an identifier' => [['name' => 'can-edit', 'type' => 'boolean', 'optional' => false], '{ "can-edit": boolean }'],
    'a literal key spelled with brackets' => [['name' => '[weird]', 'type' => 'number', 'optional' => false], '{ "[weird]": number }'],
    'a signature, bare' => [
        ['name' => '[key: `${string}_tag`]', 'type' => 'string | undefined', 'optional' => false],
        '{ [key: `${string}_tag`]: string | undefined }',
    ],
    'a signature marked optional, which never prints ?' => [
        ['name' => '[key: number]', 'type' => 'string', 'optional' => true],
        '{ [key: number]: string }',
    ],
]);
