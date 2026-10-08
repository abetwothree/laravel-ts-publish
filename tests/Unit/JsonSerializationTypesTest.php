<?php

declare(strict_types=1);

use AbeTwoThree\LaravelTsPublish\Analyzers\ResourceAstAnalyzer;
use AbeTwoThree\LaravelTsPublish\Facades\LaravelTsPublish;
use AbeTwoThree\LaravelTsPublish\LaravelTsPublish as LaravelTsPublishService;
use AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures\ArrayJsonCarbon;
use AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures\DateTimeCast;
use AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures\NullableStringJson;
use AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures\PublicLabelStringable;
use AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures\SerializationProbeResource;
use AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures\SerializedDateModel;
use AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures\StringableNullableJson;
use AbeTwoThree\LaravelTsPublish\Transformers\ModelTransformer;
use Carbon\CarbonInterface;
use Carbon\CarbonInterval;
use Carbon\CarbonPeriod;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
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
    'a jsonSerialize(): mixed whose @return is string' => [DocStringJsonProbe::class, 'string'],
    'a jsonSerialize(): mixed whose @return is ?string' => [DocNullableStringJsonProbe::class, 'string | null'],
    'an untyped jsonSerialize() whose @return is string' => [UntypedDocStringJsonProbe::class, 'string'],
    'a __toString() class with no public property' => [HtmlString::class, 'Record<string, never>'],
    'an Exception' => [Exception::class, 'Record<string, never>'],
    'an UploadedFile' => [UploadedFile::class, 'Record<string, never>'],
    'a __toString() class whose only public property is static' => [StaticOnlyStringableProbe::class, 'Record<string, never>'],
    'a __toString() class with typed public properties' => [PublicLabelStringable::class, '{ text: string; rank: number | null }'],
    'a plain DateTime' => [DateTime::class, LaravelTsPublishService::DATE_TIME_OBJECT_TYPE],
    'a plain DateTimeImmutable' => [DateTimeImmutable::class, LaravelTsPublishService::DATE_TIME_OBJECT_TYPE],
    'a DateTimeInterface, a Carbon string or a plain DateTime' => [DateTimeInterface::class, 'string | '.LaravelTsPublishService::DATE_TIME_OBJECT_TYPE],
    'the CarbonInterface now() and today() declare' => [CarbonInterface::class, 'string'],
    'a Carbon date whose ?string jsonSerialize() can write null' => [NullableJsonCarbonProbe::class, 'string | null'],
    'a Carbon date overriding jsonSerialize() with an array shape' => [ArrayJsonCarbon::class, '{ iso: string }'],
    'a CarbonPeriod, whose jsonSerialize() lists its dates' => [CarbonPeriod::class, 'string[]'],
    'a CarbonPeriod overriding jsonSerialize() with an array shape' => [ArrayJsonPeriodProbe::class, '{ from: string }'],
    'a CarbonInterval whose jsonSerialize() writes a string' => [StringJsonIntervalProbe::class, 'string'],
    'the datetime column type, unchanged' => ['datetime', 'string'],
    'a class cast returning ?DateTime, which Model::toArray() runs through serializeDate()' => [DateTimeCast::class, 'string | null'],
]);

it('keeps the class token of a __toString() class json_encode() can write properties for', function (string $class) {
    expect(LaravelTsPublish::toTsType($class)['type'])->toBe($class);
})->with([
    'an abstract class, whose subclass can declare public properties' => [AbstractStringableProbe::class],
    'a class that allows dynamic properties' => [DynamicStringableProbe::class],
    'a class whose parent allows dynamic properties' => [InheritedDynamicStringableProbe::class],
    'an untyped public property' => [UntypedPublicStringableProbe::class],
    'a jsonSerialize() that declares mixed' => [MixedJsonStringableProbe::class],
    'an internal class that writes its own properties' => [SimpleXMLElement::class],
    'a model, whose mixed jsonSerialize() says string' => [DocStringJsonModelProbe::class],
    'a mixed jsonSerialize() whose @return is only null' => [DocNullJsonProbe::class],
]);

it('publishes a CarbonInterval as the DateInterval fields json_encode() writes', function () {
    expect(LaravelTsPublish::toTsType(CarbonInterval::class)['type'])->toBe(LaravelTsPublishService::CARBON_INTERVAL_OBJECT_TYPE);
});

it('publishes every Carbon date as Date under timestamps_as_date, CarbonInterface included', function (string $class, string $type) {
    config()->set('ts-publish.timestamps_as_date', true);

    expect(LaravelTsPublish::toTsType($class)['type'])->toBe($type);
})->with([
    'Carbon\\CarbonInterface' => [CarbonInterface::class, 'Date'],
    'Illuminate\\Support\\Carbon' => [Carbon::class, 'Date'],
    'a Carbon date whose ?string jsonSerialize() can write null' => [NullableJsonCarbonProbe::class, 'Date | null'],
    'a jsonSerialize(): mixed whose @return is a Carbon date' => [DocCarbonJsonProbe::class, 'Date'],
]);

