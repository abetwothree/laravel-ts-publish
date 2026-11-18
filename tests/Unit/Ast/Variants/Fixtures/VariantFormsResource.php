<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Variants\Fixtures;

use AbeTwoThree\LaravelTsPublish\EnumResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use LogicException;
use Workbench\App\Enums\Priority;
use Workbench\App\Enums\Status;
use Workbench\App\Enums\Visibility;
use Workbench\App\Http\Resources\UserResource;
use Workbench\App\Models\Post;

/**
 * One method per DiscriminantFormsResource cell, named for it, each returning that cell's literal at its top level,
 * beside the shapes that make no group.
 *
 * @mixin Post
 */
final class VariantFormsResource extends JsonResource
{
    /**
     * The keys.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [];
    }

    /**
     * A negated read.
     *
     * @return array<string, mixed>
     */
    public function negated(): array
    {
        return ['pinned' => $this->is_pinned, 'v' => ! $this->is_pinned ? $this->published_at : $this->id];
    }

    /**
     * A read compared with `true`.
     *
     * @return array<string, mixed>
     */
    public function strictTrue(): array
    {
        return ['pinned' => $this->is_pinned, 'v' => $this->is_pinned === true ? $this->title : null];
    }

    /**
     * A read compared with `false`.
     *
     * @return array<string, mixed>
     */
    public function strictFalse(): array
    {
        return ['pinned' => $this->is_pinned, 'v' => $this->is_pinned === false ? $this->rating : $this->id];
    }

    /**
     * An enum read compared with a case.
     *
     * @return array<string, mixed>
     */
    public function caseCompare(): array
    {
        return ['status' => $this->status, 'v' => $this->status === Status::Published ? $this->published_at : null];
    }

    /**
     * An enum read compared as not a case.
     *
     * @return array<string, mixed>
     */
    public function caseNotEqual(): array
    {
        return ['status' => $this->status, 'v' => $this->status !== Status::Draft ? $this->title : $this->id];
    }

    /**
     * An enum's `->value` beside a match on the enum.
     *
     * @return array<string, mixed>
     */
    public function valueRead(): array
    {
        return [
            'status' => $this->status->value,
            'v' => match ($this->status) {
                Status::Draft => $this->updated_at,
                Status::Published => $this->id,
            },
        ];
    }

    /**
     * A pure enum matched with a default.
     *
     * @return array<string, mixed>
     */
    public function unitEnumDefault(): array
    {
        return [
            'visibility' => $this->visibility,
            'v' => match ($this->visibility) {
                Visibility::Public => $this->title,
                default => null,
            },
        ];
    }

    /**
     * A `match (true)` on an enum read.
     *
     * @return array<string, mixed>
     */
    public function matchTrue(): array
    {
        return [
            'status' => $this->status,
            'v' => match (true) {
                $this->status === Status::Draft => $this->updated_at,
                default => $this->id,
            },
        ];
    }

    /**
     * A when() on a read.
     *
     * @return array<string, mixed>
     */
    public function whenDependent(): array
    {
        return ['pinned' => $this->is_pinned, 'until' => $this->when($this->is_pinned, $this->updated_at)];
    }

    /**
     * A when() on a read whose value is a closure.
     *
     * @return array<string, mixed>
     */
    public function whenClosure(): array
    {
        return ['pinned' => $this->is_pinned, 'until' => $this->when($this->is_pinned, fn () => $this->id)];
    }

    /**
     * An unless() on a read with a default.
     *
     * @return array<string, mixed>
     */
    public function unlessDefault(): array
    {
        return ['pinned' => $this->is_pinned, 'v' => $this->unless($this->is_pinned, $this->published_at, $this->id)];
    }

    /**
     * Two keys on one read, around it.
     *
     * @return array<string, mixed>
     */
    public function twoDependents(): array
    {
        return [
            'a' => $this->is_pinned ? $this->title : null,
            'pinned' => $this->is_pinned,
            'b' => $this->is_pinned ? null : $this->rating,
        ];
    }

    /**
     * A ternary whose other arm is a ternary on another read.
     *
     * @return array<string, mixed>
     */
    public function nestedTernary(): array
    {
        return [
            'pinned' => $this->is_pinned,
            'v' => $this->is_pinned ? $this->title : ($this->rating !== null ? $this->rating : $this->id),
        ];
    }

