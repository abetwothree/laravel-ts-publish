<?php

declare(strict_types=1);

use AbeTwoThree\LaravelTsPublish\ModelAttributeResolver;
use AbeTwoThree\LaravelTsPublish\Transformers\ResourceTransformer;
use Workbench\Accounting\Http\Resources\InvoiceResource;
use Workbench\App\Enums\Priority;
use Workbench\App\Enums\Status;
use Workbench\App\Http\Resources\AddressExtendsResource;
use Workbench\App\Http\Resources\AddressMixinResource;
use Workbench\App\Http\Resources\AddressResource;
use Workbench\App\Http\Resources\Admin\Store as AdminStoreResource;
use Workbench\App\Http\Resources\ApiPostResource;
use Workbench\App\Http\Resources\BodylessOrderResource;
use Workbench\App\Http\Resources\BodylessTeamResource;
use Workbench\App\Http\Resources\BranchedInlineFqcnResource;
use Workbench\App\Http\Resources\CategoryResource;
use Workbench\App\Http\Resources\ChildInlineFqcnResource;
use Workbench\App\Http\Resources\ChildSharedResource;
use Workbench\App\Http\Resources\CommentComposedResource;
use Workbench\App\Http\Resources\CommentResource;
use Workbench\App\Http\Resources\DelegatingWithMixinResource;
use Workbench\App\Http\Resources\EmptyResource;
use Workbench\App\Http\Resources\EmptyWithMixinResource;
use Workbench\App\Http\Resources\EnumCollectionResource;
use Workbench\App\Http\Resources\EventLogResource;
use Workbench\App\Http\Resources\FqcnMixinResource;
use Workbench\App\Http\Resources\ImageDelegatedResource;
use Workbench\App\Http\Resources\ImageDimensionsResource;
use Workbench\App\Http\Resources\ImageMorphResource;
use Workbench\App\Http\Resources\ImageNullableArmsResource;
use Workbench\App\Http\Resources\InheritedInlineFqcnResource;
use Workbench\App\Http\Resources\KpiResource;
use Workbench\App\Http\Resources\MediaTypeInstanceOfResource;
use Workbench\App\Http\Resources\MediaTypeResource;
use Workbench\App\Http\Resources\MediaTypeUnknownResource;
use Workbench\App\Http\Resources\MixedEnumMergedResource;
use Workbench\App\Http\Resources\MixedEnumReturnBranchesResource;
use Workbench\App\Http\Resources\NamedArgsConditionalResource;
use Workbench\App\Http\Resources\OrderResource;
use Workbench\App\Http\Resources\PostEnumTrioResource;
use Workbench\App\Http\Resources\PostFlatCollection;
use Workbench\App\Http\Resources\PostResource;
use Workbench\App\Http\Resources\PostSpotlightResource;
use Workbench\App\Http\Resources\ProductResource;
use Workbench\App\Http\Resources\ProfileResource;
use Workbench\App\Http\Resources\RelationChainResource;
use Workbench\App\Http\Resources\ResourceWrappedEnumResource;
use Workbench\App\Http\Resources\SameBasenameModelTrioResource;
use Workbench\App\Http\Resources\ServiceDeskResource;
use Workbench\App\Http\Resources\TeamStatusAuditResource;
use Workbench\App\Http\Resources\TernaryResource;
use Workbench\App\Http\Resources\ToArrayCastsResource;
use Workbench\App\Http\Resources\TraitSpreadCoverageResource;
use Workbench\App\Http\Resources\UserResource;
use Workbench\App\Http\Resources\WarehouseResource;
use Workbench\App\Models\Address;
use Workbench\App\Models\Admin\Store as AdminStore;
use Workbench\App\Models\Comment;
use Workbench\App\Models\Image;
use Workbench\App\Models\Kpi;
use Workbench\App\Models\Marketing\Report\Report as MarketingReport;
use Workbench\App\Models\Order;
use Workbench\App\Models\Post;
use Workbench\App\Models\Product;
use Workbench\App\Models\Sales\Report\Report as SalesReport;
use Workbench\App\Models\Team;
use Workbench\App\Models\TrackingEvent;
use Workbench\App\Models\User;
use Workbench\App\Models\Warehouse;
use Workbench\App\Resources\DirectResource;
use Workbench\Blog\Http\Resources\ApiArticleResource;
use Workbench\Crm\Http\Resources\DealEnumInlineResource;
use Workbench\Crm\Http\Resources\DealEnumTrioResource;
use Workbench\Crm\Http\Resources\DealResource;
use Workbench\Crm\Http\Resources\UserResource as CrmUserResource;
use Workbench\Crm\Models\User as CrmUser;

/**
 * Split a union type string on top-level ' | ' only, so a union nested inside a member's own
 * type (e.g. `role: RoleType | null`) is never mistaken for a top-level arm boundary.
 *
 * @return list<string>
 */
function splitTopLevelType(string $type): array
{
    $arms = [];
    $current = '';
    $depth = 0;

    for ($i = 0, $length = strlen($type); $i < $length; $i++) {
        $char = $type[$i];
        $depth += (int) ($char === '{' || $char === '<') - (int) ($char === '}' || $char === '>');

        if ($depth === 0 && substr($type, $i, 3) === ' | ') {
            $arms[] = $current;
            $current = '';
            $i += 2;

            continue;
        }

        $current .= $char;
    }

    $arms[] = $current;

    return $arms;
}

/**
 * Extract member names from one inline-object union arm. Throws on a model reference such as
 * `Pick<User, …>`, whose member set cannot be derived from the type string: returning an empty
 * list instead would make the caller's "does not contain member X" assertions pass vacuously.
 *
 * @return list<string>
 */
function relationFilterArmMembers(string $arm): array
{
    $arm = trim($arm);

    if (! str_starts_with($arm, '{')) {
        throw new RuntimeException("Cannot derive members from a model reference arm: {$arm}");
    }

    $inner = trim(substr($arm, 1, -1));

    if ($inner === '') {
        return [];
    }

    return array_map(
        fn (string $member): string => trim(explode(':', $member, 2)[0]),
        explode('; ', $inner)
    );
}

describe('ResourceTransformer with PostResource', function () {
    test('resolves model class from @mixin docblock', function () {
        $data = (new ResourceTransformer(PostResource::class))->data();

        expect($data->modelClass)->toBe(Post::class);
    });

    test('transforms resource name', function () {
        $data = (new ResourceTransformer(PostResource::class))->data();

        expect($data->resourceName)->toBe('PostResource');
    });

    test('transforms basic property types from model columns', function () {
        $data = (new ResourceTransformer(PostResource::class))->data();

        expect($data->properties)
            ->toHaveKey('id')
            ->toHaveKey('title')
            ->toHaveKey('content');

        expect($data->properties['id']['type'])->toBe('number');
        expect($data->properties['title']['type'])->toBe('string');
        expect($data->properties['content']['type'])->toBe('string');
    });

    test('resolves EnumResource::make() to AsEnum type with tolki enabled', function () {
        $data = (new ResourceTransformer(PostResource::class))->data();

        expect($data->properties['status']['type'])->toBe('AsEnum<typeof Status>');
        expect($data->properties['visibility']['type'])->toBe('AsEnum<typeof Visibility> | null');
        expect($data->properties['priority']['type'])->toBe('AsEnum<typeof Priority> | null');
    });

    test('resolves new EnumResource() to AsEnum type with tolki enabled', function () {
        $data = (new ResourceTransformer(PostResource::class))->data();

        expect($data->properties['status_new']['type'])->toBe('AsEnum<typeof Status>');
        expect($data->properties['visibility_new']['type'])->toBe('AsEnum<typeof Visibility> | null');
        expect($data->properties['priority_new']['type'])->toBe('AsEnum<typeof Priority> | null');
    });

    test('resolves EnumResource::make() to enum type with tolki disabled', function () {
        config()->set('ts-publish.enums.use_tolki_package', false);

        $data = (new ResourceTransformer(PostResource::class))->data();

        expect($data->properties['status']['type'])->toBe('StatusType');
        expect($data->properties['visibility']['type'])->toBe('VisibilityType | null');
        expect($data->properties['priority']['type'])->toBe('PriorityType | null');
    });

    test('resolves new EnumResource() to enum type with tolki disabled', function () {
        config()->set('ts-publish.enums.use_tolki_package', false);

        $data = (new ResourceTransformer(PostResource::class))->data();

        expect($data->properties['status_new']['type'])->toBe('StatusType');
        expect($data->properties['visibility_new']['type'])->toBe('VisibilityType | null');
        expect($data->properties['priority_new']['type'])->toBe('PriorityType | null');
    });

    test('marks basic properties as non-optional', function () {
        $data = (new ResourceTransformer(PostResource::class))->data();

        expect($data->properties['id']['optional'])->toBeFalse();
        expect($data->properties['title']['optional'])->toBeFalse();
        expect($data->properties['status']['optional'])->toBeFalse();
        expect($data->properties['status_new']['optional'])->toBeFalse();
    });

    test('generates correct filename', function () {
        $transformer = new ResourceTransformer(PostResource::class);

        expect($transformer->filename())->toBe('post-resource');
    });

    test('filePath contains Resources and PostResource', function () {
        $data = (new ResourceTransformer(PostResource::class))->data();

        expect($data->filePath)
            ->toContain('Resources')
            ->toContain('PostResource.php')
            ->not->toStartWith('/');
    });

    // cast, mixin method, and resolve() expressions ————————————

    test('(bool) cast resolves to boolean', function () {
        $data = (new ResourceTransformer(PostResource::class))->data();

        expect($data->properties['published']['type'])->toBe('boolean');
        expect($data->properties['published']['optional'])->toBeFalse();
    });

    test('(int) cast resolves to number', function () {
        $data = (new ResourceTransformer(PostResource::class))->data();

        expect($data->properties['rating_display']['type'])->toBe('number');
        expect($data->properties['rating_display']['optional'])->toBeFalse();
    });

    test('(string) cast resolves to string', function () {
        $data = (new ResourceTransformer(PostResource::class))->data();

        expect($data->properties['word_count']['type'])->toBe('string');
        expect($data->properties['word_count']['optional'])->toBeFalse();
    });

    test('(array) cast of an inline array literal preserves its shape', function () {
        $data = (new ResourceTransformer(PostResource::class))->data();

        expect($data->properties['heading_content']['type'])->toBe('{ title: string; summary: string }');
        expect($data->properties['heading_content']['optional'])->toBeFalse();
    });

    test('@mixin method with return type — publishable resolves to boolean', function () {
        $data = (new ResourceTransformer(PostResource::class))->data();

        expect($data->properties['publishable']['type'])->toBe('boolean');
        expect($data->properties['publishable']['optional'])->toBeFalse();
    });

    test('@mixin method via $this->resource — comments_count resolves to number', function () {
        $data = (new ResourceTransformer(PostResource::class))->data();

        expect($data->properties['comments_count']['type'])->toBe('number');
        expect($data->properties['comments_count']['optional'])->toBeFalse();
    });

    test('@mixin method with docblock only — is_featured resolves to boolean', function () {
        $data = (new ResourceTransformer(PostResource::class))->data();

        expect($data->properties['is_featured']['type'])->toBe('boolean');
        expect($data->properties['is_featured']['optional'])->toBeFalse();
    });

    test('nullsafe relation method in whenLoaded closure — category_is_first resolves to boolean|null', function () {
        $data = (new ResourceTransformer(PostResource::class))->data();

        expect($data->properties['category_is_first']['type'])->toBe('boolean | null');
        expect($data->properties['category_is_first']['optional'])->toBeTrue();
    });

    test('nullsafe relation method via $this->resource in whenLoaded closure — category_is_active resolves to boolean|null', function () {
        $data = (new ResourceTransformer(PostResource::class))->data();

        expect($data->properties['category_is_active']['type'])->toBe('boolean | null');
        expect($data->properties['category_is_active']['optional'])->toBeTrue();
    });

    test('resource collection with ->resolve() — comments_resolved resolves to CommentResource[]', function () {
        $data = (new ResourceTransformer(PostResource::class))->data();

        expect($data->properties['comments_resolved']['type'])->toBe('CommentResource[]');
        expect($data->properties['comments_resolved']['optional'])->toBeTrue();
    });

    // static method call expressions ———————————————————————————————

    test('$this::staticMethod() resolves return type — post_class_name', function () {
        $data = (new ResourceTransformer(PostResource::class))->data();

        expect($data->properties['post_class_name']['type'])->toBe('string');
        expect($data->properties['post_class_name']['optional'])->toBeFalse();
    });

    test('$this->resource::staticMethod() resolves return type — post_table_name', function () {
        $data = (new ResourceTransformer(PostResource::class))->data();

        expect($data->properties['post_table_name']['type'])->toBe('string');
        expect($data->properties['post_table_name']['optional'])->toBeFalse();
    });

    test('relation::staticMethod() in whenLoaded closure resolves return type — category_class_name', function () {
        $data = (new ResourceTransformer(PostResource::class))->data();

        expect($data->properties['category_class_name']['type'])->toBe('string');
        expect($data->properties['category_class_name']['optional'])->toBeTrue();
    });

    test('resource->relation::staticMethod() in whenLoaded closure resolves return type — category_table_name', function () {
        $data = (new ResourceTransformer(PostResource::class))->data();

        expect($data->properties['category_table_name']['type'])->toBe('string');
        expect($data->properties['category_table_name']['optional'])->toBeTrue();
    });
});

describe('ResourceTransformer with UserResource', function () {
    test('resolves model class from #[TsResource(model:)] attribute', function () {
        $data = (new ResourceTransformer(UserResource::class))->data();

        expect($data->modelClass)->toBe(User::class);
    });

    test('resolves description from docblock', function () {
        $data = (new ResourceTransformer(UserResource::class))->data();

        expect($data->description)->toBe('User account resource.');
    });

    test('transforms whenLoaded as optional with relation type', function () {
        $data = (new ResourceTransformer(UserResource::class))->data();

        expect($data->properties['profile'])
            ->toHaveKey('type')
            ->toHaveKey('optional');

        expect($data->properties['profile']['optional'])->toBeTrue();
    });

    test('transforms whenHas as optional', function () {
        $data = (new ResourceTransformer(UserResource::class))->data();

        expect($data->properties['phone']['optional'])->toBeTrue();
    });

    test('transforms whenNotNull as optional', function () {
        $data = (new ResourceTransformer(UserResource::class))->data();

        expect($data->properties['avatar']['optional'])->toBeTrue();
    });

    test('transforms whenCounted as optional number', function () {
        $data = (new ResourceTransformer(UserResource::class))->data();

        expect($data->properties['posts_count']['type'])->toBe('number');
        expect($data->properties['posts_count']['optional'])->toBeTrue();
        expect($data->properties['comments_count']['type'])->toBe('number');
        expect($data->properties['comments_count']['optional'])->toBeTrue();
    });

    test('resolves EnumResource::make() to AsEnum type', function () {
        $data = (new ResourceTransformer(UserResource::class))->data();

        expect($data->properties['role']['type'])->toBe('AsEnum<typeof Role> | null');
        expect($data->properties['role']['optional'])->toBeFalse();
    });

    test('resolves EnumResource::make() to enum type with tolki disabled', function () {
        config()->set('ts-publish.enums.use_tolki_package', false);

        $data = (new ResourceTransformer(UserResource::class))->data();

        expect($data->properties['role']['type'])->toBe('RoleType | null');
    });

    test('resolves nested resource collection type', function () {
        $data = (new ResourceTransformer(UserResource::class))->data();

        expect($data->properties['posts']['type'])->toBe('PostResource[]');
        expect($data->properties['posts']['optional'])->toBeTrue();
    });
});