it('publishes a model date as Model::toArray() writes it', function () {
    $mutators = (new ModelTransformer(SerializedDateModel::class))->data()->mutators;

    expect($mutators['opened_on']['type'])->toBe('string | null')
        ->and($mutators['reviewed_on']['type'])->toBe('string')
        ->and($mutators['closed_on']['type'])->toBe('string | null')
        ->and($mutators['paused_at']['type'])->toBe('string | number | null')
        ->and($mutators['locked_on']['type'])->toBe('string | number | null')
        ->and($mutators['clocked_at']['type'])->toBe(LaravelTsPublishService::DATE_TIME_OBJECT_TYPE);
});

it('answers no serialized date for a return that names no date', function (Closure $getter) {
    expect(LaravelTsPublish::serializedDateReturnTypes(new ReflectionFunction($getter)))->toBeNull();
})->with([
    'a scalar union' => [fn (): int|string => 1],
    'no return type' => [fn () => 1],
]);

it('types a resource key as json_encode() writes it on every path the engine reads', function () {
    $analysis = new ResourceAstAnalyzer(new ReflectionClass(SerializationProbeResource::class), Post::class)->analyze();
    $types = array_column($analysis->properties, 'type', 'name');

    expect($types)->toBe([
        'failure' => 'Record<string, never>',
        'opened_on' => LaravelTsPublishService::DATE_TIME_OBJECT_TYPE,
        'note' => 'string | null',
        'now' => 'string',
        'failures' => 'Record<string, never>[]',
        'last_error' => 'Record<string, never> | null',
        'opened_at' => LaravelTsPublishService::DATE_TIME_OBJECT_TYPE,
        'own_error' => 'Record<string, never>',
        'own_note' => 'string | null',
        'caught' => 'Record<string, never>',
        'checked_at' => 'string | null',
        'default_checked_at' => 'string | null',
        'default_note' => 'string | null',
    ]);
});

/**
 * A `__toString()` value whose jsonSerialize() keeps the interface's `mixed`; only its `@return` says `string`.
 */
class DocStringJsonProbe implements JsonSerializable
{
    /**
     * The text a Blade echo writes.
     */
    public function __toString(): string
    {
        return 'x';
    }

    /**
     * The string json_encode() writes.
     *
     * @return string
     */
    public function jsonSerialize(): mixed
    {
        return 'x';
    }
}

/**
 * A model whose `mixed` jsonSerialize() says `string`, which publishing leaves to the model's own interface.
 */
class DocStringJsonModelProbe extends Model
{
    /**
     * The string json_encode() writes.
     *
     * @return string
     */
    public function jsonSerialize(): mixed
    {
        return 'x';
    }
}

/**
 * A value whose `mixed` jsonSerialize() says it writes only null, which no string rule covers.
 */
class DocNullJsonProbe implements JsonSerializable
{
    /**
     * The null json_encode() writes.
     *
     * @return null
     */
    public function jsonSerialize(): mixed
    {
        return null;
    }
}

/**
 * A value whose `mixed` jsonSerialize() says in its `@return` that it can write null.
 */
class DocNullableStringJsonProbe implements JsonSerializable
{
    /**
     * The string or null json_encode() writes.
     *
     * @return ?string
     */
    public function jsonSerialize(): mixed
    {
        return null;
    }
}

/**
 * A value whose jsonSerialize() declares no return type at all, only an `@return`.
 */
class UntypedDocStringJsonProbe implements JsonSerializable
{
    /**
     * The string json_encode() writes.
     *
     * @return string
     */
    #[ReturnTypeWillChange]
    public function jsonSerialize()
    {
        return 'x';
    }
}

/**
 * A value whose `mixed` jsonSerialize() returns a Carbon date, which json_encode() writes as Carbon's string.
 */
class DocCarbonJsonProbe implements JsonSerializable
{
    /**
     * The date json_encode() writes.
     *
     * @return CarbonInterface
     */
    public function jsonSerialize(): mixed
    {
        return Carbon::now();
    }
}

/**
 * A Carbon date whose jsonSerialize() narrows to `?string`, so json_encode() can write null.
 */
class NullableJsonCarbonProbe extends Carbon
{
    /**
     * The ISO string json_encode() writes, or null.
     */
    public function jsonSerialize(): ?string
    {
        return null;
    }
}

/**
 * A CarbonPeriod whose own jsonSerialize() replaces the list of dates with an object.
 */
class ArrayJsonPeriodProbe extends CarbonPeriod
{
    /**
     * Serialize as an object holding the start date.
     *
     * @return array{from: string}
     */
    public function jsonSerialize(): array
    {
        return ['from' => (string) $this->getStartDate()];
    }
}

/**
 * A CarbonInterval json_encode() writes through its own `string` jsonSerialize().
 */
class StringJsonIntervalProbe extends CarbonInterval implements JsonSerializable
{
    /**
     * The ISO spec json_encode() writes.
     */
    public function jsonSerialize(): string
    {
        return $this->spec();
    }
}

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
 * A `__toString()` class that inherits its parent's leave to gain public properties at runtime.
 */
class InheritedDynamicStringableProbe extends DynamicStringableProbe {}

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
