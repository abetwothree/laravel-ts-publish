<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Ast\Concerns;

use AbeTwoThree\LaravelTsPublish\Ast\MethodAnalysis;
use AbeTwoThree\LaravelTsPublish\Facades\JsEmitter;

/**
 * Flatten an analysis's properties into an inline TypeScript object literal type.
 *
 * The single home for this: InlineArrayHandler and StaticCallHandler both render one, and neither
 * could reach the other's private copy before this trait.
 *
 * @internal
 */
trait BuildsInlineObjectTypes
{
    /**
     * Flatten an analysis's properties into an inline TypeScript object literal type.
     *
     * A re-declared key keeps its first position and last value, as PHP does; emitting both is TS2300.
     * Any enum-token substitution has to be applied to the properties before this is called.
     */
    protected function buildInlineObjectType(MethodAnalysis $analysis): string
    {
        if ($analysis->properties === []) {
            return 'Record<string, unknown>';
        }

        $collapsed = [];

        foreach ($analysis->properties as $prop) {
            $collapsed[$prop['name']] = $prop;
        }

        $parts = array_map(function (array $prop): string {
            $key = JsEmitter::validJsObjectKey($prop['name']);

            return $prop['optional'] ? "{$key}?: {$prop['type']}" : "{$key}: {$prop['type']}";
        }, array_values($collapsed));

        return '{ '.implode('; ', $parts).' }';
    }
}
