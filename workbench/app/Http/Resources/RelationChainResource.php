<?php

declare(strict_types=1);

namespace Workbench\App\Http\Resources;

use AbeTwoThree\LaravelTsPublish\EnumResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Team;

/**
 * Exercises collection method chains rooted at a many-relation
 * ($this->members->take(5)->map(...)->values()), plus the ->map->only()
 * HigherOrderCollectionProxy reached directly off the relation.
 *
 * @mixin Team
 */
class RelationChainResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            // Identity-only chain: no map(), stays the relation's element type.
            'first_members' => $this->members->take(5)->values(),

            // map() with an inline array body → inline object shape, array-wrapped.
            'member_cards' => $this->members->take(5)->map(fn ($member) => [
                'id' => $member->id,
                'name' => $member->name,
            ])->values(),

            // map() body embeds an enum-cast column (Role, via the mapped $member) AND a
            // model-backed relation ($this->owner, resolved against the OUTER resource's
            // model since arrow functions capture $this) — proves embeddedEnumFqcns and
            // embeddedModelFqcns both propagate through the chain analyzer's ...$bodyResult
            // spread, not just embeddedResourceFqcns.
            'member_profiles' => $this->members->take(5)->map(fn ($member) => [
                'id' => $member->id,
                'role' => $member->role,
                'owner' => $this->owner,
            ])->values(),

            // ->map->only(): the HigherOrderCollectionProxy reached directly off the relation,
            // with no whenLoaded closure param involved — the receiver is $this->members itself.
            'member_map_only' => $this->members->map->only(['id', 'role']),

            // pluck() after the relation root → the plucked column's type, array-wrapped.
            'member_emails' => $this->members->pluck('email'),

            // pluck() on a NULLABLE column → the union must be parenthesized before the
            // '[]' suffix (RoleType | null)[], not RoleType | null[] (which TypeScript
            // parses as RoleType | (null[]) — genuinely wrong, not merely ugly).
            'member_roles' => $this->members->pluck('role'),

            // map() body is ENTIRELY EnumResource::make(...) — no array literal wrapping — so the
            // result carries a singular 'enumFqcn' (not 'directEnumFqcn'), array-wrapped by the
            // chain analyzer. ResourceTransformer's tolki rewrite must render it as a collection
            // (AsEnum<typeof Role>[]), not collapse it to a singular AsEnum<typeof Role>: each
            // element really is wrapped by EnumResource::make(), so the JSON is an array of
            // flattened enum objects.
            'member_role_resources' => $this->members->take(5)->map(fn ($member) => EnumResource::make($member->role))->values(),

            // filter() clears sequential keys, so the map body ends up keyedObjectArm()-wrapped:
            // RoleType[] | Record<string, RoleType>. ResourceTransformer::rewriteEnumResourceTypes()
            // substitutes the bare RoleType token in place of rebuilding, so both the array arm and
            // the keyed Record arm come out AsEnum-wrapped: AsEnum<typeof Role>[] | Record<string,
            // AsEnum<typeof Role>> — not the bare, un-rewritten RoleType this used to collapse to.
            'member_role_resources_filtered' => $this->members->filter(fn ($member) => $member->id > 1)
                ->map(fn ($member) => EnumResource::make($member->role)),

            // Same filter()+map() shape, nested inside an inline array literal this time.
            // analyzeInlineArray() substitutes the bare RoleType token in place rather than
            // rebuilding the type, so the keyed Record arm survives the wrap here exactly as it
            // does at the top level above: { roles: AsEnum<typeof Role>[] | Record<string,
            // AsEnum<typeof Role>> }. The two properties must never disagree — identical PHP.
            'wrapped_filtered' => [
                'roles' => $this->members->filter(fn ($member) => $member->id > 1)
                    ->map(fn ($member) => EnumResource::make($member->role)),
            ],

            // map() argument is a string callable: it is called with each member, as a closure is,
            // never read as the plain string literal 'strtoupper' itself.
            'member_names_upper' => $this->members->take(5)->map('strtoupper'),

            // map() argument is an array callable ([$this, 'method']): the method is called with each member.
            'member_formatted' => $this->members->take(5)->map([$this, 'formatMember']),

            // map()/pluck() as a first-class callable: a Closure, which json_encode() sends as {}. PHP-Parser's
            // getArgs() asserts !isFirstClassCallable() and throws under zend.assertions=1, so it must be rejected
            // before analyzeVariablePluckCall() or the map body analysis ever calls getArgs().
            'member_mapped_fcc' => $this->members->map(...),
            'member_plucked_fcc' => $this->members->pluck(...),

            // first() as the outermost, argless op terminates the chain with a single element or null.
            'first_member' => $this->members->take(5)->first(),

            // load()/loadMissing() are identity ops too (return $this unchanged) — an intervening
            // load() must not break sequential keys, nor block first() terminal recognition below it.
            'members_after_load' => $this->members->load('profile')->values(),
            'first_member_after_load' => $this->members->load('profile')->first(),

            // Key-preserving ops with no values() to reindex: json_encode serializes a gapped or
            // reordered Collection as an OBJECT, so the array type alone would be wrong.
            'members_sorted' => $this->members->sortBy('name'),
            'members_filtered_cards' => $this->members->filter(fn ($member) => $member->id > 1)->map(fn ($member) => [
                'id' => $member->id,
            ]),
            'members_tail' => $this->members->take(-2),
            'members_tail_values' => $this->members->take(-2)->values(),
            'members_sliced_emails' => $this->members->pluck('email')->sortBy(fn (string $email) => $email),
            'members_keyed_by_id' => $this->members->pluck('email', 'id'),

            // values() at the end of a key-preserving chain restores 0..n-1, so these stay arrays.
            'members_skipped' => $this->members->skip(2)->values(),
        ];
    }

    public function formatMember(mixed $member): string
    {
        return (string) $member->name;
    }
}
