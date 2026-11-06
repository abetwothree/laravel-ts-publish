<?php

declare(strict_types=1);

use AbeTwoThree\LaravelTsPublish\Ast\Members\CastCasters;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Casts\ArgumentCastable;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Casts\BlankToNullSerializeCast;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Casts\CountingCastable;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Casts\DeclaredCastable;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Casts\InboundDualCast;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Casts\InstanceCastable;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Casts\NoInterfaceSerializeCast;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Casts\NoInterfaceTypedSerializeCast;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Casts\NullableSerializedLabelCast;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Casts\ReceiverDualCast;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Casts\SerializedLabelCast;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Ui\GaugeCard;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Ui\GaugeCardCast;
use Illuminate\Database\Eloquent\Casts\AsCollection;
use Workbench\App\Casts\CoordinateCast;
use Workbench\App\Enums\Status;

test('reads the casters a cast runs without calling castUsing()', function (string $cast, ?array $expected) {
    expect(CastCasters::casters($cast))->toBe($expected);
})->with([
    'a caster class' => [CoordinateCast::class, [CoordinateCast::class]],
    'a caster class with arguments' => [CoordinateCast::class.':a,b', [CoordinateCast::class]],
    'a Castable declaring its caster, whose body throws' => [DeclaredCastable::class, [CoordinateCast::class]],
    'a Castable declaring the interface, so its body names the caster' => [InstanceCastable::class, [CoordinateCast::class]],
    'a Castable returning its caster\'s class name' => [CountingCastable::class, [CoordinateCast::class]],
    'a Castable that is a caster too, which Laravel asks castUsing() first' => [ReceiverDualCast::class, [CoordinateCast::class]],
    'a Castable whose caster only a call could know' => [ArgumentCastable::class, null],
    'AsCollection, whose caster is anonymous' => [AsCollection::class, null],
    'a built-in cast' => ['array', null],
    'an enum' => [Status::class, null],
]);

test('a cast declares a non-null get() only when every caster\'s native get() return excludes null', function (string $cast, bool $expected) {
    expect(CastCasters::getDeclaresNonNull($cast))->toBe($expected);
})->with([
    'a caster whose get() returns a class' => [CoordinateCast::class, true],
    'a caster whose get() may return null' => [GaugeCardCast::class, false],
    'a Castable declaring a non-null caster' => [DeclaredCastable::class, true],
    'a Castable whose caster may return null' => [GaugeCard::class, false],
    'a Castable whose caster only a call could know' => [ArgumentCastable::class, false],
    'a serializing caster whose serialize() declares a non-null type' => [SerializedLabelCast::class, true],
    'a serializing caster whose serialize() may return null' => [NullableSerializedLabelCast::class, false],
    'a serializing caster whose serialize() declares nothing' => [BlankToNullSerializeCast::class, false],
    'a caster that is inbound too, whose get() Laravel never calls' => [InboundDualCast::class, false],
    'a serialize() method declaring nothing, without the interface' => [NoInterfaceSerializeCast::class, false],
    'a serialize() method declaring a non-null type, without the interface' => [NoInterfaceTypedSerializeCast::class, true],
    'AsCollection' => [AsCollection::class, false],
    'a built-in cast' => ['array', false],
]);
