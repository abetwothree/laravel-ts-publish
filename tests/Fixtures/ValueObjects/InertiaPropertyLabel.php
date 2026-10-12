<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\ValueObjects;

use Inertia\PropertyContext;
use Inertia\ProvidesInertiaProperty;

/** A label Inertia sends as its toInertiaProperty() value, where json_encode() writes its public properties. */
final class InertiaPropertyLabel implements ProvidesInertiaProperty
{
    public function __construct(
        public string $text = 'label',
    ) {}

    /**
     * The value an Inertia prop sends.
     */
    public function toInertiaProperty(PropertyContext $prop): mixed
    {
        return strtoupper($this->text);
    }
}