    /**
     * Two reads, each with a key of its own.
     *
     * @return array<string, mixed>
     */
    public function twoDiscriminants(): array
    {
        return [
            'pinned' => $this->is_pinned,
            'status' => $this->status,
            'a' => $this->is_pinned ? $this->title : null,
            'b' => $this->status === Status::Draft ? $this->updated_at : $this->id,
        ];
    }

    /**
     * A match whose default throws.
     *
     * @return array<string, mixed>
     */
    public function throwArm(): array
    {
        return [
            'priority' => $this->priority,
            'v' => match ($this->priority) {
                Priority::Low => $this->title,
                Priority::High => $this->id,
                default => throw new LogicException('Unsupported priority.'),
            },
        ];
    }

    /**
     * A group beside keys no read picks.
     *
     * @return array<string, mixed>
     */
    public function sharedKeys(): array
    {
        return [
            'id' => $this->id,
            'pinned' => $this->is_pinned,
            'v' => $this->is_pinned ? $this->title : $this->id,
            'title' => $this->title,
        ];
    }

    /**
     * A read cast before it is sent.
     *
     * @return array<string, mixed>
     */
    public function castDiscriminantKept(): array
    {
        return ['pinned' => (bool) $this->is_pinned, 'v' => $this->is_pinned ? $this->title : null];
    }

    /**
     * A method call sent and tested.
     *
     * @return array<string, mixed>
     */
    public function methodConditionKept(): array
    {
        return ['featured' => $this->isFeatured(), 'v' => $this->isFeatured() ? $this->title : null];
    }

    /**
     * A test on a read the literal does not send.
     *
     * @return array<string, mixed>
     */
    public function unpublishedConditionKept(): array
    {
        return ['id' => $this->id, 'v' => $this->is_pinned ? $this->title : null];
    }

    /**
     * A test on a read with no finite set of values.
     *
     * @return array<string, mixed>
     */
    public function stringDiscriminantKept(): array
    {
        return ['title' => $this->title, 'v' => $this->title === 'x' ? $this->id : null];
    }

    /**
     * An enum sent wrapped in an EnumResource.
     *
     * @return array<string, mixed>
     */
    public function wrappedDiscriminantKept(): array
    {
        return [
            'status' => EnumResource::make($this->status),
            'v' => $this->status === Status::Draft ? $this->id : null,
        ];
    }

    /**
     * A read sent only sometimes.
     *
     * @return array<string, mixed>
     */
    public function optionalDiscriminantKept(): array
    {
        return [
            'pinned' => $this->when($this->id > 1, $this->is_pinned),
            'v' => $this->is_pinned ? $this->title : null,
        ];
    }

    /**
     * A test on a read and another read.
     *
     * @return array<string, mixed>
     */
    public function undecidableKept(): array
    {
        return ['pinned' => $this->is_pinned, 'v' => $this->is_pinned && $this->title !== '' ? $this->title : null];
    }

    /**
     * A test whose arms send the same type.
     *
     * @return array<string, mixed>
     */
    public function sameArmsKept(): array
    {
        return ['pinned' => $this->is_pinned, 'v' => $this->is_pinned ? $this->id : $this->user_id];
    }

    /**
     * A test with an arm no rule types.
     *
     * @return array<string, mixed>
     */
    public function untypableArmKept(): array
    {
        return ['pinned' => $this->is_pinned, 'v' => $this->is_pinned ? json_decode($this->title) : null];
    }

    /**
     * A test whose arm leaves out an arm no rule types.
     *
     * @return array<string, mixed>
     */
    public function droppedArmKept(): array
    {
        return [
            'pinned' => $this->is_pinned,
            'v' => $this->is_pinned ? ($this->id > 1 ? json_decode($this->title) : null) : $this->id,
        ];
    }

    /**
     * A local holding an enum's scalar, which PHP never finds identical to a case.
     *
     * @return array<string, mixed>
     */
    public function scalarLocalKept(): array
    {
        $s = $this->status->value;

        return ['s' => $s, 'v' => $s === Status::Draft ? $this->title : null];
    }

    /**
     * A nullable enum's `->value` read with `?->` beside a match with a `null` arm.
     *
     * @return array<string, mixed>
     */
    public function nullsafeValue(): array
    {
        return [
            'p' => $this->priority?->value,
            'v' => match ($this->priority) {
                Priority::Low => $this->title,
                null => null,
                default => $this->id,
            },
        ];
    }

    /**
     * The strict_true literal under a `@return` whose fill makes `v` optional.
     *
     * @return array{pinned: bool, v?: string}
     */
    public function docblockFilled(): array
    {
        return ['pinned' => $this->is_pinned, 'v' => $this->is_pinned === true ? $this->title : null];
    }

