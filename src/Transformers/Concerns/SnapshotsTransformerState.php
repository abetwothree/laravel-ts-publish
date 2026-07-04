<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Transformers\Concerns;

trait SnapshotsTransformerState
{
    /**
     * Property names that must NOT be serialized (Reflection handles, live
     * Eloquent models, injected services). They are intermediates of the
     * transform step and are never read by writers/aggregates.
     *
     * @return list<string>
     */
    abstract protected function transientProperties(): array;

    /**
     * @return array<string, mixed>
     */
    public function __serialize(): array
    {
        /** @var array<string, mixed> $data */
        $data = get_object_vars($this);

        foreach ($this->transientProperties() as $name) {
            unset($data[$name]);
        }

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function __unserialize(array $data): void
    {
        foreach ($data as $name => $value) {
            // @phpstan-ignore property.dynamicName
            $this->{$name} = $value;
        }
    }
}