describe('ResourceTransformer with CommentResource', function () {
    test('resolves model from @mixin docblock', function () {
        $data = (new ResourceTransformer(CommentResource::class))->data();

        expect($data->modelClass)->toBe(Comment::class);
    });

    test('applies TsCasts type overrides', function () {
        $data = (new ResourceTransformer(CommentResource::class))->data();

        expect($data->properties['metadata']['type'])->toBe('Record<string, unknown>');
    });

    test('applies TsCasts optional override', function () {
        $data = (new ResourceTransformer(CommentResource::class))->data();

        expect($data->properties['flagged_at']['type'])->toBe('string | null');
        expect($data->properties['flagged_at']['optional'])->toBeTrue();
    });

    test('resolves nested resource make as optional', function () {
        $data = (new ResourceTransformer(CommentResource::class))->data();

        expect($data->properties['author']['type'])->toBe('UserResource');
        expect($data->properties['author']['optional'])->toBeTrue();

        expect($data->properties['post']['type'])->toBe('PostResource');
        expect($data->properties['post']['optional'])->toBeTrue();
    });

    // closure return-type annotation as fallback ───────────────────

    test('body wins over annotation — user_name is string not nullable', function () {
        $data = (new ResourceTransformer(CommentResource::class))->data();

        expect($data->properties['user_name']['type'])->toBe('string');
        expect($data->properties['user_name']['optional'])->toBeTrue();
    });

    test('non-nullsafe chain traversal — user_email resolves to string via body', function () {
        // `fn (): ?string => $this->resource->user->email` — body resolved by analyzePropertyChain.
        $data = (new ResourceTransformer(CommentResource::class))->data();

        expect($data->properties['user_email']['type'])->toBe('string');
        expect($data->properties['user_email']['optional'])->toBeTrue();
    });

    test('annotation fallback fires when body is a FuncCall — user_email_annotated is string|null', function () {
        // json_decode() returns mixed, so the body stays unknown and the ?string annotation has to carry it.
        $data = (new ResourceTransformer(CommentResource::class))->data();

        expect($data->properties['user_email_annotated']['type'])->toBe('string | null');
        expect($data->properties['user_email_annotated']['optional'])->toBeTrue();
    });

    test('no annotation and unresolvable body — unresolvable_status is unknown', function () {
        // json_decode() returns mixed and there is no return type annotation to fall back on.
        $data = (new ResourceTransformer(CommentResource::class))->data();

        expect($data->properties['unresolvable_status']['type'])->toBe('unknown');
        expect($data->properties['unresolvable_status']['optional'])->toBeTrue();
    });

    test('enum annotation fallback resolves type — resolvable_status is StatusType', function () {
        // json_decode() returns mixed, so the Status annotation is what supplies both type and FQCN.
        $data = (new ResourceTransformer(CommentResource::class))->data();

        expect($data->properties['resolvable_status']['type'])->toBe('StatusType');
        expect($data->properties['resolvable_status']['optional'])->toBeTrue();
    });

    // nullsafe chains inside whenLoaded closures ──────────────────

    test('nullsafe chain in closure resolves to correct type — user_name_nullable is string|null', function () {
        $data = (new ResourceTransformer(CommentResource::class))->data();

        expect($data->properties['user_name_nullable']['type'])->toBe('string | null');
        expect($data->properties['user_name_nullable']['optional'])->toBeTrue();
    });

    test('nullsafe chain in closure resolves to correct type — user_email_nullable is string|null', function () {
        $data = (new ResourceTransformer(CommentResource::class))->data();

        expect($data->properties['user_email_nullable']['type'])->toBe('string | null');
        expect($data->properties['user_email_nullable']['optional'])->toBeTrue();
    });

    // top-level nullsafe chains ───────────────────────────────────

    test('top-level nullsafe resolves enum — user_role is RoleType|null', function () {
        config()->set('ts-publish.enums.use_tolki_package', false);
        $data = (new ResourceTransformer(CommentResource::class))->data();

        expect($data->properties['user_role']['type'])->toBe('RoleType | null');
        expect($data->properties['user_role']['optional'])->toBeFalse();
    });

    test('top-level nullsafe enum imports RoleType from enums', function () {
        config()->set('ts-publish.enums.use_tolki_package', false);
        $data = (new ResourceTransformer(CommentResource::class))->data();

        expect($data->typeImports)->toHaveKey('../../enums');
        expect($data->typeImports['../../enums'])->toContain('RoleType');
    });

    test('top-level nullsafe skips resource wrapper — user_profile is Profile|null', function () {
        $data = (new ResourceTransformer(CommentResource::class))->data();

        expect($data->properties['user_profile']['type'])->toBe('Profile | null');
        expect($data->properties['user_profile']['optional'])->toBeFalse();
    });

    test('top-level nullsafe relation imports Profile from models', function () {
        $data = (new ResourceTransformer(CommentResource::class))->data();

        expect($data->typeImports)->toHaveKey('../../models');
        expect($data->typeImports['../../models'])->toContain('Profile');
    });

    test('multi-hop nullsafe resolves attribute — user_profile_bio is string|null', function () {
        $data = (new ResourceTransformer(CommentResource::class))->data();

        expect($data->properties['user_profile_bio']['type'])->toBe('string | null');
        expect($data->properties['user_profile_bio']['optional'])->toBeFalse();
    });

    test('multi-hop nullsafe resolves attribute — user_profile_avatar_url is string|null', function () {
        $data = (new ResourceTransformer(CommentResource::class))->data();

        expect($data->properties['user_profile_avatar_url']['type'])->toBe('string | null');
        expect($data->properties['user_profile_avatar_url']['optional'])->toBeFalse();
    });

    // plain and nullsafe chain traversal inside whenLoaded — $this->post —————————

    test('plain chain in whenLoaded closure — post_title resolves to string', function () {
        $data = (new ResourceTransformer(CommentResource::class))->data();

        expect($data->properties['post_title']['type'])->toBe('string');
        expect($data->properties['post_title']['optional'])->toBeTrue();
    });

    test('nullsafe chain in whenLoaded closure — post_content is string|null', function () {
        $data = (new ResourceTransformer(CommentResource::class))->data();

        expect($data->properties['post_content']['type'])->toBe('string | null');
        expect($data->properties['post_content']['optional'])->toBeTrue();
    });

    test('nullsafe accessor in whenLoaded closure — post_title_display is string|null', function () {
        $data = (new ResourceTransformer(CommentResource::class))->data();

        expect($data->properties['post_title_display']['type'])->toBe('string | null');
        expect($data->properties['post_title_display']['optional'])->toBeTrue();
    });

    test('mixed chain in whenLoaded closure — post_author is string|null', function () {
        $data = (new ResourceTransformer(CommentResource::class))->data();

        expect($data->properties['post_author']['type'])->toBe('string | null');
        expect($data->properties['post_author']['optional'])->toBeTrue();
    });

    // same chains via $this->resource ———————————————————————

    test('resource wrapper skipped — post_resource_title resolves to string', function () {
        $data = (new ResourceTransformer(CommentResource::class))->data();

        expect($data->properties['post_resource_title']['type'])->toBe('string');
        expect($data->properties['post_resource_title']['optional'])->toBeTrue();
    });

    test('resource wrapper skipped — post_resource_content is string|null', function () {
        $data = (new ResourceTransformer(CommentResource::class))->data();

        expect($data->properties['post_resource_content']['type'])->toBe('string | null');
        expect($data->properties['post_resource_content']['optional'])->toBeTrue();
    });

    test('resource wrapper skipped — post_resource_title_display is string|null', function () {
        $data = (new ResourceTransformer(CommentResource::class))->data();

        expect($data->properties['post_resource_title_display']['type'])->toBe('string | null');
        expect($data->properties['post_resource_title_display']['optional'])->toBeTrue();
    });

    test('resource wrapper skipped — post_resource_author is string|null', function () {
        $data = (new ResourceTransformer(CommentResource::class))->data();

        expect($data->properties['post_resource_author']['type'])->toBe('string | null');
        expect($data->properties['post_resource_author']['optional'])->toBeTrue();
    });

    // $this->resource chains match $this-> chains ———————————————

    test('resource-wrapped chains resolve identically to direct chains', function () {
        $data = (new ResourceTransformer(CommentResource::class))->data();

        expect($data->properties['post_resource_title']['type'])->toBe($data->properties['post_title']['type'])
            ->and($data->properties['post_resource_content']['type'])->toBe($data->properties['post_content']['type'])
            ->and($data->properties['post_resource_title_display']['type'])->toBe($data->properties['post_title_display']['type'])
            ->and($data->properties['post_resource_author']['type'])->toBe($data->properties['post_author']['type']);
    });
});

describe('ResourceTransformer with ToArrayCastsResource — #[TsCasts] on toArray() method', function () {
    test('overrides property type — role becomes string', function () {
        $data = (new ResourceTransformer(ToArrayCastsResource::class))->data();

        expect($data->properties['role']['type'])->toBe('string');
        expect($data->properties['role']['optional'])->toBeFalse();
    });

    test('overrides type and sets optional — email becomes string|null and optional', function () {
        $data = (new ResourceTransformer(ToArrayCastsResource::class))->data();

        expect($data->properties['email']['type'])->toBe('string | null');
        expect($data->properties['email']['optional'])->toBeTrue();
    });

    test('injects property not in return array — injected_field is Record<string, unknown>', function () {
        $data = (new ResourceTransformer(ToArrayCastsResource::class))->data();

        expect($data->properties['injected_field']['type'])->toBe('Record<string, unknown>');
        expect($data->properties['injected_field']['optional'])->toBeFalse();
    });

    test('registers custom import for GeoPoint', function () {
        $data = (new ResourceTransformer(ToArrayCastsResource::class))->data();

        expect($data->typeImports)->toHaveKey('@/types/geo');
        expect($data->typeImports['@/types/geo'])->toContain('GeoPoint');
    });

    test('unoverridden properties remain unaffected — id is number — name is string', function () {
        $data = (new ResourceTransformer(ToArrayCastsResource::class))->data();

        expect($data->properties['id']['type'])->toBe('number');
        expect($data->properties['name']['type'])->toBe('string');
    });

    test('a #[TsCasts] override removes the enum import the analyzer had claimed', function () {
        $transformer = new ResourceTransformer(ToArrayCastsResource::class);
        $transformer->data();

        expect(implode(' ', array_merge(...array_values($transformer->typeImports))))->not->toContain('RoleType');
    });
});

describe('ResourceTransformer with OrderResource', function () {
    test('resolves model from @mixin docblock', function () {
        $data = (new ResourceTransformer(OrderResource::class))->data();

        expect($data->modelClass)->toBe(Order::class);
    });

    test('resolves EnumResource::make() for Order enums with AsEnum', function () {
        $data = (new ResourceTransformer(OrderResource::class))->data();

        expect($data->properties['status']['type'])->toBe('AsEnum<typeof OrderStatus>');
        expect($data->properties['currency']['type'])->toBe('AsEnum<typeof Currency>');
    });

    test('resolves EnumResource::make() for Order enums with tolki disabled', function () {
        config()->set('ts-publish.enums.use_tolki_package', false);

        $data = (new ResourceTransformer(OrderResource::class))->data();

        expect($data->properties['status']['type'])->toBe('OrderStatusType');
        expect($data->properties['currency']['type'])->toBe('CurrencyType');
    });

    test('transforms when() as optional', function () {
        $data = (new ResourceTransformer(OrderResource::class))->data();

        expect($data->properties['paid_at']['optional'])->toBeTrue();
    });

    test('transforms whenCounted as optional number', function () {
        $data = (new ResourceTransformer(OrderResource::class))->data();

        expect($data->properties['items_count']['type'])->toBe('number');
        expect($data->properties['items_count']['optional'])->toBeTrue();
    });

    test('transforms whenAggregated as optional number', function () {
        $data = (new ResourceTransformer(OrderResource::class))->data();

        expect($data->properties['total_avg']['type'])->toBe('number');
        expect($data->properties['total_avg']['optional'])->toBeTrue();
    });

    test('transforms mergeWhen properties as optional', function () {
        $data = (new ResourceTransformer(OrderResource::class))->data();

        expect($data->properties)->toHaveKey('shipped_at');
        expect($data->properties)->toHaveKey('delivered_at');

        expect($data->properties['shipped_at']['optional'])->toBeTrue();
        expect($data->properties['delivered_at']['optional'])->toBeTrue();
    });

    test('transforms whenLoaded for order items', function () {
        $data = (new ResourceTransformer(OrderResource::class))->data();

        expect($data->properties['items']['optional'])->toBeTrue();
    });
});

describe('ResourceTransformer with a body-less subclass', function () {
    test('inherits the parent @mixin when the subclass declares no docblock of its own', function () {
        $data = (new ResourceTransformer(BodylessOrderResource::class))->data();

        expect($data->modelClass)->toBe(Order::class);
    });

    test('the inherited toArray() shape carries real column types instead of unknown', function () {
        $data = (new ResourceTransformer(BodylessOrderResource::class))->data();

        expect($data->properties['id']['type'])->toBe('number')
            ->and($data->properties['total']['type'])->toBe('number')
            ->and($data->properties['paid_at']['type'])->toBe('string | null')
            ->and(array_column($data->properties, 'type'))->not->toContain('unknown');
    });

    test('a body-less subclass with its own @mixin generates the ancestor shape, not an empty interface', function () {
        $data = (new ResourceTransformer(BodylessTeamResource::class))->data();

        expect($data->modelClass)->toBe(Team::class)
            ->and($data->properties['name']['type'])->toBe('string')
            ->and($data->properties['members']['type'])->toBe('TeamMemberResource[]');
    });
});

