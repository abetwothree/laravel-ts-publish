<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Transformers;

use AbeTwoThree\LaravelTsPublish\Dtos\TsBroadcastChannelsDto;
use AbeTwoThree\LaravelTsPublish\Facades\LaravelTsPublish;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;

/**
 * Transforms a flat collection of broadcast channel name strings into a
 * TsBroadcastChannelsDto containing pre-rendered TypeScript declarations.
 *
 * The algorithm mirrors Wayfinder's BroadcastChannels::toConstData() approach:
 * 1. For each channel, build flat dot-notation entries keyed parent-before-child.
 * 2. Merge all entries and call Arr::undot() to get a nested tree.
 * 3. Recursively render each tree node to a TypeScript property string.
 *
 * @phpstan-type ChannelMeta = array{params: list<string>, originalName: string}
 * @phpstan-type ChannelFlatEntry = array{__meta: ChannelMeta}
 */
class BroadcastChannelsTransformer
{
    private const PARAM_PATTERN = '/\{([^\}]+)\}/';

    /**
     * Transform a collection of channel names into a DTO ready for TypeScript rendering.
     *
     * @param  Collection<int, string>  $channels
     */
    public function transform(Collection $channels): TsBroadcastChannelsDto
    {
        if ($channels->isEmpty()) {
            return new TsBroadcastChannelsDto(
                typeUnion: '',
                constBody: '',
                isEmpty: true,
            );
        }

        /** @var list<string> $typeUnionMembers */
        $typeUnionMembers = array_values($channels->map(fn (string $name) => $this->toTypeUnionMember($name))->all());
        $typeUnion = $this->buildTypeUnion($typeUnionMembers);

        /** @var array<string, ChannelFlatEntry> $flatMap */
        $flatMap = $channels
            ->map(fn (string $name) => $this->toFlatMapEntries($name))
            ->collapse()
            ->all();

        /** @var array<string, mixed> $tree */
        $tree = Arr::undot($flatMap);

        $constBody = $this->renderTree($tree, 0);

        return new TsBroadcastChannelsDto(
            typeUnion: $typeUnion,
            constBody: $constBody,
            isEmpty: false,
        );
    }

    /**
     * Convert a channel name to a TypeScript template literal type union member.
     *
     * @example 'orders.{orderId}' → '`orders.${string | number}`'
     */
    private function toTypeUnionMember(string $channelName): string
    {
        $replaced = (string) preg_replace(self::PARAM_PATTERN, '${string | number}', $channelName);

        return '`'.$replaced.'`';
    }

    /**
     * Build the complete "export type BroadcastChannel = ..." statement.
     *
     * Single member → one-liner. Multiple → multi-line with leading `|` per member.
     *
     * @param  list<string>  $members
     */
    private function buildTypeUnion(array $members): string
    {
        if (count($members) === 1) {
            return 'export type BroadcastChannel = '.$members[0].';';
        }

        $lines = ['export type BroadcastChannel ='];

        foreach ($members as $member) {
            $lines[] = '    | '.$member;
        }

        $lines[count($lines) - 1] .= ';';

        return implode("\n", $lines);
    }

    /**
     * Produce flat dot-notation entries for a single channel name.
     *
     * Parents are always listed before children so that Arr::undot() merges correctly
     * without overwriting existing nested values.
     *
     * Algorithm:
     * - Iterate parts in REVERSE. When a {param} is encountered, queue it. When a
     *   static segment is encountered, store it in $chain with the queued params and
     *   reset the queue.
     * - Iterate parts FORWARD to build the flat map with dot-separated keys, ensuring
     *   parent keys (e.g. 'user') always appear before child keys (e.g. 'user.notifications').
     *
     * @return array<string, ChannelFlatEntry>
     */
    private function toFlatMapEntries(string $channelName): array
    {
        $parts = collect(explode('.', $channelName));

        /** @var list<string> $funcParams */
        $funcParams = [];

        /** @var array<string, ChannelFlatEntry> $chain */
        $chain = [];

        foreach ($parts->reverse() as $part) {
            if (preg_match(self::PARAM_PATTERN, $part, $matches)) {
                $funcParams[] = $matches[1];
            } else {
                $chain[$part] = [
                    '__meta' => [
                        'params' => array_reverse($funcParams),
                        'originalName' => $channelName,
                    ],
                ];
                $funcParams = [];
            }
        }

        $key = '';

        /** @var array<string, ChannelFlatEntry> $nested */
        $nested = [];

        foreach ($parts as $part) {
            if (isset($chain[$part])) {
                $key .= '.'.$part;
                $key = ltrim($key, '.');
                $nested[$key] = $chain[$part];
            }
        }

        return $nested;
    }

