<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Transformers;

use AbeTwoThree\LaravelTsPublish\Dtos\TsBroadcastChannelsDto;
use AbeTwoThree\LaravelTsPublish\Facades\JsEmitter;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use InvalidArgumentException;

/**
 * Transforms broadcast channel names into a TsBroadcastChannelsDto of pre-rendered TypeScript.
 *
 * Mirrors Wayfinder's BroadcastChannels::toConstData(): flat dot-notation entries, Arr::undot(),
 * then a recursive render of the resulting tree.
 *
 * @phpstan-type ChannelMeta = array{params?: list<string>, originalName: string, selfChannel?: string}
 * @phpstan-type ChannelFlatEntry = array{__meta: ChannelMeta}
 */
class BroadcastChannelsTransformer
{
    private const PARAM_PATTERN = '/\{([^\}]+)\}/';

    /** @var Collection<int, string> */
    private Collection $channels;

    private string $typeUnion = '';

    /** @var array<string, ChannelFlatEntry> */
    private array $flatMap = [];

    private string $constBody = '';

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

        $this->channels = $channels;

        $this->transformTypeUnion()
            ->buildChannelFlatMap()
            ->buildConstBody();

        return new TsBroadcastChannelsDto(
            typeUnion: $this->typeUnion,
            constBody: $this->constBody,
            isEmpty: false,
        );
    }

    /**
     * Build the TypeScript type union string from the channel collection.
     */
    private function transformTypeUnion(): self
    {
        /** @var list<string> $members */
        $members = array_values($this->channels->map(fn (string $name) => $this->toTypeUnionMember($name))->all());
        $this->typeUnion = $this->buildTypeUnion($members);

        return $this;
    }

    /**
     * Build the flat dot-notation map from all channel names.
     */
    private function buildChannelFlatMap(): self
    {
        $this->flatMap = [];

        foreach ($this->channels as $name) {
            foreach ($this->toFlatMapEntries($name) as $key => $entry) {
                if (isset($this->flatMap[$key])) {
                    $existingParams = $this->flatMap[$key]['__meta']['params'] ?? [];
                    $newParams = $entry['__meta']['params'] ?? [];
                    if ($existingParams !== $newParams) {
                        throw new InvalidArgumentException("Broadcast channel segment [{$key}] has conflicting parameter names.");
                    }
                    // Another channel may terminate at this shared segment; keep that marker.
                    if (isset($entry['__meta']['selfChannel']) && ! isset($this->flatMap[$key]['__meta']['selfChannel'])) {
                        $this->flatMap[$key]['__meta']['selfChannel'] = $entry['__meta']['selfChannel'];
                    }

                    continue;
                }
                $this->flatMap[$key] = $entry;
            }
        }

        return $this;
    }

    /**
     * Render the TypeScript const body from the flat map.
     */
    private function buildConstBody(): self
    {
        /** @var array<string, mixed> $tree */
        $tree = Arr::undot($this->flatMap);
        $this->constBody = $this->renderTree($tree, 0);

        return $this;
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
     * Parents must precede children or Arr::undot() overwrites the already-nested values.
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

        // Mark the last static segment as terminal so renderTree can give it a $channel accessor.
        $lastKey = array_key_last($nested);
        if ($lastKey !== null) {
            $nested[$lastKey]['__meta']['selfChannel'] = $channelName;
        }

        return $nested;
    }

    /**
     * Recursively render a nested channel tree to TypeScript const body lines.
     *
     * Returns joined lines with no leading or trailing newline; callers add the braces.
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
            $tsKey = JsEmitter::validJsObjectKey((string) $key);

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
                $contentIndent = str_repeat('    ', $depth + 2);

                // A node that is itself a channel ('chat.{roomId}' next to 'chat.{roomId}.messages')
                // needs a $channel accessor to stay reachable beside its children.
                $rawSelfChannel = $meta['selfChannel'] ?? null;
                if (is_string($rawSelfChannel)) {
                    $selfValue = '`'.$this->toTemplateString($rawSelfChannel).'` as const';
                    $nestedContent = "{$contentIndent}\$channel: {$selfValue},\n{$nestedContent}";
                }

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