describe('ResourceTransformer imports', function () {
    test('PostResource has value imports for enum consts with tolki', function () {
        $data = (new ResourceTransformer(PostResource::class))->data();

        expect($data->valueImports)->toHaveKey('../../enums');
        expect($data->valueImports['../../enums'])->toContain('Priority');
        expect($data->valueImports['../../enums'])->toContain('Status');
        expect($data->valueImports['../../enums'])->toContain('Visibility');
    });

    test('PostResource has no type imports for enums with tolki', function () {
        $data = (new ResourceTransformer(PostResource::class))->data();

        // Enum FQCNs are removed from type imports when tolki rewrites them to AsEnum
        expect($data->typeImports)->not->toHaveKey('../../enums');
    });

    test('PostResource has type imports for enums with tolki disabled', function () {
        config()->set('ts-publish.enums.use_tolki_package', false);

        $data = (new ResourceTransformer(PostResource::class))->data();

        expect($data->typeImports)->toHaveKey('../../enums');
        expect($data->typeImports['../../enums'])->toContain('PriorityType');
        expect($data->typeImports['../../enums'])->toContain('StatusType');
        expect($data->typeImports['../../enums'])->toContain('VisibilityType');
        expect($data->valueImports)->toBeEmpty();
    });

    test('UserResource has type imports for nested resource', function () {
        $data = (new ResourceTransformer(UserResource::class))->data();

        expect($data->typeImports)->toHaveKey('.');
        expect($data->typeImports['.'])->toContain('PostResource');
    });

    test('UserResource has value imports for enum const', function () {
        $data = (new ResourceTransformer(UserResource::class))->data();

        expect($data->valueImports)->toHaveKey('../../enums');
        expect($data->valueImports['../../enums'])->toContain('Role');
    });

    test('CommentResource has type imports for nested resources', function () {
        $data = (new ResourceTransformer(CommentResource::class))->data();

        expect($data->typeImports)->toHaveKey('.');
        expect($data->typeImports['.'])->toContain('PostResource');
        expect($data->typeImports['.'])->toContain('UserResource');
    });

    test('CommentResource has enum imports from inline relation filter (post_extended)', function () {
        $data = (new ResourceTransformer(CommentResource::class))->data();

        // post_extended = $this->post?->except(['created_at', 'updated_at']) now references the Post model
        // interface via Pick<> of the surviving columns instead of an inline enum-casted shape. Post's own
        // generated file carries VisibilityType/PriorityType now; StatusType survives via resolvable_status.
        $postExtendedType = (string) $data->properties['post_extended']['type'];

        preg_match_all("/'([a-zA-Z0-9_]+)'/", $postExtendedType, $postExtendedMatches);
        $postExtendedKeys = $postExtendedMatches[1];
        sort($postExtendedKeys);

        expect($postExtendedType)->toStartWith('Pick<Post, ')
            ->and($postExtendedType)->toEndWith('> | null')
            ->and($postExtendedKeys)->toBe([
                'category', 'category_id', 'content', 'deleted_at', 'featured_image_url', 'id', 'is_pinned',
                'metadata', 'options', 'priority', 'published_at', 'rating', 'reading_time_minutes', 'status',
                'title', 'user_id', 'visibility', 'word_count',
            ]);
        expect($data->typeImports)->toHaveKey('../../models');
        expect($data->typeImports['../../models'])->toContain('Post');
        expect($data->typeImports)->toHaveKey('../../enums');
        expect($data->typeImports['../../enums'])->toContain('StatusType')
            ->not->toContain('VisibilityType')
            ->not->toContain('PriorityType');
        expect($data->valueImports)->toBeEmpty();
    });

    test('OrderResource has value imports for enum consts', function () {
        $data = (new ResourceTransformer(OrderResource::class))->data();

        expect($data->valueImports)->toHaveKey('../../enums');
        expect($data->valueImports['../../enums'])->toContain('Currency');
        expect($data->valueImports['../../enums'])->toContain('OrderStatus');
    });

    test('OrderResource has type imports for related model', function () {
        $data = (new ResourceTransformer(OrderResource::class))->data();

        expect($data->typeImports)->toHaveKey('../../models');
        expect($data->typeImports['../../models'])->toContain('OrderItem');
    });

    test('UserResource has type imports for related model', function () {
        $data = (new ResourceTransformer(UserResource::class))->data();

        expect($data->typeImports)->toHaveKey('../../models');
        expect($data->typeImports['../../models'])->toContain('Profile');
    });

    test('TernaryResource has value imports for all multi-FQCN EnumResource FQCNs with tolki', function () {
        config()->set('ts-publish.enums.use_tolki_package', true);
        $data = (new ResourceTransformer(TernaryResource::class))->data();

        expect($data->valueImports)->toHaveKey('../../enums');
        expect($data->valueImports['../../enums'])->toContain('Status');
        expect($data->valueImports['../../enums'])->toContain('Visibility');
    });

    test('TernaryResource has type import for StatusType when mixed ternary uses direct access with tolki', function () {
        config()->set('ts-publish.enums.use_tolki_package', true);
        $data = (new ResourceTransformer(TernaryResource::class))->data();

        // status_resource_or_type: AsEnum<typeof Status> | StatusType — StatusType needs a type import
        expect($data->typeImports)->toHaveKey('../../enums');
        expect($data->typeImports['../../enums'])->toContain('StatusType');
    });

    test('TernaryResource has type imports for StatusType and VisibilityType without tolki', function () {
        config()->set('ts-publish.enums.use_tolki_package', false);
        $data = (new ResourceTransformer(TernaryResource::class))->data();

        expect($data->typeImports)->toHaveKey('../../enums');
        expect($data->typeImports['../../enums'])->toContain('StatusType');
        expect($data->typeImports['../../enums'])->toContain('VisibilityType');
        expect($data->valueImports)->toBeEmpty();
    });
});

describe('ResourceTransformer with AddressResource', function () {
    test('resolves resourceName from TsResource name attribute', function () {
        $data = (new ResourceTransformer(AddressResource::class))->data();

        expect($data->resourceName)->toBe('Address');
    });

    test('resolves description from TsResource description attribute', function () {
        $data = (new ResourceTransformer(AddressResource::class))->data();

        expect($data->description)->toBe('Mailing address resource');
    });

    test('applies TsCasts with custom import', function () {
        $data = (new ResourceTransformer(AddressResource::class))->data();

        expect($data->properties)->toHaveKey('coordinates')
            ->and($data->properties['coordinates']['type'])->toBe('GeoPoint')
            ->and($data->typeImports)->toHaveKey('@/types/geo')
            ->and($data->typeImports['@/types/geo'])->toContain('GeoPoint');
    });
});

describe('ResourceTransformer modular imports', function () {
    test('PostResource has modular enum value imports with tolki', function () {
        config()->set('ts-publish.namespace_strip_prefix', 'Workbench\\');

        $data = (new ResourceTransformer(PostResource::class))->data();

        expect($data->valueImports)->not->toHaveKey('../enums');

        $hasEnumValueImport = false;
        foreach ($data->valueImports as $path => $names) {
            if (count(array_intersect($names, ['Status', 'Visibility', 'Priority'])) > 0) {
                $hasEnumValueImport = true;
            }
        }
        expect($hasEnumValueImport)->toBeTrue();
    });

    test('PostResource has modular enum type imports with tolki disabled', function () {
        config()->set('ts-publish.namespace_strip_prefix', 'Workbench\\');
        config()->set('ts-publish.enums.use_tolki_package', false);

        $data = (new ResourceTransformer(PostResource::class))->data();

        expect($data->typeImports)->not->toHaveKey('../enums');

        $hasEnumTypeImport = false;
        foreach ($data->typeImports as $path => $names) {
            if (count(array_intersect($names, ['StatusType', 'VisibilityType', 'PriorityType'])) > 0) {
                $hasEnumTypeImport = true;
            }
        }
        expect($hasEnumTypeImport)->toBeTrue();
    });

    test('UserResource has modular resource imports', function () {
        config()->set('ts-publish.namespace_strip_prefix', 'Workbench\\');

        $data = (new ResourceTransformer(UserResource::class))->data();

        expect($data->typeImports)->not->toHaveKey('./');

        $hasResourceImport = false;
        foreach ($data->typeImports as $path => $names) {
            if (in_array('PostResource', $names, true)) {
                $hasResourceImport = true;
            }
        }
        expect($hasResourceImport)->toBeTrue();
    });

    test('UserResource has modular model imports', function () {
        config()->set('ts-publish.namespace_strip_prefix', 'Workbench\\');

        $data = (new ResourceTransformer(UserResource::class))->data();

        expect($data->typeImports)->not->toHaveKey('../models');

        $hasModelImport = false;
        foreach ($data->typeImports as $path => $names) {
            if (in_array('Profile', $names, true)) {
                $hasModelImport = true;
            }
        }
        expect($hasModelImport)->toBeTrue();
    });
});

describe('ResourceTransformer with FqcnMixinResource', function () {
    test('resolves model class from FQCN @mixin docblock', function () {
        $data = (new ResourceTransformer(FqcnMixinResource::class))->data();

        expect($data->modelClass)->toBe(Order::class);
    });

    test('resolves property types from FQCN mixin model', function () {
        $data = (new ResourceTransformer(FqcnMixinResource::class))->data();

        expect($data->properties['id']['type'])->toBe('number')
            ->and($data->properties['total']['type'])->toContain('number');
    });
});

describe('ResourceTransformer TsCasts waterfall from model', function () {
    test('AddressResource inherits model TsCasts overrides for latitude and longitude', function () {
        $data = (new ResourceTransformer(AddressResource::class))->data();

        // The Address model's #[TsCasts] must win over the 'string' inferred from its decimal:7 cast.
        expect($data->properties['latitude']['type'])->toBe('number | null');
        expect($data->properties['longitude']['type'])->toBe('number | null');
    });

    test('model TsCasts does not add properties not present in toArray', function () {
        $data = (new ResourceTransformer(AddressResource::class))->data();

        // Address model has TsCasts for 'full_address' but it's not in AddressResource::toArray()
        expect($data->properties)->not->toHaveKey('full_address');
    });

    test('TsCasts overrides model TsCasts for same property', function () {
        $data = (new ResourceTransformer(CommentResource::class))->data();

        // Model and resource declare the same #[TsCasts] value, so this pins precedence, not the result.
        expect($data->properties['metadata']['type'])->toBe('Record<string, unknown>');
    });

    test('ProductResource inherits model TsCasts inline type for dimensions', function () {
        $data = (new ResourceTransformer(ProductResource::class))->data();

        // Product model has #[TsCasts(['dimensions' => '{ length: number; ... }'])] on casts() method
        expect($data->properties['dimensions']['type'])
            ->toBe('{ length: number; width: number; height: number; unit: "cm" | "in" }');
    });

    test('ProductResource inherits model TsCasts import for metadata', function () {
        $data = (new ResourceTransformer(ProductResource::class))->data();

        // Product model has TsCasts with import: '@js/types/product' for metadata
        expect($data->properties['metadata']['type'])->toBe('ProductMetadata | ProductJsonMetaData | null');
        expect($data->typeImports)->toHaveKey('@js/types/product');
        expect($data->typeImports['@js/types/product'])->toContain('ProductJsonMetaData')
            ->and($data->typeImports['@js/types/product'])->toContain('ProductMetadata');
    });

    test('AddressResource TsCasts coordinates still applies', function () {
        $data = (new ResourceTransformer(AddressResource::class))->data();

        // TsCasts adds 'coordinates' with GeoPoint type and import (not in toArray)
        expect($data->properties['coordinates']['type'])->toBe('GeoPoint');
        // TsCasts adds 'bounds' with GeoBounds type from the same import path
        expect($data->properties['bounds']['type'])->toBe('GeoBounds');
        expect($data->typeImports)->toHaveKey('@/types/geo');
        expect($data->typeImports['@/types/geo'])->toContain('GeoPoint')
            ->and($data->typeImports['@/types/geo'])->toContain('GeoBounds');
    });

    test('ProfileResource inherits property-level TsCasts for timezone', function () {
        $data = (new ResourceTransformer(ProfileResource::class))->data();

        // Profile model has #[TsCasts(['timezone' => 'string'])] on the $casts property
        expect($data->properties['timezone']['type'])->toBe('string');
    });

    test('ProfileResource inherits class-level TsCasts for social_links', function () {
        $data = (new ResourceTransformer(ProfileResource::class))->data();

        // Profile model has class-level #[TsCasts(['social_links' => '{ twitter?: ... }'])]
        expect($data->properties['social_links']['type'])
            ->toBe('{ twitter?: string; github?: string; linkedin?: string; website?: string }');
    });

    test('resource without backing model skips model TsCasts gracefully', function () {
        $data = (new ResourceTransformer(EmptyResource::class))->data();

        expect($data->modelClass)->toBeNull();
        expect($data->properties)->toBeEmpty();
    });

    test('resource with model and no toArray generates model attribute properties', function () {
        $data = (new ResourceTransformer(EmptyWithMixinResource::class))->data();

        expect($data->modelClass)->toBe(User::class)
            ->and($data->properties)->not->toBeEmpty()
            ->and($data->properties)->toHaveKey('id')
            ->and($data->properties)->toHaveKey('name')
            ->and($data->properties)->toHaveKey('email');
    });

    test('resource with model delegating to parent generates model attribute properties', function () {
        $data = (new ResourceTransformer(DelegatingWithMixinResource::class))->data();

        expect($data->modelClass)->toBe(User::class)
            ->and($data->properties)->not->toBeEmpty()
            ->and($data->properties)->toHaveKey('id')
            ->and($data->properties)->toHaveKey('name')
            ->and($data->properties)->toHaveKey('email');
    });
});

