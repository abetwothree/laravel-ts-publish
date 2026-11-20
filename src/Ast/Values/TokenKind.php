<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Ast\Values;

use AbeTwoThree\LaravelTsPublish\Ast\Contracts\ExpressionHandler;
use AbeTwoThree\LaravelTsPublish\Facades\TsNaming;
use AbeTwoThree\LaravelTsPublish\Support\ClassTokenQueue;
use AbeTwoThree\LaravelTsPublish\Support\TsTypeString;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A kind of token a published type spells a class with, and the channels of a result that queue the classes behind it.
 *
 * @phpstan-import-type ValueExpressionResult from ExpressionHandler
 *
 * @internal
 */
enum TokenKind: string
{
    /** A model, spelled by its basename. */
    case Model = 'models';

    /** An API resource, spelled by its published name. */
    case Resource = 'resources';

    /** An enum read directly, spelled by its type and, when backed, its `…Kind`. */
    case Enum = 'enums';

    /** An enum a wrap sends, spelled by its const after `typeof`. */
    case WrapConst = 'consts';

    /**
     * The kind of channel a class is queued on: an enum's, a model's or an API resource's; null for any other.
     *
     * @return TokenKind::Enum|TokenKind::Model|TokenKind::Resource|null
     */
    public static function ofClass(string $fqcn): ?self
    {
        return match (true) {
            enum_exists($fqcn) => self::Enum,
            is_a($fqcn, Model::class, true) => self::Model,
            is_a($fqcn, JsonResource::class, true) => self::Resource,
            default => null,
        };
    }

    /**
     * The name a class's token of this kind is spelled with.
     */
    public function nameOf(string $fqcn): string
    {
        return match ($this) {
            self::Model => class_basename($fqcn),
            self::Resource => TsNaming::resourceTypeName($fqcn),
            self::Enum => ValueResult::enumTypeName($fqcn),
            self::WrapConst => ValueResult::enumConstName($fqcn),
        };
    }

    /**
     * The names beside nameOf() a class's tokens of this kind are spelled with: a backed enum's `…Kind`.
     *
     * @return list<string>
     */
    public function siblingNames(string $fqcn): array
    {
        return $this === self::Enum ? ValueResult::enumSiblingNames($fqcn) : [];
    }

    /**
     * The pattern a token of this kind follows: a `typeof` for a const, none for a type.
     */
    public function anchor(): string
    {
        return $this === self::WrapConst ? TsTypeString::TYPEOF_ANCHOR : '';
    }

    /**
     * The classes a result queues for this kind's tokens: its single channel, then its embedded one.
     *
     * @param  ValueExpressionResult  $result
     * @return list<class-string>
     */
    public function channelFqcns(array $result): array
    {
        [$single, $embedded] = match ($this) {
            self::Model => [$result['modelFqcn'] ?? null, $result['embeddedModelFqcns'] ?? []],
            self::Resource => [$result['resourceFqcn'] ?? null, $result['embeddedResourceFqcns'] ?? []],
            self::Enum => [$result['directEnumFqcn'] ?? null, $result['embeddedEnumFqcns'] ?? []],
            self::WrapConst => [null, $result['embeddedEnumResourceFqcns'] ?? []],
        };

        return $single === null ? $embedded : [$single, ...$embedded];
    }

    /**
     * The queue of the classes behind a result's tokens of this kind, in the order its type spells them.
     *
     * @param  ValueExpressionResult  $result
     */
    public function queueOf(array $result): ClassTokenQueue
    {
        return new ClassTokenQueue(
            $this->channelFqcns($result),
            $this->nameOf(...),
            $this->siblingNames(...),
            $this->anchor(),
        );
    }
}
