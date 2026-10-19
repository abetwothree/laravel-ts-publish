<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures;

use Carbon\CarbonInterval;
use DateTime;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Auth\User as AuthUser;
use Illuminate\Support\Carbon;
use Workbench\App\Models\Post;

/** Static factories a class-held call path reads: dates, models no generated file exports, and published controls. */
final class CallPathFactory
{
    /** A plain DateTime, which json_encode() writes as `{date, timezone_type, timezone}`. */
    public static function plainDate(): DateTime
    {
        return new DateTime('2026-01-01 00:00:00');
    }

    /** A CarbonInterval, which json_encode() writes as DateInterval's public properties. */
    public static function interval(): CarbonInterval
    {
        return CarbonInterval::day();
    }

    /** A Carbon, which json_encode() writes as its ISO string. */
    public static function carbon(): Carbon
    {
        return Carbon::parse('2026-01-01');
    }

    /** The framework's abstract base model. */
    public static function baseModel(): Model
    {
        return new AuthUser;
    }

    /** A concrete framework model, which no generated file exports. */
    public static function authUser(): AuthUser
    {
        return new AuthUser;
    }

    /** An abstract application model. */
    public static function abstractModel(): ?CallPathAbstractModel
    {
        return null;
    }

    /** A Stringable whose jsonSerialize() can write null. */
    public static function nullableText(): CallPathNullableText
    {
        return new CallPathNullableText;
    }

    /** A published model: the control. */
    public static function post(): Post
    {
        return new Post;
    }
}
