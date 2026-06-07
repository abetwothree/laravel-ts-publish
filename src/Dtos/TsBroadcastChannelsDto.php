<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Dtos;

/**
 * Data transfer object for the generated broadcast-channels.ts file.
 *
 * Both `typeUnion` and `constBody` are pre-rendered TypeScript strings produced
 * by BroadcastChannelsTransformer. The Blade template stitches them together.
 */
final readonly class TsBroadcastChannelsDto
{
    /**
     * @param string $typeUnion  The complete "export type BroadcastChannel = ..." statement (with semicolon).
     * @param string $constBody  The inner body (entries) of "export const BroadcastChannels = { ... }".
     * @param bool   $isEmpty    True when no channels were collected.
     */
    public function __construct(
        public string $typeUnion,
        public string $constBody,
        public bool $isEmpty,
    ) {}
}
