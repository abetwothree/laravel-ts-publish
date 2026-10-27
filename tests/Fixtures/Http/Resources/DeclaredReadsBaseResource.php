<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\Concerns\StashesLocalThroughSelf;
use Illuminate\Http\Request;
use Workbench\App\ValueObjects\PostStats;

/**
 * Helpers whose `self` and `parent` name this class and DeclaredReadsRootResource, though DeclaredReadsResource, the
 * class they run on, redeclares every member their `self` reaches.
 */
class DeclaredReadsBaseResource extends DeclaredReadsRootResource
{
    use StashesLocalThroughSelf;

    public const KEY = 'base_key';

    public const LEVEL = 2;

    /** The stats this class's helpers read through `self::`. */
    public static function stats(): PostStats
    {
        return new PostStats(1.5, 2);
    }

    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return ['base' => 1];
    }

    /** @return array<string, mixed> */
    public function part(): array
    {
        return ['base_part' => 1];
    }

    /** @return array<string, mixed> */
    public function titled(): array
    {
        return ['title' => $this->title];
    }

    /** @return array<string, mixed> */
    protected function reads(Request $request): array
    {
        return [
            ...parent::toArray($request),
            ...self::part(),
            self::KEY => true,
            'built' => new self($this->resource)->part(),
            'built_titled' => new self($this->resource)->titled(),
            'views' => self::stats()->views,
            'level' => parent::LEVEL,
        ];
    }

    /** @return array<string, mixed> */
    protected function stashed(): array
    {
        $out = ['seed' => 1];
        self::fill($out);

        return ['stash' => $out];
    }

    /**
     * Writes a key into the array it is passed.
     *
     * @param  array<string, mixed>  $out
     */
    private static function fill(array &$out): void
    {
        $out['filled'] = true;
    }
}
