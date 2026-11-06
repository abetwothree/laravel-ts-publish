<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Models\Concerns;

/** Reads the title as a map of translations, as a translatable-attributes package does through getAttributeValue(). */
trait TranslatesTitle
{
    /** Reads an attribute, the title as its translations. */
    public function getAttributeValue($key): mixed
    {
        return $key === 'title' ? ['en' => (string) parent::getAttributeValue($key)] : parent::getAttributeValue($key);
    }
}