    /**
     * Recursively render a nested channel tree to TypeScript const body lines.
     *
     * Each call returns the indented lines for the given depth as a single string
     * (entries joined by newline, no leading or trailing newline). The caller is
     * responsible for adding surrounding braces and newlines.
     *
     * @param  array<string, mixed>  $tree
     */
    private function renderTree(array $tree, int $depth): string
    {
        $indent = str_repeat('    ', $depth + 1);
        $lines = [];

        foreach ($tree as $key => $item) {
            if (! is_array($item)) {
                continue;
            }

            $rawMeta = $item['__meta'] ?? [];
            $meta = is_array($rawMeta) ? $rawMeta : [];

            $rawParams = $meta['params'] ?? [];
            /** @var list<string> $params */
            $params = is_array($rawParams) ? array_values(array_filter((array) $rawParams, 'is_string')) : [];

            $rawOriginalName = $meta['originalName'] ?? $key;
            $originalName = is_string($rawOriginalName) ? $rawOriginalName : (string) $key;

            /** @var array<string, mixed> $children */
            $children = array_filter(
                $item,
                fn (mixed $v, mixed $k) => $k !== '__meta',
                ARRAY_FILTER_USE_BOTH,
            );

            $isFinalSegment = $children === [];
            // Delegate to the shared facade utility: quotes keys that are not valid JS identifiers
            // (e.g. 'public-announcements' → '"public-announcements"'), leaves valid ones as-is.
            $tsKey = LaravelTsPublish::validJsObjectKey((string) $key);

            if ($isFinalSegment) {
                $returnValue = '`'.$this->toTemplateString($originalName).'` as const';

                if ($params === []) {
                    $lines[] = "{$indent}{$tsKey}: {$returnValue},";
                } else {
                    $paramList = $this->toTsParams($params);
                    $lines[] = "{$indent}{$tsKey}: ({$paramList}) => {$returnValue},";
                }
            } else {
                $nestedContent = $this->renderTree($children, $depth + 1);
                $closingIndent = str_repeat('    ', $depth + 1);

                if ($params === []) {
                    $lines[] = "{$indent}{$tsKey}: {\n{$nestedContent}\n{$closingIndent}},";
                } else {
                    $paramList = $this->toTsParams($params);
                    $lines[] = "{$indent}{$tsKey}: ({$paramList}) => ({\n{$nestedContent}\n{$closingIndent}}),";
                }
            }
        }

        return implode("\n", $lines);
    }

    /**
     * Replace {param} placeholders with ${param} for TypeScript template literals.
     *
     * @example 'orders.{orderId}' → 'orders.${orderId}'
     */
    private function toTemplateString(string $channelName): string
    {
        return (string) preg_replace(self::PARAM_PATTERN, '${$1}', $channelName);
    }

    /**
     * Build a TypeScript parameter list string from param name array.
     *
     * @param  list<string>  $params
     *
     * @example ['userId', 'roomId'] → 'userId: string | number, roomId: string | number'
     */
    private function toTsParams(array $params): string
    {
        return implode(', ', array_map(fn (string $p) => "{$p}: string | number", $params));
    }
}