describe('ResourceTransformer self-referencing resources', function () {
    test('self-referencing resource does not import itself', function () {
        $data = (new ResourceTransformer(CategoryResource::class))->data();

        // CategoryResource references CategoryResource::make() and ::collection().
        foreach ($data->typeImports as $types) {
            expect($types)->not->toContain('CategoryResource');
        }
    });

    test('self-referencing resource still imports other referenced resources', function () {
        $data = (new ResourceTransformer(CategoryResource::class))->data();

        // CategoryResource also references PostResource::collection()
        $hasPostImport = false;
        foreach ($data->typeImports as $types) {
            if (in_array('PostResource', $types, true)) {
                $hasPostImport = true;
            }
        }
        expect($hasPostImport)->toBeTrue();
    });

    test('self-referencing resource resolves self-reference property types', function () {
        $data = (new ResourceTransformer(CategoryResource::class))->data();

        expect($data->properties['parent']['type'])->toBe('CategoryResource');
        expect($data->properties['children']['type'])->toBe('CategoryResource[]');
    });

    // self:: and new self() expressions ———————————————————————————————————————

    test('self::collection() resolves to CategoryResource[]', function () {
        $data = (new ResourceTransformer(CategoryResource::class))->data();

        expect($data->properties['children_self_collection']['type'])->toBe('CategoryResource[]');
        expect($data->properties['children_self_collection']['optional'])->toBeFalse();
    });

    test('self::collection() via $this->resource resolves to CategoryResource[]', function () {
        $data = (new ResourceTransformer(CategoryResource::class))->data();

        expect($data->properties['children_self_resource_collection']['type'])->toBe('CategoryResource[]');
        expect($data->properties['children_self_resource_collection']['optional'])->toBeFalse();
    });

    test('self::collection(...) first-class callable resolves to CategoryResource[]', function () {
        $data = (new ResourceTransformer(CategoryResource::class))->data();

        expect($data->properties['children_self_collection_first_callable']['type'])->toBe('CategoryResource[]');
        expect($data->properties['children_self_collection_first_callable']['optional'])->toBeFalse();
    });

    test('whenLoaded with self::collection() resolves to optional CategoryResource[]', function () {
        $data = (new ResourceTransformer(CategoryResource::class))->data();

        expect($data->properties['children_when_self_collection']['type'])->toBe('CategoryResource[]');
        expect($data->properties['children_when_self_collection']['optional'])->toBeTrue();
    });

    test('whenLoaded with self::collection() via $this->resource resolves to optional CategoryResource[]', function () {
        $data = (new ResourceTransformer(CategoryResource::class))->data();

        expect($data->properties['children_when_self_resource_collection']['type'])->toBe('CategoryResource[]');
        expect($data->properties['children_when_self_resource_collection']['optional'])->toBeTrue();
    });

    test('whenLoaded with self::collection(...) FCC resolves to optional CategoryResource[]', function () {
        $data = (new ResourceTransformer(CategoryResource::class))->data();

        expect($data->properties['children_when_self_collection_first_callable']['type'])->toBe('CategoryResource[]');
        expect($data->properties['children_when_self_collection_first_callable']['optional'])->toBeTrue();
    });

    test('new self() resolves to CategoryResource', function () {
        $data = (new ResourceTransformer(CategoryResource::class))->data();

        expect($data->properties['parent_self']['type'])->toBe('CategoryResource');
        expect($data->properties['parent_self']['optional'])->toBeFalse();
    });

    test('self::make() resolves to CategoryResource', function () {
        $data = (new ResourceTransformer(CategoryResource::class))->data();

        expect($data->properties['parent_make_self']['type'])->toBe('CategoryResource');
        expect($data->properties['parent_make_self']['optional'])->toBeFalse();
    });

    test('new self() via $this->resource resolves to CategoryResource', function () {
        $data = (new ResourceTransformer(CategoryResource::class))->data();

        expect($data->properties['parent_resource_self']['type'])->toBe('CategoryResource');
        expect($data->properties['parent_resource_self']['optional'])->toBeFalse();
    });

    test('whenLoaded with new self() in closure resolves to optional CategoryResource', function () {
        $data = (new ResourceTransformer(CategoryResource::class))->data();

        expect($data->properties['parent_when_self']['type'])->toBe('CategoryResource');
        expect($data->properties['parent_when_self']['optional'])->toBeTrue();
    });

    test('whenLoaded with new self() via $this->resource in closure resolves to optional CategoryResource', function () {
        $data = (new ResourceTransformer(CategoryResource::class))->data();

        expect($data->properties['parent_when_resource_self']['type'])->toBe('CategoryResource');
        expect($data->properties['parent_when_resource_self']['optional'])->toBeTrue();
    });
});

describe('ResourceTransformer with parent::toArray spread', function () {
    test('ApiPostResource includes parent PostResource properties', function () {
        config()->set('ts-publish.enums.use_tolki_package', false);

        $data = (new ResourceTransformer(ApiPostResource::class))->data();

        expect($data->properties)->toHaveKey('id')
            ->and($data->properties)->toHaveKey('title')
            ->and($data->properties)->toHaveKey('content')
            ->and($data->properties)->toHaveKey('status')
            ->and($data->properties)->toHaveKey('status_new')
            ->and($data->properties)->toHaveKey('visibility')
            ->and($data->properties)->toHaveKey('visibility_new')
            ->and($data->properties)->toHaveKey('priority')
            ->and($data->properties)->toHaveKey('priority_new');
    });

    test('ApiPostResource parent properties have correct types', function () {
        config()->set('ts-publish.enums.use_tolki_package', false);

        $data = (new ResourceTransformer(ApiPostResource::class))->data();

        expect($data->properties['id']['type'])->toBe('number');
        expect($data->properties['title']['type'])->toBe('string');
        expect($data->properties['content']['type'])->toBe('string');
    });

    test('child properties override parent properties with same key', function () {
        config()->set('ts-publish.enums.use_tolki_package', false);

        $data = (new ResourceTransformer(ApiPostResource::class))->data();

        // Parent has EnumResource::make() → StatusType, child has $this->status → StatusType
        expect($data->properties['status']['type'])->toBe('StatusType');
        expect($data->properties['visibility']['type'])->toBe('VisibilityType | null');
        expect($data->properties['priority']['type'])->toBe('PriorityType | null');
    });

    test('non-overridden _new enum resource properties flow through from parent', function () {
        config()->set('ts-publish.enums.use_tolki_package', false);

        $data = (new ResourceTransformer(ApiPostResource::class))->data();

        // Parent has new EnumResource() for _new keys, child does not override them
        expect($data->properties['status_new']['type'])->toBe('StatusType');
        expect($data->properties['visibility_new']['type'])->toBe('VisibilityType | null');
        expect($data->properties['priority_new']['type'])->toBe('PriorityType | null');
    });

    test('ApiPostResource has enum type imports from parent', function () {
        config()->set('ts-publish.enums.use_tolki_package', false);

        $data = (new ResourceTransformer(ApiPostResource::class))->data();

        $allTypes = array_merge(...array_values($data->typeImports));

        expect($allTypes)->toContain('StatusType')
            ->and($allTypes)->toContain('VisibilityType')
            ->and($allTypes)->toContain('PriorityType');
    });
});

describe('ResourceTransformer with CommentComposedResource — top-level spreads flatten', function () {
    test('top-level resolve()/toArray() spreads flatten into the resource properties', function () {
        $properties = (new ResourceTransformer(CommentComposedResource::class))->data()->properties;
        $names = array_keys($properties);

        expect($names)->toContain('id')
            ->and($names)->toContain('title')   // from PostResource
            ->and($names)->toContain('email')   // from User::toArray()
            // from Post::tags()->toArray() — an index signature, not a named key: spreading a
            // collection renumbers its elements 0..n, the same shape buildSpreadArmTypes() wraps
            // as `Record<number, Tag>` for the identical spread one level down inside an inline array.
            ->and($properties['[key: number]']['type'])->toBe('Tag');
    });
});

describe('ResourceTransformer with trait method spread', function () {
    test('PostResource morphValue has string type from PHPDoc array shape', function () {
        $data = (new ResourceTransformer(PostResource::class))->data();

        expect($data->properties)->toHaveKey('morphValue')
            ->and($data->properties['morphValue']['type'])->toBe('string');
    });

    test('AddressResource morphValue has string type from PHPDoc array shape', function () {
        $data = (new ResourceTransformer(AddressResource::class))->data();

        expect($data->properties)->toHaveKey('morphValue')
            ->and($data->properties['morphValue']['type'])->toBe('string');
    });

    test('ApiPostResource inherits morphValue with string type via parent::toArray spread', function () {
        $data = (new ResourceTransformer(ApiPostResource::class))->data();

        expect($data->properties)->toHaveKey('morphValue')
            ->and($data->properties['morphValue']['type'])->toBe('string');
    });
});

describe('ResourceTransformer with trait TsCasts', function () {
    test('applies TsCasts type override from trait method', function () {
        $data = (new ResourceTransformer(TraitSpreadCoverageResource::class))->data();

        expect($data->properties)->toHaveKey('location')
            ->and($data->properties['location']['type'])->toBe('GeoPoint');
    });

    test('generates import from TsCasts on trait method', function () {
        $data = (new ResourceTransformer(TraitSpreadCoverageResource::class))->data();

        expect($data->typeImports)->toHaveKey('@/types/geo')
            ->and($data->typeImports['@/types/geo'])->toContain('GeoPoint');
    });

    test('adds new property from TsCasts on trait method', function () {
        $data = (new ResourceTransformer(TraitSpreadCoverageResource::class))->data();

        expect($data->properties)->toHaveKey('extra')
            ->and($data->properties['extra']['type'])->toBe('Record<string, unknown>');
    });

    test('resolves multiline @return array shape types from trait method', function () {
        $data = (new ResourceTransformer(TraitSpreadCoverageResource::class))->data();

        expect($data->properties)->toHaveKey('firstName')
            ->and($data->properties['firstName']['type'])->toBe('string')
            ->and($data->properties)->toHaveKey('isActive')
            ->and($data->properties['isActive']['type'])->toBe('boolean');
    });
});

describe('ResourceTransformer convention-based model guess', function () {
    test('resolves model from naming convention when no @mixin or TsResource & resolves properties', function () {
        $data = (new ResourceTransformer(WarehouseResource::class))->data();

        expect($data->modelClass)->toBe(Warehouse::class);

        expect($data->properties)->toHaveKey('id')
            ->and($data->properties['id']['type'])->toBe('number')
            ->and($data->properties)->toHaveKey('name')
            ->and($data->properties['name']['type'])->toBe('string');
    });

    test('convention guess does not override @mixin when present', function () {
        $data = (new ResourceTransformer(PostResource::class))->data();

        expect($data->modelClass)->toBe(Post::class);
    });

    test('resource without matching model stays null', function () {
        $data = (new ResourceTransformer(EmptyResource::class))->data();

        expect($data->modelClass)->toBeNull();
    });

    test('resolves model from convention in modularized namespace & resolves properties', function () {
        $data = (new ResourceTransformer(CrmUserResource::class))->data();

        expect($data->modelClass)->toBe(CrmUser::class);

        expect($data->properties)->toHaveKey('id')
            ->and($data->properties['id']['type'])->toBe('number')
            ->and($data->properties)->toHaveKey('name')
            ->and($data->properties['name']['type'])->toBe('string')
            ->and($data->properties)->toHaveKey('email')
            ->and($data->properties['email']['type'])->toBe('string');
    });
});

describe('ResourceTransformer convention guess edge cases', function () {
    test('returns null when resource is not in Http\Resources namespace', function () {
        $data = (new ResourceTransformer(DirectResource::class))->data();

        expect($data->modelClass)->toBeNull();
    });

    test('resolves model from subdirectory without Resource suffix', function () {
        $data = (new ResourceTransformer(AdminStoreResource::class))->data();

        expect($data->modelClass)->toBe(AdminStore::class);
    });

    test('resolves properties from subdirectory convention-guessed model', function () {
        $data = (new ResourceTransformer(AdminStoreResource::class))->data();

        expect($data->properties)->toHaveKey('id')
            ->and($data->properties['id']['type'])->toBe('number')
            ->and($data->properties)->toHaveKey('name')
            ->and($data->properties['name']['type'])->toBe('string');
    });
});

describe('ResourceTransformer UseResource attribute model guess', function () {
    test('resolves model from #[UseResource] attribute on model & resolves properties', function () {
        $data = (new ResourceTransformer(EventLogResource::class))->data();

        expect($data->modelClass)->toBe(TrackingEvent::class);

        expect($data->properties)->toHaveKey('id')
            ->and($data->properties)->toHaveKey('description');
    })->skip(
        ! class_exists('Illuminate\Database\Eloquent\Attributes\UseResource'),
        'UseResource attribute requires Laravel 12+',
    );
});

describe('ResourceTransformer import collision deconfliction', function () {
    test('aliases colliding enum types and model types in modular mode', function () {
        config()->set('ts-publish.namespace_strip_prefix', 'Workbench\\');
        config()->set('ts-publish.enums.use_tolki_package', false);

        $data = (new ResourceTransformer(DealResource::class))->data();

        $allTypeImports = array_merge(...array_values($data->typeImports));

        // App\Enums, App\Models, and App\Http\Resources are entirely skip-listed, so the
        // registry falls back to the raw, nearest segment (Enums / Models / Resources); the
        // Crm side keeps its one non-skip segment ('Crm').
        expect($allTypeImports)->toContain('StatusType as EnumsStatusType')
            ->toContain('StatusType as CrmStatusType');

        expect($allTypeImports)->toContain('User as ModelsUser')
            ->toContain('User as CrmUser');

        expect($allTypeImports)->toContain('UserResource as ResourcesUserResource')
            ->toContain('UserResource as CrmUserResource');

        expect($data->properties['status']['type'])->toBe('EnumsStatusType');
        expect($data->properties['status_enum']['type'])->toBe('EnumsStatusType');
        expect($data->properties['crm_status']['type'])->toBe('CrmStatusType');
        expect($data->properties['crm_enum']['type'])->toBe('CrmStatusType');
        expect($data->properties['customer']['type'])->toBe('CrmUser');
        expect($data->properties['admin']['type'])->toBe('ModelsUser');
        expect($data->properties['customer_resource']['type'])->toBe('CrmUserResource');
        expect($data->properties['admin_resource']['type'])->toBe('ResourcesUserResource');
    });

    test('aliases enum type imports, value imports, and property types with tolki enabled', function () {
        config()->set('ts-publish.namespace_strip_prefix', 'Workbench\\');
        config()->set('ts-publish.enums.use_tolki_package', true);

        $data = (new ResourceTransformer(DealResource::class))->data();

        $allTypeImports = array_merge(...array_values($data->typeImports));
        $allValueImports = array_merge(...array_values($data->valueImports));

        // Same all-skip-listed fallback as the tolki-disabled test above; the const alias
        // mirrors the type alias's chosen prefix ('Enums') via the sibling const registry.
        expect($allTypeImports)->toContain('User as ModelsUser')
            ->toContain('User as CrmUser');

        expect($allTypeImports)->toContain('UserResource as ResourcesUserResource')
            ->toContain('UserResource as CrmUserResource');

        expect($allTypeImports)->toContain('StatusType as EnumsStatusType')
            ->toContain('StatusType as CrmStatusType');

        expect($allValueImports)->toContain('Status as EnumsStatus')
            ->toContain('Status as CrmStatus');

        expect($data->properties['status']['type'])->toBe('EnumsStatusType');
        expect($data->properties['crm_status']['type'])->toBe('CrmStatusType');

        expect($data->properties['status_enum']['type'])->toBe('AsEnum<typeof EnumsStatus>');
        expect($data->properties['crm_enum']['type'])->toBe('AsEnum<typeof CrmStatus>');

        expect($data->properties['customer']['type'])->toBe('CrmUser');
        expect($data->properties['admin']['type'])->toBe('ModelsUser');
        expect($data->properties['customer_resource']['type'])->toBe('CrmUserResource');
        expect($data->properties['admin_resource']['type'])->toBe('ResourcesUserResource');
    });

    test('aliases both same-named consts wrapped again inside one inline object', function () {
        config()->set('ts-publish.namespace_strip_prefix', 'Workbench\\');
        config()->set('ts-publish.enums.use_tolki_package', true);

        $data = (new ResourceTransformer(DealResource::class))->data();

        // Both App\Enums\Status and Crm\Enums\Status are already aliased at top level (the test
        // above), so status_pair's inline wrap of the same two FQCNs must reuse the same aliases —
        // not the bare 'Status' const analyzeInlineArray() substitutes before any alias exists.
        expect($data->properties['status_pair']['type'])
            ->toBe('{ app: AsEnum<typeof EnumsStatus>; crm: AsEnum<typeof CrmStatus> }');
    });

    test('registers and aliases consts reachable only through an inline EnumResource wrap', function () {
        config()->set('ts-publish.namespace_strip_prefix', 'Workbench\\');
        config()->set('ts-publish.enums.use_tolki_package', true);

        $data = (new ResourceTransformer(DealEnumInlineResource::class))->data();

        $allValueImports = array_merge(...array_values($data->valueImports));

        // Neither enum is read at the top level anywhere in this file, so enumFqcnMap stays empty
        // and no bare type import is generated — only the two value imports, now distinct.
        expect($data->typeImports)->toBe([]);
        expect($allValueImports)->toBe(['Status as EnumsStatus', 'Status as CrmStatus']);
        expect($data->properties['summary']['type'])
            ->toBe('{ app_status: AsEnum<typeof EnumsStatus>; crm_status: AsEnum<typeof CrmStatus> }');
    });

    test('two colliding Status enums, one repeated, keep their own alias through the positional queue', function () {
        $data = (new ResourceTransformer(DealEnumTrioResource::class))->data();

        // App\Enums\Status and Crm\Enums\Status share a basename, so both render the same bare
        // 'StatusType' token — aliasPropertyType() is the only thing telling occurrence 'c' apart
        // from 'b', and it can only do that if the FQCN queue kept all three entries in order.
        expect($data->properties['trio']['type'])
            ->toBe('{ a: WorkbenchStatusType; b: CrmStatusType; c: WorkbenchStatusType }');

        // Same collision, reached through a class-constant array of one record: the record's own
        // positional list (analyzeConstantRecordValue()) must survive analyzeConstantListValue()'s
        // list-element handling instead of being re-deduped there.
        expect($data->properties['matrix']['type'])
            ->toBe('{ a: WorkbenchStatusType; b: CrmStatusType; c: WorkbenchStatusType }[]');
    });
});

