<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures\Wrapped;

use DateTime;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Auth\User as AuthUser;

/** A plain class a resource wraps, whose methods the resource forwards. */
final class CallPathWrapped
{
    /** A string: the control. */
    public function label(): string
    {
        return '';
    }

    /** A static string: the control. */
    public static function staticLabel(): string
    {
        return '';
    }

    /** A plain DateTime. */
    public function date(): DateTime
    {
        return new DateTime;
    }

    /** The framework's abstract base model. */
    public function baseModel(): Model
    {
        return new AuthUser;
    }

    /** A concrete framework model. */
    public function authUser(): AuthUser
    {
        return new AuthUser;
    }

    /** A static plain DateTime. */
    public static function staticDate(): DateTime
    {
        return new DateTime;
    }

    /** A static framework base model. */
    public static function staticBaseModel(): Model
    {
        return new AuthUser;
    }
}
