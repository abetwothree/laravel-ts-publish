<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures;

/** A plain class whose values() and all() return what their signatures say, not the class's own public properties. */
final class PostSettings
{
    public string $theme = 'dark';

    public int $size = 1;

    /**
     * Only the theme, keyed by name.
     *
     * @return array<string, string>
     */
    public function all(): array
    {
        return ['theme' => $this->theme];
    }

    /**
     * Every setting as a list.
     *
     * @return list<string|int>
     */
    public function values(): array
    {
        return [$this->theme, $this->size];
    }
}
