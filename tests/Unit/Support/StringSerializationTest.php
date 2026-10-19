<?php

declare(strict_types=1);

use AbeTwoThree\LaravelTsPublish\Support\StringSerialization;
use AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures\ArrayJsonCarbon;
use AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures\ArrayJsonDate;
use AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures\NullableStringJson;
use AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures\StringableNullableJson;
use AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures\StringJsonArrayable;
use Carbon\Carbon as CarbonCarbon;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Carbon\CarbonInterval;
use Carbon\CarbonPeriod;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Stringable;
use Illuminate\Support\Uri;
use Workbench\App\Models\User;

it('answers the string json_encode() writes a class as, reading jsonSerialize() and never __toString()', function (string $class, ?string $type) {
    expect(StringSerialization::jsonStringType($class))->toBe($type);
})->with([
    'a Carbon date' => [Carbon::class, 'string'],
    'Carbon\\Carbon' => [CarbonCarbon::class, 'string'],
    'Carbon\\CarbonImmutable' => [CarbonImmutable::class, 'string'],
    'the CarbonInterface now() and today() declare' => [CarbonInterface::class, 'string'],
    'a Stringable' => [Stringable::class, 'string'],
    'a Uri' => [Uri::class, 'string'],
    'a ?string jsonSerialize()' => [NullableStringJson::class, 'string | null'],
    'a __toString() class whose ?string jsonSerialize() can write null' => [StringableNullableJson::class, 'string | null'],
    'an Arrayable whose jsonSerialize() returns a string' => [StringJsonArrayable::class, 'string'],
    'a Carbon date overriding jsonSerialize() with an array' => [ArrayJsonCarbon::class, null],
    'a DateTime with its own array jsonSerialize()' => [ArrayJsonDate::class, null],
    'a plain DateTime, written as an object' => [DateTime::class, null],
    'a DateTimeInterface, which may be either' => [DateTimeInterface::class, null],
    'a __toString() class with no jsonSerialize()' => [HtmlString::class, null],
    'an Exception' => [Exception::class, null],
    'a CarbonInterval, written as its DateInterval fields' => [CarbonInterval::class, null],
    'a CarbonPeriod, written as a list of dates' => [CarbonPeriod::class, null],
    'a model, written as its attributes' => [User::class, null],
    'a model narrowing jsonSerialize() to string' => [StringJsonModelProbe::class, null],
    'a name no class has' => ['No\\Such\\ClassName', null],
]);

/**
 * A model whose jsonSerialize() narrows to `string`, which the rule leaves to the model's own publishing.
 */
class StringJsonModelProbe extends Model
{
    /**
     * The string json_encode() writes.
     */
    public function jsonSerialize(): string
    {
        return 'x';
    }
}