describe('ResourceTransformer with ApiArticleResource (abstract parent + trait spreads)', function () {
    test('includes properties from parent CommonResource trait method spreads', function () {
        $data = (new ResourceTransformer(ApiArticleResource::class))->data();

        expect($data->properties)->toHaveKey('morphValue')
            ->and($data->properties)->toHaveKey('firstName')
            ->and($data->properties)->toHaveKey('isActive')
            ->and($data->properties)->toHaveKey('location')
            ->and($data->properties)->toHaveKey('flag');
    });

    test('resolves enum types with tolki enabled', function () {
        $data = (new ResourceTransformer(ApiArticleResource::class))->data();

        expect($data->properties['status']['type'])->toBe('AsEnum<typeof ArticleStatus>')
            ->and($data->properties['content_type']['type'])->toBe('AsEnum<typeof ContentType>');
    });

    test('resolves enum types with tolki disabled', function () {
        config()->set('ts-publish.enums.use_tolki_package', false);

        $data = (new ResourceTransformer(ApiArticleResource::class))->data();

        expect($data->properties['status']['type'])->toBe('ArticleStatusType')
            ->and($data->properties['content_type']['type'])->toBe('ContentTypeType');
    });

    test('author from whenLoaded is optional', function () {
        $data = (new ResourceTransformer(ApiArticleResource::class))->data();

        expect($data->properties['author']['optional'])->toBeTrue()
            ->and($data->properties['author']['type'])->toBe('User');
    });

    test('includes custom import from parent TsCasts trait', function () {
        $data = (new ResourceTransformer(ApiArticleResource::class))->data();

        $allTypes = array_merge(...array_values($data->typeImports));

        expect($allTypes)->toContain('GeoPoint');
    });

    test('resolves $this->only properties with Article model types', function () {
        $data = (new ResourceTransformer(ApiArticleResource::class))->data();

        expect($data->properties['title']['type'])->toBe('string')
            ->and($data->properties['slug']['type'])->toBe('string')
            ->and($data->properties['excerpt']['type'])->toBe('string | null')
            ->and($data->properties['body']['type'])->toBe('string');
    });
});

describe('ResourceTransformer with union model accessor types', function () {
    test('accessor returning a union of two different models produces correct aliased type', function () {
        $data = (new ResourceTransformer(WarehouseResource::class))->data();

        expect($data->properties)
            ->toHaveKey('last_user_activity_by')
            ->toHaveKey('last_user_activity_by_typed')
            ->toHaveKey('last_user_activity_by_typed_short');

        // Warehouse::lastUserActivityBy is Attribute<CrmUser|User|null, never>; both share basename 'User'.
        // mergeTypeScriptInfos() appends to $types and $orderedClassFqcns in one pass, so arm N is FQCN N:
        // the CRM arm is declared first and must alias first.
        expect($data->properties['last_user_activity_by']['type'])
            ->toBe('CrmUser | WorkbenchUser | null');
        expect($data->properties['last_user_activity_by_typed']['type'])
            ->toBe('CrmUser | WorkbenchUser | null');
        expect($data->properties['last_user_activity_by_typed_short']['type'])
            ->toBe('CrmUser | WorkbenchUser | null');
    });

    test('accessor returning a union of two different models generates import aliases', function () {
        $data = (new ResourceTransformer(WarehouseResource::class))->data();

        expect($data->typeImports)
            ->and($data->typeImports['../../../crm/models'])->toContain('User as CrmUser')
            ->and($data->typeImports['../../models'])->toContain('User as WorkbenchUser');
    });

    test('accessor union type with ->only() filter references each arm\'s own model', function () {
        $data = (new ResourceTransformer(WarehouseResource::class))->data();

        // 'id' and 'name' are published columns on both models, so each arm becomes its own
        // Pick<> reference instead of a re-derived inline shape.
        expect($data->properties)->toHaveKey('last_user_activity_by_partial')
            ->and($data->properties['last_user_activity_by_partial']['type'])
            ->toBe("Pick<CrmUser, 'id' | 'name'> | Pick<WorkbenchUser, 'id' | 'name'> | null");
    });

    test('accessor union type with ->except() filter references each arm\'s own model', function () {
        $data = (new ResourceTransformer(WarehouseResource::class))->data();

        expect($data->properties)->toHaveKey('last_user_activity_by_mostly');

        $type = $data->properties['last_user_activity_by_mostly']['type'];

        // Every excluded key (id, name) is a published column on both models, so each arm is its own
        // Pick<> reference to the model interface — never a re-derived inline shape.
        expect($type)->toBe(
            "Pick<CrmUser, 'email' | 'company' | 'status' | 'created_at' | 'updated_at'> | "
            ."Pick<WorkbenchUser, 'email' | 'email_verified_at' | 'password' | 'options' | 'remember_token' | "
            ."'created_at' | 'updated_at' | 'role' | 'membership_level' | 'phone' | 'avatar' | 'bio' | "
            ."'settings' | 'last_login_at' | 'last_login_ip'> | null"
        );

        // Member-level, not substring: relationFilterArmMembers() throws on a model-reference arm
        // instead of returning [] — proving both arms are real Pick<> references, not silently inline.
        $arms = array_filter(splitTopLevelType($type), fn (string $arm): bool => trim($arm) !== 'null');

        foreach ($arms as $arm) {
            expect(fn () => relationFilterArmMembers($arm))->toThrow(RuntimeException::class);
        }
    });

    test('accessor returning a union of two enum types produces correct aliased type', function () {
        $data = (new ResourceTransformer(WarehouseResource::class))->data();

        expect($data->properties)
            ->toHaveKey('review_priority')
            ->toHaveKey('review_priority_typed')
            ->toHaveKey('review_priority_typed_short');

        // Workbench\App\Enums\Status collides with CrmStatus; Priority has no conflict.
        expect($data->properties['review_priority']['type'])
            ->toBe('WorkbenchStatusType | PriorityType | null');
        expect($data->properties['review_priority_typed']['type'])
            ->toBe('WorkbenchStatusType | PriorityType | null');
        expect($data->properties['review_priority_typed_short']['type'])
            ->toBe('WorkbenchStatusType | PriorityType | null');
    });

    test('accessor returning a union of two enum types includes both in enums import', function () {
        $data = (new ResourceTransformer(WarehouseResource::class))->data();

        $enumImports = collect($data->typeImports)
            ->filter(fn ($types) => in_array('PriorityType', $types, true))
            ->first();

        expect($enumImports)
            ->not->toBeNull()
            ->toContain('PriorityType')
            ->toContain('StatusType as WorkbenchStatusType');
    });

    test('inline object from ->only() uses aliased enum name instead of base name', function () {
        $data = (new ResourceTransformer(WarehouseResource::class))->data();

        // crm_contact_partial = $this->primaryContact?->only(['status', 'images']): 'images' is a
        // relation, not a published column, so relationFilterModelReference() rejects the reference
        // and this falls back to inline expansion, carrying the aliased CrmStatus enum.
        expect($data->properties)->toHaveKey('crm_contact_partial')
            ->and($data->properties['crm_contact_partial']['type'])
            ->toContain('status: CrmStatusType');
    });

    test('a declining arm does not misalign the alias queue for the arm that resolves to Pick<>', function () {
        $data = (new ResourceTransformer(WarehouseResource::class))->data();

        // Only App\Models\User has a 'phone' column, so the CrmUser arm falls back to a plain
        // { id: number } — no bare model token — while the other arm resolves to Pick<>, which must
        // alias to WorkbenchUser, not CrmUser via a phantom queue entry from the declining arm.
        expect($data->properties)->toHaveKey('probe_mixed')
            ->and($data->properties['probe_mixed']['type'])
            ->toBe("{ id: number } | Pick<WorkbenchUser, 'id' | 'phone'> | null");
    });
});

describe('ResourceTransformer mergePropertyFqcnMaps() overlap guard', function () {
    test('a property already carrying inline model FQCNs is not re-queued by the accessor pass', function () {
        $transformer = new ResourceTransformer(WarehouseResource::class);

        $bindMethod = fn (string $method) => (fn () => $this->{$method}())->call($transformer);

        // Simulate a property whose per-occurrence FQCNs are already known — as inline array-literal
        // analysis supplies — in an order that differs from the accessor's own declared union.
        (function () {
            unset($this->propertyModelFqcnsList['last_user_activity_by']);
            $this->propertyInlineModelFqcns['last_user_activity_by'] = [User::class, CrmUser::class];
        })->call($transformer);

        $bindMethod('resolveMultiClassAccessorFqcns');

        $merged = (fn () => $this->mergePropertyFqcnMaps())->call($transformer);

        // Without the guard the accessor pass re-adds its own [CrmUser, User] ahead of the inline
        // entries, producing a 4-entry queue for what is really 2 occurrences and misaligning the prefix.
        expect($merged['last_user_activity_by'])->toBe([User::class, CrmUser::class]);
    });
});

describe('ResourceTransformer resolveMultiEnumAccessorFqcns() overlap guard', function () {
    test('a property already carrying inline enum FQCNs is not re-queued by the accessor pass', function () {
        $transformer = new ResourceTransformer(WarehouseResource::class);

        (function () {
            unset($this->propertyEnumFqcnsList['review_priority']);
            $this->propertyInlineEnumFqcns['review_priority'] = [Priority::class, Status::class];
        })->call($transformer);

        (fn () => $this->resolveMultiEnumAccessorFqcns())->call($transformer);

        $list = (fn () => $this->propertyEnumFqcnsList)->call($transformer);

        expect($list)->not->toHaveKey('review_priority');
    });
});

describe('ResourceTransformer inline model FQCN collision via ->only() filter', function () {
    test('model nested in ->only() inline object is aliased when it conflicts with another model', function () {
        $data = (new ResourceTransformer(ServiceDeskResource::class))->data();

        // 'order_requester' is $this->order?->only(['user']), and Order.user() is a BelongsTo to
        // Workbench\App\Models\User — the aliased token has to be rewritten inside the inline object too.
        expect($data->properties['order_requester']['type'])->toBe('{ user: WorkbenchUser } | null');
    });

    test('direct model reference alongside inline embedded model both receive aliases', function () {
        $data = (new ResourceTransformer(ServiceDeskResource::class))->data();

        // crm_agent is a BelongsTo to Workbench\Crm\Models\User whose FK crm_agent_id is nullable.
        expect($data->properties['crm_agent']['type'])->toBe('CrmUser | null');
    });

    test('imports include aliased names for both conflicting User models', function () {
        $data = (new ResourceTransformer(ServiceDeskResource::class))->data();

        $modelImports = collect($data->typeImports)->flatten()->all();

        expect($modelImports)
            ->toContain('User as WorkbenchUser')
            ->toContain('User as CrmUser');
    });
});

describe('ResourceTransformer with #[TsExtends] attribute', function () {
    test('WarehouseResource has tsExtends from attribute, trait, and parent class', function () {
        $data = (new ResourceTransformer(WarehouseResource::class))->data();

        expect($data->tsExtends)->toBe([
            'BaseResource',
            'ExtendableInterface',
            'Omit<Timestamps, "created_at" | "updated_at">',
            'ResourceRoutes',
            'Pick<Routable, "store" | "update">',
        ]);
    });

    test('WarehouseResource imports types from TsExtends', function () {
        $data = (new ResourceTransformer(WarehouseResource::class))->data();

        expect($data->typeImports)->toHaveKey('@/types/base')
            ->and($data->typeImports['@/types/base'])->toContain('BaseResource');
    });

    test('resource without TsExtends has empty tsExtends', function () {
        $data = (new ResourceTransformer(PostResource::class))->data();

        expect($data->tsExtends)->toBe([]);
    });
});

describe('ResourceTransformer with config-based ts_extends', function () {
    test('applies global resource extends from config', function () {
        config()->set('ts-publish.ts_extends.resources', [
            'GlobalResource',
        ]);

        $data = (new ResourceTransformer(PostResource::class))->data();

        expect($data->tsExtends)->toContain('GlobalResource');
    });

    test('applies config extends with import', function () {
        config()->set('ts-publish.ts_extends.resources', [
            ['extends' => 'ApiResource', 'import' => '@/types/api'],
        ]);

        $data = (new ResourceTransformer(PostResource::class))->data();

        expect($data->tsExtends)->toContain('ApiResource')
            ->and($data->typeImports)->toHaveKey('@/types/api')
            ->and($data->typeImports['@/types/api'])->toContain('ApiResource');
    });

    test('merges attribute and config extends for resources', function () {
        config()->set('ts-publish.ts_extends.resources', [
            'GlobalResource',
        ]);

        $data = (new ResourceTransformer(WarehouseResource::class))->data();

        expect($data->tsExtends)->toContain('BaseResource')
            ->and($data->tsExtends)->toContain('GlobalResource');
    });

    test('config array entry without import key is collected without an import', function () {
        config()->set('ts-publish.ts_extends.resources', [
            ['extends' => 'GloballyKnown'],
        ]);

        $data = (new ResourceTransformer(PostResource::class))->data();

        expect($data->tsExtends)->toContain('GloballyKnown')
            ->and($data->typeImports)->not->toHaveKey('GloballyKnown');
    });
});