    /**
     * The strict_true literal under a `@return` that types `v` narrower than the body, which keeps the body's arms.
     *
     * @return array{pinned: bool, v: string}
     */
    public function docblockAgreeing(): array
    {
        return ['pinned' => $this->is_pinned, 'v' => $this->is_pinned === true ? $this->title : null];
    }

    /**
     * The strict_true literal beside an index signature.
     *
     * @return array<string, mixed>
     */
    public function indexSignature(): array
    {
        return [
            'pinned' => $this->is_pinned,
            'v' => $this->is_pinned === true ? $this->title : null,
            "k_{$this->id}" => 1,
        ];
    }

    /**
     * A spread that writes a key a read picks.
     *
     * @return array<string, mixed>
     */
    public function spreadWrites(): array
    {
        return [...$this->extras(), 'pinned' => $this->is_pinned, 'v' => $this->is_pinned === true ? $this->title : null];
    }

    /**
     * The strict_true literal after a guard's empty return.
     *
     * @return array<string, mixed>
     */
    public function twoReturns(): array
    {
        if ($this->id < 1) {
            return [];
        }

        return ['pinned' => $this->is_pinned, 'v' => $this->is_pinned === true ? $this->title : null];
    }

    /**
     * A local holding a case, which a body fallback prints as its scalar.
     *
     * @return array<string, mixed>
     */
    public function casesAsLiterals(): array
    {
        $s = $this->id > 1 ? Status::Draft : Status::Published;

        return ['s' => $s, 'x' => $s ? 'a' : 1];
    }

    /**
     * The strict_true literal after a spread of a resource, read as a nested literal.
     *
     * @return array<string, mixed>
     */
    public function spreadArmLeads(): array
    {
        return [
            ...UserResource::make($this->author)->resolve(),
            'pinned' => $this->is_pinned,
            'v' => $this->is_pinned === true ? $this->title : null,
        ];
    }

    /**
     * A merge of keys no rule reads before the keys, which filter() lifts over them.
     *
     * @return array<string, mixed>
     */
    public function mergeParentKept(Request $request): array
    {
        return [
            $this->merge(parent::toArray($request)),
            'status' => $this->status,
            'published_at' => $this->status === Status::Published ? $this->published_at : null,
        ];
    }

    /**
     * A read assigned between the key that sends it and the key that tests it.
     *
     * @return array<string, mixed>
     */
    public function mutateBetweenKept(): array
    {
        return ['pinned' => $this->is_pinned, 'x' => $this->is_pinned = ! $this->is_pinned, 'v' => $this->is_pinned ? $this->title : null];
    }

    /**
     * A read assigned before the key that sends it.
     *
     * @return array<string, mixed>
     */
    public function mutateBefore(): array
    {
        return ['x' => $this->is_pinned = ! $this->is_pinned, 'pinned' => $this->is_pinned, 'v' => $this->is_pinned ? $this->title : null];
    }

    /**
     * Two keys sending one read, the first before an assignment of it, the second after.
     *
     * @return array<string, mixed>
     */
    public function ownedAfterDecline(): array
    {
        return [
            'a' => $this->is_pinned,
            'x' => $this->is_pinned = ! $this->is_pinned,
            'b' => $this->is_pinned,
            'v' => $this->is_pinned ? $this->title : null,
        ];
    }

    /**
     * Nine ternaries on one read, each in the true arm of the one around it.
     *
     * @return array<string, mixed>
     */
    public function nineLevels(): array
    {
        return [
            'pinned' => $this->is_pinned,
            'v' => $this->is_pinned ? ($this->is_pinned ? ($this->is_pinned ? ($this->is_pinned ? ($this->is_pinned
                ? ($this->is_pinned ? ($this->is_pinned ? ($this->is_pinned ? ($this->is_pinned ? $this->title : $this->id)
                    : null) : null) : null) : null) : null) : null) : null) : null,
        ];
    }

    /**
     * One keyed item, whose value picks between arms.
     *
     * @return array<string, mixed>
     */
    public function oneKey(): array
    {
        return ['v' => $this->is_pinned ? $this->title : null];
    }

    /**
     * Keyed items none of whose values picks between arms.
     *
     * @return array<string, mixed>
     */
    public function plainKeys(): array
    {
        return ['pinned' => $this->is_pinned, 'title' => $this->title];
    }

    /**
     * The keys spreadWrites() spreads.
     *
     * @return array<string, mixed>
     */
    private function extras(): array
    {
        return ['v' => 1];
    }
}
