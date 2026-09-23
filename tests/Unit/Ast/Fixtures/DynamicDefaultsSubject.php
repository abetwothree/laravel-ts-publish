<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures;

/** Writes whatever property its input names, so no default can stand for any of them. */
final class DynamicDefaultsSubject
{
    protected $status = 'draft';

    /**
     * Copies every entry onto the property of the same name.
     *
     * @param  array<string, mixed>  $data
     */
    public function fill(array $data): void
    {
        foreach ($data as $key => $value) {
            $this->{$key} = $value;
        }
    }
}