describe('ResourceTransformer TsExtends deduplication and conflict resolution', function () {
    test('situation 1 — identical (extends, no-import) pairs are deduplicated', function () {
        config()->set('ts-publish.ts_extends.resources', ['SameType', 'SameType']);

        $data = (new ResourceTransformer(PostResource::class))->data();

        expect($data->tsExtends)->toBe(['SameType']);
    });

    test('situation 1 — identical (extends, import) pairs from config are deduplicated', function () {
        config()->set('ts-publish.ts_extends.resources', [
            ['extends' => 'BaseItem', 'import' => '@/types/base'],
            ['extends' => 'BaseItem', 'import' => '@/types/base'],
        ]);

        $data = (new ResourceTransformer(PostResource::class))->data();

        expect($data->tsExtends)->toBe(['BaseItem'])
            ->and($data->typeImports['@/types/base'])->toBe(['BaseItem']);
    });

    test('situation 2 — same type name from different import paths gets aliased', function () {
        config()->set('ts-publish.ts_extends.resources', [
            ['extends' => 'Routable', 'import' => '@/types/routing'],
            ['extends' => 'Routable', 'import' => '@/types/legacy'],
        ]);

        $data = (new ResourceTransformer(PostResource::class))->data();

        expect($data->tsExtends)->toBe(['RoutingRoutable', 'LegacyRoutable'])
            ->and($data->typeImports['@/types/routing'])->toBe(['Routable as RoutingRoutable'])
            ->and($data->typeImports['@/types/legacy'])->toBe(['Routable as LegacyRoutable']);
    });

    test('situation 2 — alias is applied inside a generic extends clause via preg_replace', function () {
        config()->set('ts-publish.ts_extends.resources', [
            ['extends' => 'Pick<Routable, "store" | "update">', 'import' => '@/types/routing', 'types' => ['Routable']],
            ['extends' => 'Routable', 'import' => '@/types/legacy'],
        ]);

        $data = (new ResourceTransformer(PostResource::class))->data();

        expect($data->tsExtends)->toBe(['Pick<RoutingRoutable, "store" | "update">', 'LegacyRoutable'])
            ->and($data->typeImports['@/types/routing'])->toBe(['Routable as RoutingRoutable'])
            ->and($data->typeImports['@/types/legacy'])->toBe(['Routable as LegacyRoutable']);
    });

    test('situation 3 — same type name from same import path is deduplicated to a single import', function () {
        config()->set('ts-publish.ts_extends.resources', [
            ['extends' => 'Routable', 'import' => '@/types/routing'],
            ['extends' => 'Pick<Routable, "store" | "update">', 'import' => '@/types/routing', 'types' => ['Routable']],
        ]);

        $data = (new ResourceTransformer(PostResource::class))->data();

        expect($data->tsExtends)->toBe(['Routable', 'Pick<Routable, "store" | "update">'])
            ->and($data->typeImports['@/types/routing'])->toBe(['Routable']);
    });
});

describe('ResourceTransformer TsExtends BFS trait deduplication', function () {
    test('trait shared by both child and parent is only processed once', function () {
        // ChildSharedResource uses SharedExtendsInterface directly and also extends BaseSharedResource,
        // which uses it too — the interface is reachable by two paths.
        $data = (new ResourceTransformer(ChildSharedResource::class))->data();

        expect($data->tsExtends)->toBe(['SharedInterface'])
            ->and($data->typeImports['@/types/shared'])->toBe(['SharedInterface']);
    });
});

describe('ResourceTransformer with InvoiceResource', function () {
    test('has enum imports from accessor model filter (latest_payment_only)', function () {
        $data = (new ResourceTransformer(InvoiceResource::class))->data();

        // Both latest_payment_only and latest_payment_excluded (the latest_payment accessor returns
        // ?Payment) reference the Payment model interface via Pick<> — Payment's own generated file
        // carries PaymentStatus/PaymentMethod/Currency internally, so this resource imports neither.
        expect($data->properties['latest_payment_only']['type'])->toBe(
            "Pick<Payment, 'invoice_id' | 'status' | 'method' | 'currency' | 'amount' | 'reference' | 'paid_at'> | null",
        );

        $excludedType = (string) $data->properties['latest_payment_excluded']['type'];

        preg_match_all("/'([a-zA-Z0-9_]+)'/", $excludedType, $excludedMatches);
        $excludedKeys = $excludedMatches[1];
        sort($excludedKeys);

        expect($excludedType)->toStartWith('Pick<Payment, ')
            ->and($excludedType)->toEndWith('> | null')
            ->and($excludedKeys)->toBe(['created_at', 'id', 'updated_at']);
    });

    test('has model imports from accessor model filter (latest_payment_excluded)', function () {
        $data = (new ResourceTransformer(InvoiceResource::class))->data();

        // The old inline expansion of latest_payment_excluded embedded the Invoice FQCN (via its
        // 'invoice' relation property); Pick<Payment, ...> only needs Payment itself — that relation
        // lives in Payment's own generated file now. See tests/Feature/ModelOnlyExceptSemanticsTest.php.
        expect($data->typeImports)->toHaveKey('../../models');
        expect($data->typeImports['../../models'])->toContain('Payment');
    });
});

describe('ResourceTransformer with MediaTypeResource (model-less enum resource)', function () {
    test('enum-backed resource produces correct interface shape', function () {
        $data = (new ResourceTransformer(MediaTypeResource::class))->data();

        expect($data->properties)->toHaveKeys(['name', 'value', 'meta']);
        expect($data->properties['name']['type'])->toBe('string');
        expect($data->properties['value']['type'])->toBe('string');
        expect($data->properties['meta']['type'])
            ->toStartWith('{ ')
            ->toEndWith(' }')
            ->toContain('maxSizeMb: number')
            ->toContain('icon: string');
    });

    test('enum-backed resource has no model class', function () {
        $data = (new ResourceTransformer(MediaTypeResource::class))->data();

        expect($data->modelClass)->toBeNull();
    });

    test('enum-backed resource has no type imports', function () {
        $data = (new ResourceTransformer(MediaTypeResource::class))->data();

        expect($data->typeImports)->toBeEmpty();
    });
});

describe('ResourceTransformer with MediaTypeInstanceOfResource (instanceof guard)', function () {
    test('instanceof guard resolves same interface shape as @var docblock', function () {
        $data = (new ResourceTransformer(MediaTypeInstanceOfResource::class))->data();

        expect($data->properties)->toHaveKeys(['name', 'value', 'meta']);
        expect($data->properties['name']['type'])->toBe('string');
        expect($data->properties['value']['type'])->toBe('string');
        expect($data->properties['meta']['type'])
            ->toStartWith('{ ')
            ->toEndWith(' }')
            ->toContain('maxSizeMb: number')
            ->toContain('icon: string');
    });
});

describe('ResourceTransformer with MediaTypeUnknownResource (no type hints)', function () {
    test('produces unknown types when no @var or instanceof hints exist', function () {
        $data = (new ResourceTransformer(MediaTypeUnknownResource::class))->data();

        expect($data->properties)->toHaveKeys(['name', 'value', 'meta']);
        expect($data->properties['name']['type'])->toBe('unknown');
        expect($data->properties['value']['type'])->toBe('unknown');
    });
});

describe('ResourceTransformer with AddressMixinResource and AddressExtendsResource', function () {
    test('@mixin resolves model class from docblock', function () {
        $data = (new ResourceTransformer(AddressMixinResource::class))->data();

        expect($data->modelClass)->toBe(Address::class);
    });

    test('@extends resolves model class from docblock', function () {
        $data = (new ResourceTransformer(AddressExtendsResource::class))->data();

        expect($data->modelClass)->toBe(Address::class);
    });

    test('@mixin does not match when tag appears in description text', function () {
        $data = (new ResourceTransformer(AddressMixinResource::class))->data();

        // The description contains "@mixin" in prose but the regex should only match "* @mixin"
        expect($data->description)->toContain('@mixin');
        expect($data->modelClass)->toBe(Address::class);
    });

    test('@extends does not match when tag appears in description text', function () {
        $data = (new ResourceTransformer(AddressExtendsResource::class))->data();

        // The description contains "@extends" in prose but the regex should only match "* @extends"
        expect($data->description)->toContain('@extends');
        expect($data->modelClass)->toBe(Address::class);
    });

    test('both resources produce identical properties, imports, & value imports', function () {
        $mixinData = (new ResourceTransformer(AddressMixinResource::class))->data();
        $extendsData = (new ResourceTransformer(AddressExtendsResource::class))->data();

        expect($mixinData->properties)->toBe($extendsData->properties);
        expect($mixinData->typeImports)->toBe($extendsData->typeImports);
        expect($mixinData->valueImports)->toBe($extendsData->valueImports);
    });
});

describe('ResourceTransformer ternary operator support', function () {
    test('EnumResource::make vs null resolves to StatusType | null', function () {
        config()->set('ts-publish.enums.use_tolki_package', false);
        $data = (new ResourceTransformer(TernaryResource::class))->data();

        expect($data->properties['status_or_null']['type'])->toBe('StatusType | null');
        expect($data->properties['status_or_null']['optional'])->toBeFalse();
    });

    test('EnumResource::make vs EnumResource::make (same) resolves to StatusType', function () {
        config()->set('ts-publish.enums.use_tolki_package', false);
        $data = (new ResourceTransformer(TernaryResource::class))->data();

        expect($data->properties['status_or_status']['type'])->toBe('StatusType');
        expect($data->properties['status_or_status']['optional'])->toBeFalse();
    });

    test('EnumResource::make vs EnumResource::make (different) resolves to union', function () {
        config()->set('ts-publish.enums.use_tolki_package', false);
        $data = (new ResourceTransformer(TernaryResource::class))->data();

        expect($data->properties['status_or_visibility']['type'])->toContain('StatusType');
        expect($data->properties['status_or_visibility']['type'])->toContain('VisibilityType');
    });

    test('EnumResource::make vs EnumResource::make (different enums) resolves to AsEnum union with tolki enabled', function () {
        config()->set('ts-publish.enums.use_tolki_package', true);
        $data = (new ResourceTransformer(TernaryResource::class))->data();

        // The | null comes from Post.visibility being nullable in the DB, not from a null ternary branch.
        expect($data->properties['status_or_visibility']['type'])->toBe('AsEnum<typeof Status> | AsEnum<typeof Visibility> | null');
        expect($data->properties['status_or_visibility']['optional'])->toBeFalse();
    });

    test('Resource::make vs null resolves to CategoryResource | null', function () {
        $data = (new ResourceTransformer(TernaryResource::class))->data();

        expect($data->properties['category_or_null']['type'])->toBe('CategoryResource | null');
        expect($data->properties['category_or_null']['optional'])->toBeFalse();
    });

    test('Resource::make vs Resource::make (same) resolves to CategoryResource', function () {
        $data = (new ResourceTransformer(TernaryResource::class))->data();

        expect($data->properties['category_or_category']['type'])->toBe('CategoryResource');
        expect($data->properties['category_or_category']['optional'])->toBeFalse();
    });

    test('Resource::make vs Resource::make (different) resolves to union type', function () {
        $data = (new ResourceTransformer(TernaryResource::class))->data();

        expect($data->properties['category_or_user']['type'])->toContain('CategoryResource');
        expect($data->properties['category_or_user']['type'])->toContain('UserResource');
    });

    test('new Resource() vs null resolves to ImageResource | null', function () {
        $data = (new ResourceTransformer(TernaryResource::class))->data();

        expect($data->properties['image_or_null']['type'])->toBe('ImageResource | null');
        expect($data->properties['image_or_null']['optional'])->toBeFalse();
    });

    test('Resource::collection vs null resolves to CommentResource[] | null', function () {
        $data = (new ResourceTransformer(TernaryResource::class))->data();

        expect($data->properties['comments_or_null']['type'])->toBe('CommentResource[] | null');
        expect($data->properties['comments_or_null']['optional'])->toBeFalse();
    });

    test('Resource::collection vs Resource::collection (same) resolves to CommentResource[]', function () {
        $data = (new ResourceTransformer(TernaryResource::class))->data();

        expect($data->properties['comments_or_comments']['type'])->toBe('CommentResource[]');
        expect($data->properties['comments_or_comments']['optional'])->toBeFalse();
    });

    test('string property vs null resolves to string | null', function () {
        $data = (new ResourceTransformer(TernaryResource::class))->data();

        expect($data->properties['title_or_null']['type'])->toBe('string | null');
        expect($data->properties['title_or_null']['optional'])->toBeFalse();
    });

    test('number property vs null resolves to number | null', function () {
        $data = (new ResourceTransformer(TernaryResource::class))->data();

        expect($data->properties['word_count_or_null']['type'])->toBe('number | null');
        expect($data->properties['word_count_or_null']['optional'])->toBeFalse();
    });

    test('string literal vs string literal resolves to string', function () {
        $data = (new ResourceTransformer(TernaryResource::class))->data();

        expect($data->properties['pin_label']['type'])->toBe('string');
        expect($data->properties['pin_label']['optional'])->toBeFalse();
    });

    test('Elvis operator with string fallback resolves to string', function () {
        $data = (new ResourceTransformer(TernaryResource::class))->data();

        expect($data->properties['title_fallback']['type'])->toBe('string');
        expect($data->properties['title_fallback']['optional'])->toBeFalse();
    });

    test('ternary inside whenLoaded closure is optional with resource type', function () {
        $data = (new ResourceTransformer(TernaryResource::class))->data();

        expect($data->properties['category_when_loaded_or_null']['type'])->toContain('CategoryResource');
        expect($data->properties['category_when_loaded_or_null']['optional'])->toBeTrue();
    });

    test('ternary using $this->resource accessor resolves to CategoryResource | null', function () {
        $data = (new ResourceTransformer(TernaryResource::class))->data();

        expect($data->properties['category_resource_or_null']['type'])->toBe('CategoryResource | null');
        expect($data->properties['category_resource_or_null']['optional'])->toBeFalse();
    });

    test('EnumResource::make vs null resolves to AsEnum | null with tolki enabled', function () {
        config()->set('ts-publish.enums.use_tolki_package', true);
        $data = (new ResourceTransformer(TernaryResource::class))->data();

        expect($data->properties['status_or_null']['type'])->toBe('AsEnum<typeof Status> | null');
        expect($data->properties['status_or_null']['optional'])->toBeFalse();
    });

    test('EnumResource::make vs EnumResource::make (same) resolves to AsEnum with tolki enabled', function () {
        config()->set('ts-publish.enums.use_tolki_package', true);
        $data = (new ResourceTransformer(TernaryResource::class))->data();

        expect($data->properties['status_or_status']['type'])->toBe('AsEnum<typeof Status>');
        expect($data->properties['status_or_status']['optional'])->toBeFalse();
    });

    test('EnumResource::make vs $this->prop (same enum) resolves to AsEnum | XType with tolki enabled', function () {
        config()->set('ts-publish.enums.use_tolki_package', true);
        $data = (new ResourceTransformer(TernaryResource::class))->data();

        expect($data->properties['status_resource_or_type']['type'])->toBe('AsEnum<typeof Status> | StatusType');
        expect($data->properties['status_resource_or_type']['optional'])->toBeFalse();
    });

    test('EnumResource::make vs $this->prop (same enum) resolves to XType without tolki', function () {
        config()->set('ts-publish.enums.use_tolki_package', false);
        $data = (new ResourceTransformer(TernaryResource::class))->data();

        expect($data->properties['status_resource_or_type']['type'])->toBe('StatusType');
        expect($data->properties['status_resource_or_type']['optional'])->toBeFalse();
    });

    test('nested ternary resolves to string | null', function () {
        $data = (new ResourceTransformer(TernaryResource::class))->data();

        expect($data->properties['nested_ternary_label']['type'])->toBe('string | null');
        expect($data->properties['nested_ternary_label']['optional'])->toBeFalse();
    });
});

