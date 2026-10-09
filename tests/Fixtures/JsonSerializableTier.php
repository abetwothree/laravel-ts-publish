<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures;

use JsonSerializable;

/** A backed enum json_encode() writes as its jsonSerialize() value, never as its backed value. */
enum JsonSerializableTier: string implements JsonSerializable
{
    case Gold = 'gold';

    /**
     * The tier and its label, which json_encode() writes in place of the case's value.
     *
     * @return array{tier: string, label: string}
     */
    public function jsonSerialize(): array
    {
        return ['tier' => $this->value, 'label' => ucfirst($this->value)];
    }
}
