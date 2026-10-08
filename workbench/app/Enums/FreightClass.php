<?php

declare(strict_types=1);

namespace Workbench\App\Enums;

use AbeTwoThree\LaravelTsPublish\Attributes\TsEnumMethod;
use AbeTwoThree\LaravelTsPublish\Attributes\TsEnumStaticMethod;
use Carbon\CarbonImmutable;
use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Support\Collection;
use Workbench\App\ValueObjects\RateCard;
use Workbench\App\ValueObjects\SealedManifest;
use Workbench\App\ValueObjects\TrackingCode;

/**
 * Freight classes whose methods return objects, each published as json_encode() writes it.
 */
enum FreightClass: string
{
    case Standard = 'standard';
    case Express = 'express';

    #[TsEnumMethod(description: 'Rate card for the class')]
    public function rateCard(): RateCard
    {
        return new RateCard($this === self::Standard ? 100 : 250);
    }

    #[TsEnumMethod(description: 'Daily pickup cutoff')]
    public function cutoff(): CarbonImmutable
    {
        return CarbonImmutable::parse($this === self::Standard ? '2026-01-01 17:00:00' : '2026-01-01 20:00:00', 'UTC');
    }

    #[TsEnumMethod]
    public function tracking(): TrackingCode
    {
        return new TrackingCode($this->value);
    }

    /** @return Collection<int, string> */
    #[TsEnumMethod]
    public function zones(): Collection
    {
        return collect($this === self::Standard ? ['north', 'south'] : ['north']);
    }

    #[TsEnumMethod]
    public function firstPickup(): DateTimeImmutable
    {
        return new DateTimeImmutable('2026-01-01 09:00:00', new DateTimeZone('UTC'));
    }

    #[TsEnumMethod]
    public function manifest(): SealedManifest
    {
        return new SealedManifest;
    }

    #[TsEnumStaticMethod]
    public static function defaultRate(): RateCard
    {
        return new RateCard(50);
    }
}