describe('ResourceTransformer with ResourceWrappedEnumResource — inline array enum resolution', function () {
    test('inline array with all EnumResource properties produces AsEnum types when tolki enabled', function () {
        config()->set('ts-publish.enums.use_tolki_package', true);
        $data = (new ResourceTransformer(ResourceWrappedEnumResource::class))->data();

        expect($data->properties['enums_array']['type'])
            ->toBe('{ status: AsEnum<typeof Status>; visibility: AsEnum<typeof Visibility> | null; priority: AsEnum<typeof Priority> | null }');
    });

    test('inline array with all EnumResource properties produces plain types when tolki disabled', function () {
        config()->set('ts-publish.enums.use_tolki_package', false);
        $data = (new ResourceTransformer(ResourceWrappedEnumResource::class))->data();

        expect($data->properties['enums_array']['type'])
            ->toBe('{ status: StatusType; visibility: VisibilityType | null; priority: PriorityType | null }');
    });

    test('mixed inline array produces plain types for direct enum access and AsEnum for EnumResource', function () {
        config()->set('ts-publish.enums.use_tolki_package', true);
        $data = (new ResourceTransformer(ResourceWrappedEnumResource::class))->data();

        $type = $data->properties['mixed_enums_array']['type'];

        expect($type)
            ->toContain('status_type: StatusType')
            ->toContain('visibility_type: VisibilityType | null')
            ->toContain('priority_type: PriorityType | null')
            ->toContain('status_resource_type: StatusType')
            ->toContain('visibility_resource_type: VisibilityType | null')
            ->toContain('priority_resource_type: PriorityType | null')
            ->toContain('status_enum: AsEnum<typeof Status>')
            ->toContain('visibility_enum: AsEnum<typeof Visibility> | null')
            ->toContain('priority_enum: AsEnum<typeof Priority> | null');
    });

    test('mixed inline array produces only plain types when tolki disabled', function () {
        config()->set('ts-publish.enums.use_tolki_package', false);
        $data = (new ResourceTransformer(ResourceWrappedEnumResource::class))->data();

        $type = $data->properties['mixed_enums_array']['type'];

        expect($type)
            ->not->toContain('AsEnum')
            ->toContain('status_enum: StatusType')
            ->toContain('visibility_enum: VisibilityType | null')
            ->toContain('priority_enum: PriorityType | null');
    });

    test('inline enum resource properties generate value imports (hasEnums) when tolki enabled', function () {
        config()->set('ts-publish.enums.use_tolki_package', true);
        $data = (new ResourceTransformer(ResourceWrappedEnumResource::class))->data();

        $allValueImports = $data->valueImports !== [] ? array_merge(...array_values($data->valueImports)) : [];

        expect($allValueImports)->toContain('Status');
        expect($allValueImports)->toContain('Visibility');
        expect($allValueImports)->toContain('Priority');
    });

    // whenNotNull($this->resource->priority, EnumResource::make($this->resource->priority)) — the value arm
    // (direct property access) and the default arm (EnumResource-wrapped) reach the same enum through two
    // genuinely different runtime shapes: a raw backing value (PriorityType) vs. the AsEnum wrapper. Pinned
    // at the transformer layer since ResourceAstAnalyzerTest only ever sees the analyzer's own alias-name
    // string ('PriorityType | null'), never the AsEnum<...> expansion rewriteEnumResourceTypes() applies.
    test('whenNotNull() default-arm union keeps both the AsEnum wrapper and the plain alias, required', function () {
        config()->set('ts-publish.enums.use_tolki_package', true);
        $data = (new ResourceTransformer(ResourceWrappedEnumResource::class))->data();

        expect($data->properties['priority_when_not_null_make']['type'])
            ->toBe('AsEnum<typeof Priority> | PriorityType | null')
            ->and($data->properties['priority_when_not_null_make']['optional'])->toBeFalse();
    });

    // Full-pipeline pin: rewriteEnumResourceTypes() only iterates top-level properties, so it never
    // touches this nested key — analyzeInlineArray()'s own mixed-arm expansion is what reaches the
    // emitted file, and the transformer must leave both arms standing.
    test('ternary_enums_array keeps both mixed arms through the full transformer pipeline', function () {
        config()->set('ts-publish.enums.use_tolki_package', true);
        $data = (new ResourceTransformer(ResourceWrappedEnumResource::class))->data();

        expect($data->properties['ternary_enums_array']['type'])
            ->toBe('{ status: AsEnum<typeof Status> | StatusType }');
    });
});

describe('ResourceTransformer with RelationChainResource — EnumResource::make() array-wrapped by a map() chain', function () {
    // Regression pin for change #2: EnumResource::make() wraps each element, so the JSON is an
    // array of flattened enum objects, not raw enum values — AsEnum<typeof Role>[] is correct;
    // the old RoleType[] (a plain, un-rewritten type import) was wrong.
    test('member_role_resources rewrites to AsEnum<typeof Role>[] with tolki enabled', function () {
        config()->set('ts-publish.enums.use_tolki_package', true);
        $data = (new ResourceTransformer(RelationChainResource::class))->data();

        expect($data->properties['member_role_resources']['type'])->toBe('AsEnum<typeof Role>[]')
            ->and($data->properties['member_role_resources']['optional'])->toBeFalse();
    });

    test('member_role_resources stays the plain array type without tolki', function () {
        config()->set('ts-publish.enums.use_tolki_package', false);
        $data = (new ResourceTransformer(RelationChainResource::class))->data();

        expect($data->properties['member_role_resources']['type'])->toBe('RoleType[]')
            ->and($data->properties['member_role_resources']['optional'])->toBeFalse();
    });

    // filter() clears sequential keys, so the map body's keyed Record arm can't be rebuilt by the
    // old AsEnum rewrite. The substitution-based rewrite reproduces it losslessly instead, AsEnum-
    // wrapping both the array arm and the keyed Record arm rather than collapsing to bare RoleType.
    test('member_role_resources_filtered AsEnum-wraps both its array and keyed Record arms', function () {
        config()->set('ts-publish.enums.use_tolki_package', true);
        $data = (new ResourceTransformer(RelationChainResource::class))->data();

        expect($data->properties['member_role_resources_filtered']['type'])
            ->toBe('AsEnum<typeof Role>[] | Record<string, AsEnum<typeof Role>>');
    });

    // Same keyed-Record shape nested inside an inline array: analyzeInlineArray() substitutes the
    // bare RoleType token in place too, so the nested union AsEnum-wraps both arms end-to-end,
    // exactly like member_role_resources_filtered above.
    test('wrapped_filtered AsEnum-wraps both arms of its union inside the inline array', function () {
        config()->set('ts-publish.enums.use_tolki_package', true);
        $data = (new ResourceTransformer(RelationChainResource::class))->data();

        expect($data->properties['wrapped_filtered']['type'])
            ->toBe('{ roles: AsEnum<typeof Role>[] | Record<string, AsEnum<typeof Role>> }');
    });
});

describe('ResourceTransformer with EnumCollectionResource — EnumResource::collection() shapes', function () {
    test('accessor-backed list<Enum> rewrites to AsEnum<typeof Status>[]', function () {
        config()->set('ts-publish.enums.use_tolki_package', true);
        $data = (new ResourceTransformer(EnumCollectionResource::class))->data();

        expect($data->properties['status_history']['type'])->toBe('AsEnum<typeof Status>[]')
            ->and($data->properties['status_history']['optional'])->toBeFalse();
    });

    // Matches the model precedent at workbench team.ts: week_days: AsEnum<typeof WeekDays>[] | null.
    test('AsEnumCollection cast rewrites to AsEnum<typeof WeekDays>[] | null', function () {
        config()->set('ts-publish.enums.use_tolki_package', true);
        $data = (new ResourceTransformer(EnumCollectionResource::class))->data();

        expect($data->properties['week_days']['type'])->toBe('AsEnum<typeof WeekDays>[] | null')
            ->and($data->properties['week_days']['optional'])->toBeFalse();
    });

    test('EnumResource::collection() inside an inline array keeps AsEnum<typeof WeekDays>[]', function () {
        config()->set('ts-publish.enums.use_tolki_package', true);
        $data = (new ResourceTransformer(EnumCollectionResource::class))->data();

        expect($data->properties['wrapped_week_days']['type'])
            ->toBe('{ week_days: AsEnum<typeof WeekDays>[] | null }');
    });

    // whenHas() never analyzes its value argument for a type, but IS checked for EnumResource
    // shape, so the wrapped first-class-callable value still gets the AsEnum rewrite — this is
    // the real reported bug pattern: $this->whenHas('kinds', EnumResource::collection(...)).
    test('first-class callable inside whenHas() rewrites to AsEnum<typeof WeekDays>[] | null', function () {
        config()->set('ts-publish.enums.use_tolki_package', true);
        $data = (new ResourceTransformer(EnumCollectionResource::class))->data();

        expect($data->properties['week_days_when_has']['type'])->toBe('AsEnum<typeof WeekDays>[] | null')
            ->and($data->properties['week_days_when_has']['optional'])->toBeTrue();
    });

    test('EnumResource::collection() value inside whenAppended() rewrites to AsEnum<typeof Status>[]', function () {
        config()->set('ts-publish.enums.use_tolki_package', true);
        $data = (new ResourceTransformer(EnumCollectionResource::class))->data();

        expect($data->properties['status_history_when_appended']['type'])->toBe('AsEnum<typeof Status>[]')
            ->and($data->properties['status_history_when_appended']['optional'])->toBeTrue();
    });

    // An explicit default arm's 'string' type can't fold into the old AsEnum rebuild. The
    // substitution-based rewrite reproduces the full union losslessly instead: the reported bug
    // was this property emitting the raw StatusType[] instead of the AsEnum-wrapped element type.
    test('whenHas() with an explicit default keeps the full union, AsEnum-wrapped', function () {
        config()->set('ts-publish.enums.use_tolki_package', true);
        $data = (new ResourceTransformer(EnumCollectionResource::class))->data();

        expect($data->properties['week_days_when_has_default']['type'])
            ->toBe('AsEnum<typeof WeekDays>[] | string | null')
            ->and($data->properties['week_days_when_has_default']['optional'])->toBeFalse();
    });

    test('local variable ->map() with an EnumResource::make() body rewrites to AsEnum<typeof Role>[]', function () {
        config()->set('ts-publish.enums.use_tolki_package', true);
        $data = (new ResourceTransformer(EnumCollectionResource::class))->data();

        expect($data->properties['members_via_var']['type'])->toBe('AsEnum<typeof Role>[]')
            ->and($data->properties['members_via_var']['optional'])->toBeTrue();
    });

    test('EnumResource::collection() properties generate value imports (hasEnums) when tolki enabled', function () {
        config()->set('ts-publish.enums.use_tolki_package', true);
        $data = (new ResourceTransformer(EnumCollectionResource::class))->data();

        $allValueImports = $data->valueImports !== [] ? array_merge(...array_values($data->valueImports)) : [];

        expect($allValueImports)->toContain('Status')
            ->and($allValueImports)->toContain('Role');
    });

    // No other property in this file keeps a bare StatusType token; it survives only because
    // wrapped_status_fallback's direct fallback arm genuinely needs it — that property alone is why
    // this asserts the opposite of what its old name claimed.
    test('bare StatusType type import survives only because of a genuine direct reader, not stale substitution', function () {
        config()->set('ts-publish.enums.use_tolki_package', true);
        $data = (new ResourceTransformer(EnumCollectionResource::class))->data();

        $statusTypeStillImported = isset($data->typeImports['../../enums'])
            && in_array('StatusType', $data->typeImports['../../enums'], true);

        expect($statusTypeStillImported)->toBeTrue();
    });

    // A bare enum read nested inside an inline array must keep its own type import: pins
    // member_role_snapshot's RoleType survives even though members_via_var, the only other
    // Role-typed property here, is EnumResource-wrapped rather than a direct reader.
    test('a bare enum read nested inside an inline array keeps its own RoleType import alive', function () {
        config()->set('ts-publish.enums.use_tolki_package', true);
        $data = (new ResourceTransformer(EnumCollectionResource::class))->data();

        expect($data->properties['member_role_snapshot']['type'])
            ->toBe('({ role: RoleType | null })[]');

        expect($data->typeImports)->toHaveKey('../../enums');
        expect($data->typeImports['../../enums'])->toContain('RoleType');
    });

    // Full-pipeline pin: the collection-wrapped arm substitutes to AsEnum<typeof Status>[], the
    // scalar direct arm stays bare StatusType, and that bare token's own type import must survive
    // since it is genuinely still referenced.
    test('wrapped_status_fallback substitutes only its array-shaped arm and keeps StatusType imported', function () {
        config()->set('ts-publish.enums.use_tolki_package', true);
        $data = (new ResourceTransformer(EnumCollectionResource::class))->data();

        expect($data->properties['wrapped_status_fallback']['type'])
            ->toBe('{ status: AsEnum<typeof Status>[] | StatusType }');

        expect($data->typeImports)->toHaveKey('../../enums');
        expect($data->typeImports['../../enums'])->toContain('StatusType');
    });

    // Top-level mirror of wrapped_status_fallback with the array-shaped arm on the *direct* side
    // instead of the wrapped one: only StatusType, the bare direct-read arm, is actually an array
    // — AsEnum<typeof Status> | StatusType[], not the old (AsEnum<typeof Status> | StatusType)[].
    test('latest_status_or_history reconstructs the heterogeneous mixed union without over-arraying it', function () {
        config()->set('ts-publish.enums.use_tolki_package', true);
        $data = (new ResourceTransformer(EnumCollectionResource::class))->data();

        expect($data->properties['latest_status_or_history']['type'])
            ->toBe('AsEnum<typeof Status> | StatusType[]');
    });

    test('a mixed ternary suffixes [] on whichever arm is a collection', function () {
        config()->set('ts-publish.enums.use_tolki_package', true);
        $properties = (new ResourceTransformer(EnumCollectionResource::class))->data()->properties;

        expect($properties['wrapped_history_or_scalar']['type'])->toBe('AsEnum<typeof Status>[] | StatusType')
            ->and($properties['wrapped_history_or_array']['type'])->toBe('AsEnum<typeof Status>[] | StatusType[]');
    });
});

