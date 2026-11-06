<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Models\Grade as FixtureGrade;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Enums\Status;
use Workbench\App\Models\Comment;
use Workbench\App\Models\Grade;
use Workbench\App\Models\Post;
use Workbench\App\Support\Opaque;

/**
 * Locals whose inline `@var` types a shape member, or a list, that a dropped untypable arm left `null` or `never[]`.
 *
 * @mixin Post
 */
final class DroppedMemberVarResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        /** @var array{a: string|null, b: int} $filled */
        $filled = ['a' => $this->id > 0 ? Opaque::value('s') : null, 'b' => 1];
        /** @var array{a: string, b: int} $unadmitted */
        $unadmitted = ['a' => $this->id > 0 ? Opaque::value('s') : null, 'b' => 1];
        /** @var array{a: string|null, b: int} $both */
        $both = ['a' => $this->id > 0 ? Opaque::value('s') : null, 'b' => $this->id > 0 ? Opaque::value(1) : null];
        /** @var array{a: string|null, b: int|null} $literal */
        $literal = ['a' => null, 'b' => $this->id > 0 ? Opaque::value(1) : null];
        /** @var array{a: list<string>, b: int} $listMember */
        $listMember = ['a' => $this->id > 0 ? Opaque::value(['s']) : [], 'b' => 1];
        /** @var array{a: Comment|null, b: int} $model */
        $model = ['a' => $this->id > 0 ? Opaque::value($this->comments->first()) : null, 'b' => 1];
        /** @var list<string> $listed */
        $listed = $this->id > 0 ? Opaque::value(['s']) : [];
        /** @var list<string> $empty */
        $empty = [];
        /** @var string $unlisted */
        $unlisted = $this->id > 0 ? Opaque::value('s') : [];
        /** @var array{a: array{fn: callable(int): string}|null, b: int} $callable */
        $callable = ['a' => $this->id > 0 ? Opaque::value(['fn' => 'strlen']) : null, 'b' => 1];
        /** @var array{a: Status|null, b: int} $enum */
        $enum = ['a' => $this->id > 0 ? Opaque::value(Status::Published) : null, 'b' => 1];
        /** @var array{a: string|null, b: FixtureGrade|int} $unspelled */
        $unspelled = ['a' => $this->id > 0 ? Opaque::value('s') : null, 'b' => $this->grade()];
        /** @var array{a: FixtureGrade|null, b: Grade} $sameName */
        $sameName = ['a' => $this->id > 0 ? Opaque::value(null) : null, 'b' => $this->grade()];
        /** @var array{a: list<string>, b: int} $defaulted */
        $defaulted = ['a' => ($this->id > 0 ? Opaque::value('s') : null) ?? 'x', 'b' => 1];
        /** @var array{a: string|null, b: int}|array{a: int|null, b: int} $unionTag */
        $unionTag = ['a' => $this->id > 0 ? Opaque::value(1) : null, 'b' => 1];
        /** @var array{a: int|null, b: int}|array{a: string|null, b: int} $unionTagReversed */
        $unionTagReversed = ['a' => $this->id > 0 ? Opaque::value(1) : null, 'b' => 1];
        /** @var array<string, string|null>|array{a: int|null, b: string} $unionRecord */
        $unionRecord = ['a' => $this->id > 0 ? Opaque::value(1) : null, 'b' => 'x'];
        /** @var array{a: list<string>, b: int}|array{a: list<int>, b: int} $unionList */
        $unionList = ['a' => $this->id > 0 ? Opaque::value([1]) : [], 'b' => 1];
        /** @var array{a: string|null, b: int}|array{b: int} $unionLacking */
        $unionLacking = ['a' => $this->id > 0 ? Opaque::value('s') : null, 'b' => 1];
        /** @var array{a: string|null, b: int}|null $nullableTag */
        $nullableTag = ['a' => $this->id > 0 ? Opaque::value('s') : null, 'b' => 1];
        /** @var array{a: FixtureGrade|null, b: int}|array{a: Grade|null, b: int} $unionSameName */
        $unionSameName = ['a' => $this->id > 0 ? Opaque::value(null) : null, 'b' => 1];

        return [
            'filled' => $filled,
            'unadmitted' => $unadmitted,
            'both' => $both,
            'literal' => $literal,
            'list_member' => $listMember,
            'model' => $model,
            'listed' => $listed,
            'empty' => $empty,
            'unlisted' => $unlisted,
            'callable' => $callable,
            'enum' => $enum,
            'unspelled' => $unspelled,
            'same_name' => $sameName,
            'defaulted' => $defaulted,
            'union_tag' => $unionTag,
            'union_tag_reversed' => $unionTagReversed,
            'union_record' => $unionRecord,
            'union_list' => $unionList,
            'union_lacking' => $unionLacking,
            'nullable_tag' => $nullableTag,
            'union_same_name' => $unionSameName,
        ];
    }

    /** A grade of the workbench's, named like the test fixtures' own. */
    private function grade(): Grade
    {
        return new Grade;
    }
}
