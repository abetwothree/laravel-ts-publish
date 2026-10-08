<?php

declare(strict_types=1);

use AbeTwoThree\LaravelTsPublish\Analyzers\ResourceAstAnalyzer;
use AbeTwoThree\LaravelTsPublish\Facades\LaravelTsPublish;
use AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures\NullableStringJson;
use AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures\PublicLabelStringable;
use AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures\SerializationProbeResource;
use AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures\StringableNullableJson;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Stringable;
use Workbench\App\Models\Post;

it('publishes a class as what json_encode() writes for it, never what __toString() returns', function (string $class, string $type) {
    expect(LaravelTsPublish::toTsType($class)['type'])->toBe($type);
})->with([
    'a ?string jsonSerialize() with no __toString()' => [NullableStringJson::class, 'string | null'],
    'the same class, nullable' => ['?'.NullableStringJson::class, 'string | null'],
    'a __toString() class whose ?string jsonSerialize() can write null' => [StringableNullableJson::class, 'string | null'],
    'a Stringable, whose jsonSerialize() is its string' => [Stringable::class, 'string'],
    'a __toString() class with no public property' => [HtmlString::class, 'Record<string, never>'],
    'an Exception' => [Exception::class, 'Record<string, never>'],
    'an UploadedFile' => [UploadedFile::class, 'Record<string, never>'],
    'a __toString() class whose only public property is static' => [StaticOnlyStringableProbe::class, 'Record<string, never>'],
    'a __toString() class with typed public properties' => [PublicLabelStringable::class, '{ text: string; rank: number | null }'],
]);

it('keeps the class token of a __toString() class json_encode() can write properties for', function (string $class) {
    expect(LaravelTsPublish::toTsType($class)['type'])->toBe($class);
})->with([
    'an abstract class, whose subclass can declare public properties' => [AbstractStringableProbe::class],
    'a class that allows dynamic properties' => [DynamicStringableProbe::class],
    'an untyped public property' => [UntypedPublicStringableProbe::class],
    'a jsonSerialize() that declares mixed' => [MixedJsonStringableProbe::class],
    'an internal class that writes its own properties' => [SimpleXMLElement::class],
]);

it('types a resource key as json_encode() writes it on every path the engine reads', function () {
    $analysis = new ResourceAstAnalyzer(new ReflectionClass(SerializationProbeResource::class), Post::class)->analyze();
    $types = array_column($analysis->properties, 'type', 'name');

    expect($types)->toBe([
        'note' => 'string | null',
        'own_error' => 'Record<string, never>',
        'own_note' => 'string | null',
        'caught' => 'Record<string, never>',
        'checked_at' => 'string | null',
        'default_checked_at' => 'string | null',
        'default_note' => 'string | null',
    ]);
});

/**
 * A `__toString()` class whose one public property is static, which json_encode() never writes.
 */
class StaticOnlyStringableProbe
{
    public static int $made = 0;

    /**
     * The text a Blade echo writes.
     */
    public function __toString(): string
    {
        return 'x';
    }
}

/**
 * An abstract `__toString()` class: a subclass instance can carry public properties.
 */
abstract class AbstractStringableProbe
{
    /**
     * The text a Blade echo writes.
     */
    public function __toString(): string
    {
        return 'x';
    }
}

/**
 * A `__toString()` class whose instances can gain public properties at runtime.
 */
#[AllowDynamicProperties]
class DynamicStringableProbe
{
    /**
     * The text a Blade echo writes.
     */
    public function __toString(): string
    {
        return 'x';
    }
}

/**
 * A `__toString()` class whose public property json_encode() writes, though no type says what it holds.
 */
class UntypedPublicStringableProbe
{
    /** @var mixed */
    public $label = 'x';

    /**
     * The text a Blade echo writes.
     */
    public function __toString(): string
    {
        return 'x';
    }
}

/**
 * A `__toString()` class whose jsonSerialize() declares `mixed`, so only its body says what json_encode() writes.
 */
class MixedJsonStringableProbe implements JsonSerializable
{
    /**
     * The text a Blade echo writes.
     */
    public function __toString(): string
    {
        return 'x';
    }

    /**
     * The value json_encode() writes.
     */
    public function jsonSerialize(): mixed
    {
        return ['a' => 1];
    }
}