// Regression: the mixed-ternary arm shape (Task 28) is recorded once, in TernaryHandler, but is
// only useful if every ResourceAnalysis-building collector threads it through to the transformer —
// not only ResourceAstAnalyzer::analyzeReturnArray(), the single path the first fix wired up.
describe('ResourceTransformer with mixed-ternary collector regressions', function () {
    test('mergeReturnBranches() carries the arm shape across multiple return branches', function () {
        config()->set('ts-publish.enums.use_tolki_package', true);
        $properties = (new ResourceTransformer(MixedEnumReturnBranchesResource::class))->data()->properties;

        expect($properties['wrapped_history_or_scalar']['type'])->toBe('AsEnum<typeof Status>[] | StatusType');
    });

    test('ThisPropertyHandler::extractPropertiesFromArray() carries the arm shape through $this->merge()', function () {
        config()->set('ts-publish.enums.use_tolki_package', true);
        $properties = (new ResourceTransformer(MixedEnumMergedResource::class))->data()->properties;

        expect($properties['wrapped_history_or_scalar_merged']['type'])->toBe('AsEnum<typeof Status>[] | StatusType');
    });

    test('collectVariableArrayAssignments() carries the arm shape through a variable-building spread method', function () {
        config()->set('ts-publish.enums.use_tolki_package', true);
        $properties = (new ResourceTransformer(MixedEnumMergedResource::class))->data()->properties;

        expect($properties['wrapped_history_or_scalar_assigned']['type'])->toBe('AsEnum<typeof Status>[] | StatusType');
    });
});

describe('ResourceTransformer with ImageDimensionsResource', function () {
    test('a when() default keeps both inline-object arms intact', function () {
        $properties = (new ResourceTransformer(ImageDimensionsResource::class))->data()->properties;

        expect($properties['box']['type'])
            ->toBe('{ width: number | null; height: number | null } | { width: number | null }');
    });
});

describe('ResourceTransformer with ImageNullableArmsResource', function () {
    test('ternary and Elvis arms share one trailing null', function () {
        $properties = (new ResourceTransformer(ImageNullableArmsResource::class))->data()->properties;

        expect($properties['size']['type'])->toBe('number | string | null')
            ->and($properties['label']['type'])->toBe('string | number | null');
    });
});

describe('ResourceTransformer with PostSpotlightResource', function () {
    test('a single-FQCN accessor imports its model under a foreign key and inside an inline member', function () {
        $transformer = new ResourceTransformer(PostSpotlightResource::class);
        $properties = $transformer->data()->properties;

        expect($properties['headline']['type'])->toBe('Comment | null')
            ->and($properties['spotlight']['type'])->toBe('{ comment: Comment | null }')
            ->and(array_merge(...array_values($transformer->typeImports)))->toContain('Comment');
    });
});

describe('ResourceTransformer with TeamStatusAuditResource', function () {
    // Both arms read the same list-shaped accessor, so the merged union kept one member and only the
    // per-arm shape still says the [] belongs on both. The direct arm surviving means its bare type
    // token is spelled in the emitted type, so its type import has to travel out with it.
    test('a nested mixed EnumResource ternary keeps both arms and the direct arm keeps its type import', function () {
        $transformer = new ResourceTransformer(TeamStatusAuditResource::class);
        $data = $transformer->data();

        expect($data->properties['audit']['type'])->toBe('{ status: AsEnum<typeof Status>[] | StatusType[] }')
            ->and(implode(' ', array_merge(...array_values($transformer->typeImports))))->toContain('StatusType')
            ->and(implode(' ', array_merge(...array_values($transformer->valueImports))))->toContain('Status');
    });
});

describe('ResourceTransformer with PostEnumTrioResource', function () {
    test('repeated enum members keep their own type through the positional queue', function () {
        $properties = (new ResourceTransformer(PostEnumTrioResource::class))->data()->properties;

        expect($properties['trio']['type'])->toBe('{ a: StatusType; b: StatusType; c: PriorityType | null }');
    });
});

describe('ResourceTransformer with PostFlatCollection (typeAlias)', function () {
    test('typeAlias is publicly accessible after transform', function () {
        $transformer = new ResourceTransformer(PostFlatCollection::class);
        $transformer->transform();

        expect($transformer->typeAlias)->toBe('PostResource[]');
    })->skip(fn () => ! version_compare(app()->version(), '13', '>='));
})->group('transformer');

describe('ResourceTransformer with morphTo-backed resources', function () {
    beforeEach(function () {
        // The publish command builds this map up front; a bare transformer test has to seed it.
        resolve(ModelAttributeResolver::class)->buildMorphTargetMap([
            Post::class, Product::class, User::class, CrmUser::class, Image::class,
        ]);
    });

    // The union names every morph parent, so every parent needs an import: resolveRelation()'s
    // singular modelFqcn is null for a MorphTo and could never carry them.
    test('a morphTo property imports and aliases every parent model', function () {
        $data = (new ResourceTransformer(ImageMorphResource::class))->data();

        $allTypeImports = array_merge(...array_values($data->typeImports));

        expect($data->properties['imageable']['type'])->toBe('Post | Product | WorkbenchUser | CrmUser')
            ->and($data->properties['imageable_when_loaded']['type'])->toBe('Post | Product | WorkbenchUser | CrmUser')
            ->and($allTypeImports)->toContain('Post', 'Product', 'User as WorkbenchUser', 'User as CrmUser');
    });

    test('the model-delegated analysis imports the morph parents too', function () {
        $data = (new ResourceTransformer(ImageDelegatedResource::class))->data();

        $allTypeImports = array_merge(...array_values($data->typeImports));

        expect($data->properties['imageable']['type'])->toBe('Post | Product | WorkbenchUser | CrmUser')
            ->and($allTypeImports)->toContain('Post', 'Product', 'User as WorkbenchUser', 'User as CrmUser');
    });

    test('a get-having accessor with an unresolvable type survives model-delegated analysis as unknown', function () {
        // no_docblock_accessor has a real getter (unlike search_index's write-only case), just
        // nothing to read a type from. ModelTransformer::transformMutators() keeps such a mutator
        // as 'unknown' rather than omitting it, and buildModelDelegatedAnalysis() must agree.
        $data = (new ResourceTransformer(ImageDelegatedResource::class))->data();

        expect($data->properties)->toHaveKey('no_docblock_accessor')
            ->and($data->properties['no_docblock_accessor']['type'])->toBe('unknown');
    });

    // A widened container names its element in both arms; aliasing only the first left the second bare.
    test('an aliased element is replaced in every arm of a widened collection type', function () {
        $data = (new ResourceTransformer(ImageMorphResource::class))->data();

        expect($data->properties['uploaders_from_docblock']['type'])
            ->toBe('WorkbenchUser[] | Record<string, WorkbenchUser>');
    });

    // The same pass must still take exactly one occurrence when two FQCNs share the basename.
    test('a same-basename union still gets one replacement per aliasing pass', function () {
        $data = (new ResourceTransformer(ImageMorphResource::class))->data();

        expect($data->properties['imageable']['type'])->toBe('Post | Product | WorkbenchUser | CrmUser');
    });

    // #[TsType(['type' => ..., 'import' => ...])] on a cast: no analysis path carried the author's
    // import into a resource, so the token was emitted alone.
    test('a #[TsType(import:)] cast reaching a resource brings its import', function () {
        $data = (new ResourceTransformer(ImageDelegatedResource::class))->data();

        expect($data->properties['config_from_docblock']['type'])->toBe('MenuSettingsType')
            ->and($data->typeImports['@js/types/settings'] ?? [])->toContain('MenuSettingsType');
    });
})->group('transformer');

describe('ResourceTransformer import alias resolution for same basename and same parent segment', function () {
    test('same basename and same parent segment produce distinct aliases', function () {
        // Kpi::reportable() morphs to both Report models; their nearest namespace segment is
        // identically 'Report', reproducing the eagle MailPrice collision at depth 1.
        resolve(ModelAttributeResolver::class)->buildMorphTargetMap([
            Kpi::class,
            SalesReport::class,
            MarketingReport::class,
        ]);

        $data = (new ResourceTransformer(KpiResource::class))->data();

        expect($data->properties['reportable']['type'])
            ->toContain('SalesReportReport')
            ->toContain('MarketingReportReport');

        $allImports = array_merge(...array_values($data->typeImports));
        expect($allImports)->toContain('Report as SalesReportReport')
            ->and($allImports)->toContain('Report as MarketingReportReport');

        preg_match_all('/as (\w+)/', implode(' ', $allImports), $matches);
        expect($matches[1])->toBe(array_unique($matches[1]));
    });
});

describe('ResourceTransformer inline model FQCN multiplicity through analyzer merges', function () {
    // Warehouse::regionalHub() has two distinct User FQCNs occurrence-ordered by relation name.
    // mergeReturnBranches() unions each branch's inlineModelFqcns per key; deduping that union loses
    // branch 2's repeat once the two branch types join into one union string.
    test('mergeReturnBranches() keeps every branch occurrence aliased to its own FQCN', function () {
        $data = (new ResourceTransformer(BranchedInlineFqcnResource::class))->data();

        expect($data->properties['regional_hub_contacts']['type'])->toBe(
            '{ primaryContact: CrmUser | null; manager: WorkbenchUser | null }'
            .' | { manager: WorkbenchUser | null; secondaryContact: CrmUser | null; primaryContact: CrmUser | null } | null'
        );
    });

    // Analyzed directly (not spread), a property's own occurrences are pushed raw with no merge
    // involved, so this is unaffected by both fixes — a control the two ChildInlineFqcnResource cases
    // below are compared against.
    test('InheritedInlineFqcnResource resolves both properties correctly analyzed on its own', function () {
        $data = (new ResourceTransformer(InheritedInlineFqcnResource::class))->data();

        expect($data->properties['regional_hub_contacts']['type'])
            ->toBe('{ primaryContact: CrmUser | null; manager: WorkbenchUser | null; secondaryContact: CrmUser | null } | null')
            ->and($data->properties['regional_hub_leads']['type'])
            ->toBe('{ primaryContact: CrmUser | null; manager: WorkbenchUser | null; secondaryContact: CrmUser | null } | null');
    });

    // Two-sided: regional_hub_contacts overrides the parent's spread-in property, exercising the
    // child-overrides-parent unset(); regional_hub_leads passes through unmodified, exercising
    // syncAnalysisMaps()'s dedupe on its own. Both fixes must land — either alone still fails.
    test('ChildInlineFqcnResource resolves an overridden and a pass-through property correctly', function () {
        $data = (new ResourceTransformer(ChildInlineFqcnResource::class))->data();

        expect($data->properties['regional_hub_contacts']['type'])
            ->toBe('{ manager: WorkbenchUser | null; secondaryContact: CrmUser | null; primaryContact: CrmUser | null; last_checked_by: Image | WorkbenchUser | null } | null')
            ->and($data->properties['regional_hub_leads']['type'])
            ->toBe('{ primaryContact: CrmUser | null; manager: WorkbenchUser | null; secondaryContact: CrmUser | null } | null');
    });

    // WarehouseResource::probe_nested = ['first' => $this->last_user_activity_by, 'second' => $this->manager].
    // first is itself a multi-FQCN accessor (CrmUser|User); its two arms plus second's single arm must
    // each alias to their own FQCN, not collapse to one bare 'User' repeated three times.
    test('an inline array member keeps both arms of a multi-FQCN accessor aliased apart', function () {
        config()->set('ts-publish.namespace_strip_prefix', 'Workbench\\');

        $data = (new ResourceTransformer(WarehouseResource::class))->data();

        expect($data->properties['probe_nested']['type'])
            ->toBe('{ first: CrmUser | ModelsUser | null; second: ModelsUser | null }');
    });
});

describe('ResourceTransformer with NamedArgsConditionalResource', function () {
    test('reads the conditional family\'s named arguments the way Laravel binds them', function () {
        $properties = (new ResourceTransformer(NamedArgsConditionalResource::class))->data()->properties;

        expect($properties['not_null_named_default']['type'])->toBe('string | number')
            ->and($properties['not_null_named_default']['optional'])->toBeFalse()
            ->and($properties['when_all_named']['type'])->toBe('string | number')
            ->and($properties['when_all_named']['optional'])->toBeFalse()
            ->and($properties['loaded_named_default']['type'])->toBe('Comment[]')
            ->and($properties['loaded_named_default']['optional'])->toBeFalse()
            ->and($properties['counted_named_out_of_order']['type'])->toBe('number')
            ->and($properties['counted_named_out_of_order']['optional'])->toBeFalse();
    });
});

describe('ResourceTransformer with SameBasenameModelTrioResource', function () {
    test('a repeated same-basename model inside one inline array keeps its own alias each time', function () {
        $data = (new ResourceTransformer(SameBasenameModelTrioResource::class))->data();
        $trio = $data->properties['trio']['type'];

        // Capture the alias prefix rather than the whole name (they follow the namespace-prefix scheme
        // import-name-registry.md documents), so a regression to the bare name still fails on the
        // comparison below instead of on the count. Positions 1 and 3 must match while 2 differs.
        preg_match_all('/(\w*)User\b/', $trio, $m);
        expect($m[1])->toHaveCount(3)
            ->and($m[1][0])->toBe($m[1][2])
            ->and($m[1][1])->not->toBe($m[1][0])
            // Pins the shape too: a flat inline array renders one object, never a union of two, and
            // reaches InlineArrayHandler's own queue instead of mergeUnion().
            ->and($trio)->toBe('{ a: CrmUser | null } | { b: WorkbenchUser | null; c: CrmUser | null }');
    });

    // collapsed_arms' two inner arms are both Crm\Models\User, so analyzeClosureUnion() folds them to
    // one rendered token while keeping both branch results. The branch-level FQCNs must dedupe to match,
    // or the manager in the second arm consumes the surplus entry and aliases as CrmUser.
    test('branch arms that render one token consume one entry of the alias queue', function () {
        $data = (new ResourceTransformer(SameBasenameModelTrioResource::class))->data();

        expect($data->properties['collapsed_arms']['type'])
            ->toBe('CrmUser | { c: WorkbenchUser | null } | null');
    });

    // reversed_arms and control_arms are one expression with its arms swapped, so both hold the same two
    // FQCNs. Prepending the branch-level entry queues [Crm, App] for both, which silently exchanges the
    // two User identities in the reversed orientation while leaving the control one right.
    test('a whole-branch model arm keeps its position against an inline-object arm', function () {
        $data = (new ResourceTransformer(SameBasenameModelTrioResource::class))->data();

        expect($data->properties['reversed_arms']['type'])
            ->toBe('{ c: WorkbenchUser | null } | CrmUser | null')
            ->and($data->properties['control_arms']['type'])
            ->toBe('CrmUser | { c: WorkbenchUser | null } | null');
    });
});
