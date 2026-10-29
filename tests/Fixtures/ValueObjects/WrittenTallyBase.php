<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\ValueObjects;

/** A test-only payload whose `toArray()` reads untyped defaults only its subclasses write. */
class WrittenTallyBase
{
    protected $tier = 'gold';

    protected $count = 0;

    protected $label = 'draft';

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'tier' => $this->tier,
            'count' => $this->count,
            'label' => $this->label,
            'labelled' => $this->labelled(),
        ];
    }

    /**
     * The label, under its key.
     *
     * @return array<string, mixed>
     */
    public function labelled(): array
    {
        return ['label' => $this->label];
    }
}
