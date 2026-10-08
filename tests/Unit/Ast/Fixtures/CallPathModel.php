<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures;

use DateTime;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as AuthUser;

/** A backing model whose methods a resource forwards, reads off itself and reads off a relation. */
final class CallPathModel extends Model
{
    protected $table = 'posts';

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

    /** A string the resource's `mixed` declaration of the same name returns. */
    public function shadowedLabel(): string
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

    /**
     * A relation to another of its own kind, whose methods a nullsafe chain or a whenLoaded() closure reads.
     *
     * @return BelongsTo<self, $this>
     */
    public function sibling(): BelongsTo
    {
        return $this->belongsTo(self::class, 'sibling_id');
    }
}
