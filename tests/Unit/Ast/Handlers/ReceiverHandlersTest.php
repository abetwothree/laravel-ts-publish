<?php

declare(strict_types=1);

use AbeTwoThree\LaravelTsPublish\Analyzers\ResourceAstAnalyzer;
use AbeTwoThree\LaravelTsPublish\Ast\Analysis\AnalysisScope;
use AbeTwoThree\LaravelTsPublish\Ast\AstEngine;
use AbeTwoThree\LaravelTsPublish\Ast\Concerns\CollectsInstanceofGuards;
use AbeTwoThree\LaravelTsPublish\Ast\Concerns\CollectsLocalVarBindings;
use AbeTwoThree\LaravelTsPublish\Ast\Dispatch\ControllerExpressionHandlers;
use AbeTwoThree\LaravelTsPublish\Ast\Dispatch\ResourceExpressionHandlers;
use AbeTwoThree\LaravelTsPublish\Ast\Handlers\KnownMethodRuleHandler;
use AbeTwoThree\LaravelTsPublish\Ast\Handlers\PropertyChainHandler;
use AbeTwoThree\LaravelTsPublish\Ast\Handlers\ReceiverMethodCallHandler;
use AbeTwoThree\LaravelTsPublish\Ast\Handlers\ReceiverPropertyFetchHandler;
use AbeTwoThree\LaravelTsPublish\Ast\Handlers\RelationFilterHandler;
use AbeTwoThree\LaravelTsPublish\Ast\Members\MethodReturnTypeResolver;
use AbeTwoThree\LaravelTsPublish\Ast\Parsing\AstParser;
use AbeTwoThree\LaravelTsPublish\Ast\Receivers\ReceiverClassResolver;
use AbeTwoThree\LaravelTsPublish\Ast\Receivers\ReceiverMethodReturnResolver;
use AbeTwoThree\LaravelTsPublish\Ast\Receivers\ReceiverType;
use AbeTwoThree\LaravelTsPublish\Ast\Resources\ModelClassResolver;
use AbeTwoThree\LaravelTsPublish\Cache\PublishedModelRegistry;
use AbeTwoThree\LaravelTsPublish\Facades\LaravelTsPublish;
use AbeTwoThree\LaravelTsPublish\Generators\ResourceGenerator;
use AbeTwoThree\LaravelTsPublish\LaravelTsPublish as LaravelTsPublishService;
use AbeTwoThree\LaravelTsPublish\ModelAttributeResolver;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Casts\CountingCastable;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Controllers\PostReceiverController;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Controllers\ProxyRelationController;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\MorphSpellingsResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\NamesakeInvoiceResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\NamesakeProtectedResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\NamesakeWrappedResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\NullableGuardedPostResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\NullableShorthandWrappedResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\ProxyAuthenticatableResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\ProxyCollectedPostCollection;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\ProxyDeclaredPageCollection;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\ProxyDepotResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\ProxyGuardedMediaResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\ProxyGuardedPageCollection;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\ProxyMediaOrPostResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\ProxyMediaOrStringResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\ProxyModelPostResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\ProxyPostOrImageResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\ProxyPostOrStringResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\ProxyRawPostCollection;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\ProxyRelationPostResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\VariableHeldModelResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Models\AbstractKeyedModel;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Models\AbstractKeyTypeOverrideModel;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Models\AbstractUniqueIdsKeyModel;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Models\AuthoredPost;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Models\ComputedIdentifierUser;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Models\EmailIdentifierUser;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Models\IntKeyedModel;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Models\LabeledPost;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Models\NamesakeInvoice;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Models\NullKeyPost;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Models\ProxyRelationPost;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Models\ProxyShadowPost;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Models\SingleModelStepPost;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Models\UuidKeyedModel;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\ValueObjects\NamesakeStatusCard;
use AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures\AccessorNamedKeysResource;
use AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures\AppendingModelFilterOverrideModel;
use AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures\CallPathAbstractModel;
use AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures\CastablePostResource;
use AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures\ClassTypedFilterOverrideModel;
use AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures\ClosureNamesakePost;
use AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures\CollectionMemberModel;
use AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures\DocblockFilterOverrideModel;
use AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures\FilterOverrideModel;
use AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures\ForwardedMethodPost;
use AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures\ForwardedMethodResource;
use AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures\GuardOrderResource;
use AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures\GuardWritePostResource;
use AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures\HeldReceiversCommentResource;
use AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures\HiddenFilterOverrideModel;
use AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures\ListTypedFilterOverrideModel;
use AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures\MagicPost;
use AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures\NarrowingGuardBodyResource;
use AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures\ParamGuardAttachmentResource;
use AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures\PostScoreSource;
use AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures\ReceiverIntegerKeyModel;
use AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures\ReceiverMethodProbe;
use AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures\ReceiverProbeEnum;
use AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures\ReceiverProbeResource;
use AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures\ReceiverShapedToArrayModel;
use AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures\ReceiverStringDateProbe;
use AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures\ReceiverVarProbe;
use AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures\UntypedFilterOverrideModel;
use AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures\UserFilterOverrideModel;
use AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures\UserListFilterOverrideModel;
use AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures\VagueDirtyOverrideModel;
use AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures\VagueFilterOverrideModel;
use AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures\VisibleFilterOverrideModel;
use Illuminate\Contracts\Auth\CanResetPassword;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;
use Illuminate\Support\Fluent;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\NullsafeMethodCall;
use PhpParser\Node\Expr\PropertyFetch;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Stmt;
use PhpParser\Node\Stmt\Return_;
use Workbench\App\Enums\MediaType;
use Workbench\App\Enums\Priority;
use Workbench\App\Http\Resources\ClosureResourceRootResource;
use Workbench\App\Http\Resources\CommentRelationFiltersResource;
use Workbench\App\Http\Resources\CommentResource;
use Workbench\App\Http\Resources\ImageResource;
use Workbench\App\Http\Resources\MediaTypeInstanceOfResource;
use Workbench\App\Http\Resources\MediaTypeResource;
use Workbench\App\Http\Resources\ModelWrappedPropResource;
use Workbench\App\Http\Resources\NarrowedImageableResource;
use Workbench\App\Http\Resources\NarrowedParentResource;
use Workbench\App\Http\Resources\NarrowedWiderTestResource;
use Workbench\App\Http\Resources\PostCollection;
use Workbench\App\Http\Resources\PostResource;
use Workbench\App\Http\Resources\PostStatsResource;
use Workbench\App\Http\Resources\ReceiverMethodResource;
use Workbench\App\Http\Resources\ReceiverPropertyResource;
use Workbench\App\Http\Resources\RosterSlotResource;
use Workbench\App\Http\Resources\SpacedDocblockResource;
use Workbench\App\Http\Resources\TeamSubscriberLocalResource;
use Workbench\App\Http\Resources\TeamSubscriberResource;
use Workbench\App\Models\Attachment;
use Workbench\App\Models\Comment;
use Workbench\App\Models\Depot;
use Workbench\App\Models\Image;
use Workbench\App\Models\Post;
use Workbench\App\Models\Product;
use Workbench\App\Models\Release;
use Workbench\App\Models\Team;
use Workbench\App\Models\User;
use Workbench\Crm\Models\User as CrmUser;

/** Parse one expression statement written with fully-qualified names. */
function receiverHandlerExpr(string $php): Expr
{
    return new AstParser()->parseSource('<?php '.$php.';')[0]->expr;
}

/** A scope whose subject is the method probe, backed by Post. */
function receiverProbeScope(): AnalysisScope
{
    return new AnalysisScope(new ReflectionClass(ReceiverMethodProbe::class), Post::class);
}

/**
 * ReceiverPropertyResource's published property types, keyed by property name.
 *
 * @return array<string, string>
 */
function receiverPropertyTypes(): array
{
    return collect(new ResourceAstAnalyzer(new ReflectionClass(ReceiverPropertyResource::class), Comment::class)->analyze()->properties)
        ->mapWithKeys(fn (array $p): array => [$p['name'] => $p['type']])
        ->all();
}

/** A scope whose subject is the property resource, with `$post` bound the way its body binds it. */
function receiverPropertyScope(): AnalysisScope
{
    $scope = new AnalysisScope(new ReflectionClass(ReceiverPropertyResource::class), Comment::class);
    $scope->localVarBindings['post'] = receiverHandlerExpr('$this->post');

    return $scope;
}

describe('ReceiverMethodCallHandler through the resource analyzer', function () {
    test('follows method return types through every receiver kind', function () {
        $props = collect(new ResourceAstAnalyzer(new ReflectionClass(ReceiverMethodResource::class), Post::class)->analyze()->properties)
            ->mapWithKeys(fn (array $p): array => [$p['name'] => $p['type']]);

        expect($props->all())->toMatchArray([
            'priority_label' => 'string',
            'priority_label_nullsafe' => 'string | null',
            'resource_priority_label' => 'string',
            'resource_priority_label_nullsafe' => 'string | null',
            'resource_published_date' => 'string',
            'published_date' => 'string',
            'author_morph' => 'string | null',
            'record_class' => 'string',
            'from_label' => 'string',
            'author_fresh' => 'User | null',
            'author_fresh_nullsafe' => 'User | null',
            'resource_author_fresh' => 'User | null',
            'resource_author_fresh_nullsafe' => 'User | null',
        ]);
    });

    test('a method read through a Castable-cast column types from its caster without running castUsing()', function () {
        CountingCastable::$calls = 0;

        $props = collect(resolve(AstEngine::class)->analyze(CastablePostResource::class)->properties)
            ->mapWithKeys(fn (array $p): array => [$p['name'] => $p['type']]);

        expect($props->all())->toBe(['title_label' => 'string', 'options_label' => 'unknown'])
            ->and(CountingCastable::$calls)->toBe(0);
    });

    test('getKey and modelKeys type from the receiver model key type', function () {
        $props = collect(new ResourceAstAnalyzer(new ReflectionClass(ReceiverMethodResource::class), Post::class)->analyze()->properties)
            ->mapWithKeys(fn (array $p): array => [$p['name'] => $p['type']]);

        expect($props['author_key'])->toBe('number | null')
            ->and($props['comment_ids'])->toBe('number[]')
            ->and($props['resource_comment_ids'])->toBe('number[]')
            ->and($props['resource_author_key'])->toBe('number | null');
    });

    test('getKey and modelKeys type the same through the resource proxy and the model', function (string $php, string $type) {
        $analyzer = new ResourceAstAnalyzer(new ReflectionClass(ReceiverMethodResource::class), Post::class);

        expect($analyzer->resolve(receiverHandlerExpr($php))['type'])->toBe($type);
    })->with([
        ['$this->author->getKey()', 'number'],
        ['$this->resource->author->getKey()', 'number'],
        ['$this->author?->getKey()', 'number | null'],
        ['$this->resource?->author?->getKey()', 'number | null'],
        ['$this->resource?->getKey()', 'number | null'],
        ['$this->comments?->modelKeys()', 'number[] | null'],
        ['$this->resource?->comments?->modelKeys()', 'number[] | null'],
    ]);

    test('a bare $this->method() on a resource publishes the type of its $this->resource twin', function () {
        $props = collect(new ResourceAstAnalyzer(new ReflectionClass(ReceiverMethodResource::class), Post::class)->analyze()->properties)
            ->mapWithKeys(fn (array $p): array => [$p['name'] => $p['type']]);

        expect($props['bare_key'])->toBe('number')
            ->and($props['resource_key'])->toBe($props['bare_key'])
            ->and($props['bare_comments_count'])->toBe('number')
            ->and($props['resource_comments_count'])->toBe($props['bare_comments_count']);
    });

    test('a method only the model declares types the same bare, nullsafe on $this, and through $this->resource', function (string $call, string $type) {
        $analyzer = new ResourceAstAnalyzer(new ReflectionClass(ReceiverMethodResource::class), Post::class);

        expect($analyzer->resolve(receiverHandlerExpr('$this->'.$call))['type'])->toBe($type)
            ->and($analyzer->resolve(receiverHandlerExpr('$this?->'.$call))['type'])->toBe($type)
            ->and($analyzer->resolve(receiverHandlerExpr('$this->resource->'.$call))['type'])->toBe($type);
    })->with([
        ['getKey()', 'number'],
        ['commentsCount()', 'number'],
        ['fresh()', 'Post | null'],
        ['replicate()', 'Post'],
    ]);

    test('an integer key type publishes number in both $this->resource getKey() spellings', function () {
        $analyzer = new ResourceAstAnalyzer(new ReflectionClass(ReceiverProbeResource::class), ReceiverIntegerKeyModel::class);

        expect($analyzer->resolve(receiverHandlerExpr('$this->resource->getKey()'))['type'])->toBe('number')
            ->and($analyzer->resolve(receiverHandlerExpr('$this->resource?->getKey()'))['type'])->toBe('number | null');
    });

    test('a DateTime return publishes the object json_encode() writes, in every spelling', function (string $php, string $type) {
        $analyzer = new ResourceAstAnalyzer(new ReflectionClass(ReceiverMethodResource::class), Post::class);

        expect($analyzer->resolve(receiverHandlerExpr($php))['type'])->toBe($type);
    })->with([
        ['$this->resource->published_at->toDateTime()', LaravelTsPublishService::DATE_TIME_OBJECT_TYPE],
        ['$this->published_at?->toDateTime()', LaravelTsPublishService::DATE_TIME_OBJECT_TYPE.' | null'],
        ['$this->resource?->published_at?->toDateTime()', LaravelTsPublishService::DATE_TIME_OBJECT_TYPE.' | null'],
    ]);
});

describe('ReceiverMethodReturnResolver', function () {
    test('a public method types on any receiver, a protected one only from inside', function () {
        $resolver = resolve(ReceiverMethodReturnResolver::class);
        $probe = ReceiverType::of(ReceiverMethodProbe::class);

        expect($resolver->resolve($probe, 'label', receiverProbeScope())['type'] ?? null)->toBe('string')
            ->and($resolver->resolve($probe, 'secret', receiverProbeScope()))->toBeNull()
            ->and($resolver->resolve($probe, 'secret', receiverProbeScope(), fromInside: true)['type'] ?? null)->toBe('number');
    });

    test('a receiver-returning method keeps the receiver type, with null when the return admits it', function () {
        $resolver = resolve(ReceiverMethodReturnResolver::class);

        expect($resolver->resolve(ReceiverType::of(User::class), 'fresh', receiverProbeScope()))
            ->toBe(['type' => 'User | null', 'optional' => false, 'modelFqcn' => User::class])
            ->and($resolver->resolve(ReceiverType::of(ReceiverProbeEnum::class), 'next', receiverProbeScope()))
            ->toBe(['type' => 'ReceiverProbeEnumType | null', 'optional' => false, 'directEnumFqcn' => ReceiverProbeEnum::class])
            ->and($resolver->resolve(ReceiverType::of(Carbon::class), 'setTimezone', receiverProbeScope())['type'] ?? null)
            ->toBe('string');
    });

    test('a __toString class publishes what json_encode() writes, and a string only when it serializes as one', function () {
        $resolver = resolve(ReceiverMethodReturnResolver::class);
        $probe = ReceiverType::of(ReceiverMethodProbe::class);
        $interval = LaravelTsPublishService::CARBON_INTERVAL_OBJECT_TYPE;

        expect($resolver->resolve($probe, 'interval', receiverProbeScope())['type'] ?? null)->toBe($interval)
            ->and($resolver->resolve($probe, 'docblockInterval', receiverProbeScope())['type'] ?? null)->toBe($interval)
            ->and($resolver->resolve(ReceiverType::of(Carbon::class), 'diff', receiverProbeScope())['type'] ?? null)->toBe($interval)
            ->and($resolver->resolve($probe, 'docblockIntervals', receiverProbeScope())['type'] ?? null)->toBe('('.$interval.')[]')
            ->and($resolver->resolve($probe, 'text', receiverProbeScope())['type'] ?? null)->toBe('string');
    });

    test('a DateTime that is not JsonSerializable publishes its object, while Carbon keeps its string', function () {
        $resolver = resolve(ReceiverMethodReturnResolver::class);
        $date = LaravelTsPublishService::DATE_TIME_OBJECT_TYPE;

        expect($resolver->resolve(ReceiverType::of(Carbon::class), 'toDateTime', receiverProbeScope())['type'] ?? null)->toBe($date)
            ->and($resolver->resolve(ReceiverType::of(ReceiverMethodProbe::class), 'plainDate', receiverProbeScope())['type'] ?? null)
            ->toBe('string | '.$date.' | '.$date)
            ->and($resolver->resolve(ReceiverType::of(Carbon::class), 'toMutable', receiverProbeScope())['type'] ?? null)->toBe('string');
    });

    test('a framework or abstract model token declines, because no file is published for it', function () {
        $resolver = resolve(ReceiverMethodReturnResolver::class);

        expect($resolver->resolve(ReceiverType::of(User::class), 'resolveChildRouteBinding', receiverProbeScope()))->toBeNull()
            ->and($resolver->resolve(ReceiverType::of(User::class), 'newPivot', receiverProbeScope()))->toBeNull()
            ->and($resolver->resolve(ReceiverType::of(Model::class), 'fresh', receiverProbeScope()))->toBeNull();
    });

    test('an inherited route binding is the receiver itself or null, though Laravel declares Model|null', function () {
        $resolver = resolve(ReceiverMethodReturnResolver::class);

        expect($resolver->resolve(ReceiverType::of(User::class), 'resolveRouteBinding', receiverProbeScope()))
            ->toBe(['type' => 'User | null', 'optional' => false, 'modelFqcn' => User::class])
            ->and($resolver->resolve(ReceiverType::of(Post::class), 'resolveSoftDeletableRouteBinding', receiverProbeScope()))
            ->toBe(['type' => 'Post | null', 'optional' => false, 'modelFqcn' => Post::class])
            ->and($resolver->resolve(ReceiverType::of(Model::class), 'resolveRouteBinding', receiverProbeScope()))->toBeNull();
    });

    test('a union types only when every class types the method', function () {
        $resolver = resolve(ReceiverMethodReturnResolver::class);

        expect($resolver->resolve(new ReceiverType([Priority::class, Post::class]), 'label', receiverProbeScope()))->toBeNull()
            ->and($resolver->resolve(new ReceiverType([User::class, Post::class]), 'getMorphClass', receiverProbeScope()))
            ->toBe(['type' => 'string', 'optional' => false]);
    });

    test('a model running Model::toArray() publishes an attribute record, where an abstract model declines', function () {
        $resolver = resolve(ReceiverMethodReturnResolver::class);

        expect($resolver->resolve(ReceiverType::of(Post::class), 'toArray', receiverProbeScope()))
            ->toBe(['type' => 'Record<string, unknown>', 'optional' => false])
            ->and($resolver->resolve(ReceiverType::of(CallPathAbstractModel::class), 'toArray', receiverProbeScope()))->toBeNull();
    });

    test('a relation\'s collection lists each model\'s toArray(), an attribute record unless the model overrides it', function () {
        $resolver = resolve(ReceiverMethodReturnResolver::class);
        $collection = fn (string $model): ReceiverType => new ReceiverType([EloquentCollection::class], elementModel: $model);

        expect($resolver->resolve($collection(Post::class), 'toArray', receiverProbeScope()))
            ->toBe(['type' => 'Record<string, unknown>[]', 'optional' => false])
            ->and($resolver->resolve($collection(ReceiverShapedToArrayModel::class), 'toArray', receiverProbeScope()))
            ->toBe(['type' => 'unknown[]', 'optional' => false]);
    });

    test('a model toArray() override declines even when it declares a precise shape', function () {
        $resolver = resolve(ReceiverMethodReturnResolver::class);
        $model = ReceiverType::of(ReceiverShapedToArrayModel::class);

        expect($resolver->resolve($model, 'toArray', receiverProbeScope()))->toBeNull()
            ->and($resolver->resolve($model, 'shape', receiverProbeScope())['type'] ?? null)->toBe('{ id: number }');
    });

    test('a vague array return declines, save the attribute record a concrete model inherits from Model', function (string $model, string $method, ?string $type) {
        expect(resolve(ReceiverMethodReturnResolver::class)->resolve(ReceiverType::of($model), $method, receiverProbeScope())['type'] ?? null)
            ->toBe($type);
    })->with([
        [Post::class, 'attributesToArray', 'Record<string, unknown>'],
        [User::class, 'getAttributes', 'Record<string, unknown>'],
        [User::class, 'getDirty', 'Record<string, unknown>'],
        [User::class, 'getChanges', 'Record<string, unknown>'],
        [User::class, 'getPrevious', 'Record<string, unknown>'],
        [VagueDirtyOverrideModel::class, 'getDirty', null],
        [CallPathAbstractModel::class, 'getAttributes', null],
    ]);

    test('Model\'s own loosely declared arrays type by what they hold, where an override declines', function (string $model, string $method, ?string $type) {
        expect(resolve(ReceiverMethodReturnResolver::class)->resolve(ReceiverType::of($model), $method, receiverProbeScope())['type'] ?? null)
            ->toBe($type);
    })->with([
        [User::class, 'getAppends', 'string[]'],
        [User::class, 'getMutatedAttributes', 'string[]'],
        [User::class, 'getTouchedRelations', 'string[]'],
        [User::class, 'uniqueIds', 'string[]'],
        [User::class, 'getQueueableRelations', 'string[]'],
        [User::class, 'getCasts', 'Record<string, string>'],
        [User::class, 'getGlobalScopes', 'Record<string, unknown>'],
        [User::class, 'getRelations', 'Record<string, unknown>'],
        [User::class, 'relationsToArray', 'Record<string, unknown>'],
        [User::class, 'guessResourceName', 'string[]'],
        [User::class, 'qualifyColumns', 'string[]'],
        [User::class, 'resolveGlobalScopeAttributes', 'string[]'],
        [User::class, 'resolveObserveAttributes', 'string[]'],
        [User::class, 'getAllGlobalScopes', 'Record<string, Record<string, unknown>>'],
        [User::class, 'all', 'unknown[]'],
        [VagueDirtyOverrideModel::class, 'getCasts', null],
        [CallPathAbstractModel::class, 'getCasts', null],
    ]);

    test('a method the model does not have is never Model\'s own, so nothing reflects it', function () {
        $inherits = fn (string $class, string $method): bool => $this->inheritsModelMethod($class, $method);

        expect($inherits->call(resolve(ReceiverMethodReturnResolver::class), User::class, 'notAModelMethod'))->toBeFalse()
            ->and($inherits->call(resolve(ReceiverMethodReturnResolver::class), User::class, 'getCasts'))->toBeTrue();
    });

    test('an overridden toArray() publishes its declared shape through $this->resource and on a controller\'s property', function () {
        $resource = new ResourceAstAnalyzer(new ReflectionClass(ReceiverProbeResource::class), ReceiverShapedToArrayModel::class);
        $controller = new ResourceAstAnalyzer(new ReflectionClass(PostReceiverController::class), null, '__construct', ControllerExpressionHandlers::make());

        expect($resource->resolve(receiverHandlerExpr('$this->resource->toArray()'))['type'])->toBe('{ id: number }')
            ->and($controller->resolve(receiverHandlerExpr('$this->shaped->toArray()'))['type'])->toBe('{ id: number }');
    });
});

describe('ReceiverMethodCallHandler', function () {
    test('leaves a request receiver to the request rule', function () {
        $scope = receiverProbeScope();
        $scope->requestVarNames['request'] = Request::class;

        expect(new ReceiverMethodCallHandler()->resolve(receiverHandlerExpr('$request->ip()'), $scope, chainHandlersThrowingEngine()))
            ->toBeNull();
    });

    test('forwards a bare $this->method() only on a resource, and only for a method the resource does not declare', function () {
        $resource = new AnalysisScope(new ReflectionClass(ReceiverProbeResource::class), Post::class);
        $plain = new AnalysisScope(new ReflectionClass(ReceiverVarProbe::class), Post::class);
        $handler = new ReceiverMethodCallHandler;

        expect($handler->resolve(receiverHandlerExpr('$this->getKey()'), $resource, chainHandlersThrowingEngine())['type'] ?? null)
            ->toBe('number')
            ->and($handler->resolve(receiverHandlerExpr('$this->urls()'), $resource, chainHandlersThrowingEngine()))->toBeNull()
            ->and($handler->resolve(receiverHandlerExpr('$this->getKey()'), $plain, chainHandlersThrowingEngine()))->toBeNull();
    });

    test('reaches a protected static method only through static::', function () {
        $scope = receiverProbeScope();
        $scope->localVarBindings['probe'] = receiverHandlerExpr('new '.ReceiverMethodProbe::class);
        $handler = new ReceiverMethodCallHandler;

        expect($handler->resolve(receiverHandlerExpr('static::secret()'), $scope, chainHandlersThrowingEngine())['type'] ?? null)
            ->toBe('number')
            ->and($handler->resolve(receiverHandlerExpr('$probe::secret()'), $scope, chainHandlersThrowingEngine()))->toBeNull();
    });

    // Laravel's only()/except() return an attribute-keyed array whatever keys arrive at runtime. In a resource this
    // rule answers the own-model spellings; RelationFilterHandler answers a relation first, from the same helper.
    test('types a filter with no literal key list on a lone model as Record<string, unknown>', function (string $php, string $type) {
        $scope = new AnalysisScope(new ReflectionClass(ReceiverMethodResource::class), Post::class);

        expect(new ReceiverMethodCallHandler()->resolve(receiverHandlerExpr($php), $scope, chainHandlersThrowingEngine()))
            ->toBe(['type' => $type, 'optional' => false]);
    })->with([
        ['$this->resource->only($fields)', 'Record<string, unknown>'],
        ['$this->resource->author->only($fields)', 'Record<string, unknown>'],
        ['$this->author->except($fields)', 'Record<string, unknown>'],
        ['$this->resource->author?->only($fields)', 'Record<string, unknown> | null'],
    ]);

    // In a model's own method or accessor body `$this` is the model, and the chain handler declines its filters.
    // A method body reached by the body fallback carries no import, so a literal list there names no token: a key whose
    // type names one, such as an enum column, is `unknown`, and the rest of the shape keeps its types.
    test('types a filter on a model subject\'s own $this, naming no token where the scope carries no import', function () {
        $getter = new AnalysisScope(new ReflectionClass(Release::class), Release::class);
        $method = new AnalysisScope(new ReflectionClass(Post::class), Post::class);
        $method->carriesImports = false;
        $plain = new AnalysisScope(new ReflectionClass(ReceiverVarProbe::class), Post::class);
        $handler = new ReceiverMethodCallHandler;
        $resolve = fn (string $php, AnalysisScope $scope): ?array => $handler->resolve(receiverHandlerExpr($php), $scope, chainHandlersThrowingEngine());
        $record = ['type' => 'Record<string, unknown>', 'optional' => false];

        expect($resolve('$this->only($keys)', $getter))->toBe($record)
            ->and($resolve('$this->except($this->keys)', $getter))->toBe($record)
            ->and($resolve('$this?->except($keys)', $getter))->toBe($record)
            ->and($resolve("\$this->only(['major'])", $getter))->toBe(['type' => "Pick<Release, 'major'>", 'optional' => false, 'modelFqcn' => Release::class])
            ->and($resolve("\$this->only(['id', 'title'])", $method))->toBe([
                'type' => '{ id: number; title: string }', 'optional' => false, 'embeddedEnumFqcns' => [], 'embeddedModelFqcns' => [], 'customImports' => [],
            ])
            ->and($resolve("\$this?->only(['id', 'status'])", $method))->toBe([
                'type' => '{ id: number; status: unknown }', 'optional' => false, 'embeddedEnumFqcns' => [], 'embeddedModelFqcns' => [], 'customImports' => [],
            ])
            ->and($resolve('$this->except($keys)', $method))->toBe($record)
            ->and($resolve('$this->getKey()', $getter))->toBeNull()
            ->and($resolve('$this->only($keys)', $plain))->toBeNull();
    });

    // Model::only()/except() are what the filter rules describe; an override declares its own return, which PHP holds
    // every subclass to, so reflection answers it, as it answers a getKey() override.
    test('keeps a model\'s own only()/except() override return, in every scope', function (string $subject, ?array $profile, bool $carriesImports, string $php, string $type) {
        $engine = new ResourceAstAnalyzer(new ReflectionClass($subject), FilterOverrideModel::class, 'toArray', $profile, carriesImports: $carriesImports);

        expect($engine->resolve(receiverHandlerExpr($php))['type'])->toBe($type);
    })->with([
        'resource, own model' => [CommentResource::class, null, true, "\$this->only(['id'])", 'string'],
        'resource, $this->resource' => [CommentResource::class, null, true, '$this->resource->except($keys)', 'number'],
        'resource, relation' => [CommentResource::class, null, true, "\$this->twin->only(['id'])", 'string'],
        'resource, relation ?->' => [CommentResource::class, null, true, '$this->resource->twin?->except($keys)', 'number | null'],
        'method body' => [FilterOverrideModel::class, null, false, "\$this->only(['id', 'title'])", 'string'],
        'method body, relation' => [FilterOverrideModel::class, null, false, '$this->twin->except($keys)', 'number'],
        'getter body' => [FilterOverrideModel::class, ResourceExpressionHandlers::forModelClosures(), true, '$this?->except([\'id\'])', 'number'],
        'getter body, relation' => [FilterOverrideModel::class, ResourceExpressionHandlers::forModelClosures(), true, '$this->twin->only($keys)', 'string'],
    ]);

    // An override that declares no return leaves reflection nothing to publish, so it keeps Model's filter answer.
    test('keeps the filter answer for an only()/except() override that declares no return, in every scope', function (string $subject, ?array $profile, bool $carriesImports, string $php, string $type) {
        $engine = new ResourceAstAnalyzer(new ReflectionClass($subject), UntypedFilterOverrideModel::class, 'toArray', $profile, carriesImports: $carriesImports);

        expect($engine->resolve(receiverHandlerExpr($php))['type'])->toBe($type);
    })->with([
        'resource, own model' => [CommentResource::class, null, true, "\$this->only(['id', 'title'])", "Pick<UntypedFilterOverrideModel, 'id' | 'title'>"],
        'resource, $this->resource' => [CommentResource::class, null, true, '$this->resource->except($keys)', 'Record<string, unknown>'],
        'resource, relation' => [CommentResource::class, null, true, "\$this->twin->only(['id', 'title'])", "Pick<UntypedFilterOverrideModel, 'id' | 'title'>"],
        'resource, relation ?->' => [CommentResource::class, null, true, '$this->resource->twin?->except($keys)', 'Record<string, unknown> | null'],
        'resource, map proxy' => [CommentResource::class, null, true, "\$this->twins->map->only(['id', 'title'])", '{ id: number; title: string }[]'],
        'resource, multi-model accessor' => [CommentResource::class, null, true, "\$this->counterpart->only(['id'])", "Pick<UntypedFilterOverrideModel, 'id'> | Pick<User, 'id'>"],
        'method body' => [UntypedFilterOverrideModel::class, null, false, "\$this->only(['id', 'title'])", '{ id: number; title: string }'],
        'method body, relation' => [UntypedFilterOverrideModel::class, null, false, "\$this->twin?->only(['id', 'title'])", '{ id: number; title: string } | null'],
        'getter body' => [UntypedFilterOverrideModel::class, ResourceExpressionHandlers::forModelClosures(), true, "\$this?->only(['id', 'title'])", "Pick<UntypedFilterOverrideModel, 'id' | 'title'>"],
        'getter body, relation' => [UntypedFilterOverrideModel::class, ResourceExpressionHandlers::forModelClosures(), true, '$this->twin->only($keys)', 'Record<string, unknown>'],
    ]);

    // A declared return that publishes nothing more than `unknown[]`, `unknown` or `Record<string, unknown>` is one
    // reflection cannot type, so the override keeps Model's filter answer exactly as an undeclared one does.
    test('keeps the filter answer for an only()/except() override whose declared return is too vague to publish', function (string $model, string $subject, ?array $profile, bool $carriesImports, string $php, string $type) {
        $engine = new ResourceAstAnalyzer(new ReflectionClass($subject), $model, 'toArray', $profile, carriesImports: $carriesImports);

        expect($engine->resolve(receiverHandlerExpr($php))['type'])->toBe($type);
    })->with([
        ': array, resource' => [VagueFilterOverrideModel::class, CommentResource::class, null, true, "\$this->only(['id', 'title'])", "Pick<VagueFilterOverrideModel, 'id' | 'title'>"],
        ': array, resource relation' => [VagueFilterOverrideModel::class, CommentResource::class, null, true, "\$this->resource->twin->only(['id'])", "Pick<VagueFilterOverrideModel, 'id'>"],
        ': array, method body' => [VagueFilterOverrideModel::class, VagueFilterOverrideModel::class, null, false, "\$this->only(['id', 'title'])", '{ id: number; title: string }'],
        ': array, getter body' => [VagueFilterOverrideModel::class, VagueFilterOverrideModel::class, ResourceExpressionHandlers::forModelClosures(), true, "\$this->twin?->only(['id'])", "Pick<VagueFilterOverrideModel, 'id'> | null"],
        ': mixed, resource' => [VagueFilterOverrideModel::class, CommentResource::class, null, true, '$this->except($keys)', 'Record<string, unknown>'],
        ': mixed, resource relation' => [VagueFilterOverrideModel::class, CommentResource::class, null, true, '$this->twin->except($keys)', 'Record<string, unknown>'],
        'docblock array<string, mixed>, resource' => [DocblockFilterOverrideModel::class, CommentResource::class, null, true, "\$this->only(['id', 'title'])", "Pick<DocblockFilterOverrideModel, 'id' | 'title'>"],
        'docblock array<string, mixed>, getter body' => [DocblockFilterOverrideModel::class, DocblockFilterOverrideModel::class, ResourceExpressionHandlers::forModelClosures(), true, "\$this->only(['id'])", "Pick<DocblockFilterOverrideModel, 'id'>"],
    ]);

    // The body fallback carries no FQCN channel and drops a shape naming a token. Where the scope imports nothing, a
    // model the override returns is spelled as the object it serializes to, narrowed to the call's literal keys, and an
    // override returning any other token, such as an enum, publishes unknown; the enclosing shape survives either way.
    test('publishes a class-typed only()/except() override with no token where the scope carries no import', function (?array $profile, bool $carriesImports, string $php, string $type) {
        $engine = new ResourceAstAnalyzer(new ReflectionClass(ClassTypedFilterOverrideModel::class), ClassTypedFilterOverrideModel::class, 'toArray', $profile, carriesImports: $carriesImports);

        expect($engine->resolve(receiverHandlerExpr($php))['type'])->toBe($type);
    })->with([
        'method body, a model return' => [null, false, "\$this->only(['id'])", '{ id: number } | null'],
        'method body, relation ?->' => [null, false, '$this->twin?->only($keys)', 'Record<string, unknown> | null'],
        'method body, an enum return' => [null, false, "\$this->except(['id'])", 'unknown'],
        'method body, relation ?->, an enum return' => [null, false, "\$this->twin?->except(['id'])", 'unknown'],
        'method body, map proxy' => [null, false, "\$this->twins->map->only(['id', 'name'])", '({ id: number; name: string } | null)[]'],
        'method body, map proxy, an enum return' => [null, false, "\$this->twins->map->except(['id'])", 'unknown[]'],
        'method body, multi-model accessor' => [null, false, "\$this->counterpart->only(['id'])", '{ id: number } | { id: number } | null'],
        'method body, multi-model accessor, runtime keys' => [null, false, '$this->counterpart?->only($keys)', 'Record<string, unknown> | null'],
        'method body, multi-model accessor, an enum return' => [null, false, "\$this->counterpart->except(['id'])", 'unknown'],
        'getter body, a model return' => [ResourceExpressionHandlers::forModelClosures(), true, "\$this->only(['id'])", 'ClassTypedFilterOverrideModel | null'],
        'getter body, an enum return' => [ResourceExpressionHandlers::forModelClosures(), true, '$this->twin->except($keys)', 'PriorityType'],
        'getter body, map proxy' => [ResourceExpressionHandlers::forModelClosures(), true, "\$this->twins->map->except(['id'])", 'PriorityType[]'],
        'getter body, multi-model accessor' => [ResourceExpressionHandlers::forModelClosures(), true, "\$this->counterpart->only(['id'])", "ClassTypedFilterOverrideModel | Pick<User, 'id'> | null"],
    ]);

    test('keeps a method body\'s shape around a class-typed only()/except() override', function () {
        expect(resolve(MethodReturnTypeResolver::class)->resolve(ClassTypedFilterOverrideModel::class, 'filterFields'))->toBe([
            'type' => '{ own: { id: number; name: string } | null; twin: { id: number } | null; rest: unknown; id: number }',
            'optional' => false,
        ]);
    });

    // A returned model reaches JSON through toArray(): its columns and appended accessors, kept to `$visible` when that
    // lists any, less `$hidden`, whatever `exclude_hidden` says. The literal keys select from those names, a member
    // naming a token is `unknown`, and a runtime key list, or keys selecting nothing, leave a record.
    test('spells a model an override returns as the attributes it serializes, narrowed to the literal keys, where the scope carries no import', function (string $model, string $php, string $type) {
        $engine = new ResourceAstAnalyzer(new ReflectionClass($model), $model, 'toArray', carriesImports: false);

        expect($engine->resolve(receiverHandlerExpr($php))['type'])->toBe($type);
    })->with([
        'hidden columns, an enum cast and a class cast' => [HiddenFilterOverrideModel::class, "\$this->only(['id', 'name', 'password', 'remember_token', 'role', 'options'])", '{ id: number; name: string; role: unknown; options: unknown }'],
        'a map proxy, a key naming no column and a repeated one' => [HiddenFilterOverrideModel::class, "\$this->twins->map->only(['id', 'role', 'nope', 'id'])", '{ id: number; role: unknown }[]'],
        'a runtime key list' => [HiddenFilterOverrideModel::class, '$this->twins->map->except($keys)', 'Record<string, unknown>[]'],
        'keys selecting only hidden columns' => [HiddenFilterOverrideModel::class, "\$this->only(['password'])", 'Record<string, unknown>'],
        'visible columns less a hidden one' => [VisibleFilterOverrideModel::class, "\$this->only(['id', 'slug', 'color'])", '{ id: number } | null'],
        'another model, its own columns' => [UserFilterOverrideModel::class, "\$this->twins->map->only(['id', 'title', 'password', 'role'])", '{ id: number; role: unknown }[]'],
        'appended accessors, one of them an enum' => [HiddenFilterOverrideModel::class, "\$this->only(['id', 'badge', 'rank'])", '{ id: number; badge: string; rank: unknown }'],
        'an appended accessor alone, through a map proxy' => [HiddenFilterOverrideModel::class, "\$this->twins->map->only(['badge'])", '{ badge: string }[]'],
        'an appended accessor through ?->' => [HiddenFilterOverrideModel::class, "\$this->twin?->only(['id', 'badge'])", '{ id: number; badge: string } | null'],
        'an appended accessor on a multi-model accessor arm' => [HiddenFilterOverrideModel::class, "\$this->counterpart->only(['id', 'badge'])", '{ id: number; badge: string } | { id: number }'],
        'another model, its own appended accessor' => [AppendingModelFilterOverrideModel::class, "\$this->twins->map->only(['id', 'title', 'badge'])", '{ id: number; badge: string }[]'],
        'a hidden appended accessor, and one not appended' => [HiddenFilterOverrideModel::class, "\$this->only(['id', 'secret', 'nick'])", '{ id: number }'],
        'a hidden appended accessor alone' => [HiddenFilterOverrideModel::class, "\$this->twin?->only(['secret'])", 'Record<string, unknown> | null'],
        'a visible appended accessor, and one outside $visible' => [VisibleFilterOverrideModel::class, "\$this->only(['id', 'label', 'shade'])", '{ id: number; label: string } | null'],
        'the complement of the visible names, an append named like a column once' => [VisibleFilterOverrideModel::class, "\$this->twin?->except(['id'])", '{ name: string; label: string } | null'],
        'a complement selecting nothing' => [VisibleFilterOverrideModel::class, "\$this->except(['id', 'name', 'label'])", 'Record<string, unknown> | null'],
    ]);

    // Only a top-level arm that is a model, or a list of one, is spelled from its columns: a model nested in a shape
    // key is a leaf the reflected docblock already spells `unknown`.
    test('spells a list of models an override returns as a list of their columns where the scope carries no import', function (string $model, string $php, string $type) {
        $engine = new ResourceAstAnalyzer(new ReflectionClass($model), $model, 'toArray', carriesImports: false);

        expect($engine->resolve(receiverHandlerExpr($php))['type'])->toBe($type);
    })->with([
        'a list of token-free models' => [ListTypedFilterOverrideModel::class, "\$this->only(['id', 'slug'])", '{ id: number; slug: string }[]'],
        'a model nested in a shape key' => [ListTypedFilterOverrideModel::class, "\$this->except(['id'])", '{ owner: unknown; id: number }'],
        'a list of models whose columns name an enum' => [UserListFilterOverrideModel::class, "\$this->only(['id', 'role', 'password'])", '{ id: number; role: unknown }[]'],
    ]);

    // Support\Collection::only()/except() keep the entries whose keys are listed, so the value is a keyed map
    // whether the collection comes from a cast column, an accessor or a method, and whatever its elements are.
    test('types a filter on a Support\Collection member as Record<string, unknown>, in every scope', function (string $subject, ?array $profile, bool $carriesImports, string $php, string $type) {
        $engine = new ResourceAstAnalyzer(new ReflectionClass($subject), CollectionMemberModel::class, 'toArray', $profile, carriesImports: $carriesImports);

        expect($engine->resolve(receiverHandlerExpr($php))['type'])->toBe($type);
    })->with([
        'resource, cast column' => [CommentResource::class, null, true, "\$this->options->only(['a', 'b'])", 'Record<string, unknown>'],
        'resource, cast column through $this->resource' => [CommentResource::class, null, true, '$this->resource->options->except($keys)', 'Record<string, unknown>'],
        'resource, AsEncryptedCollection column' => [CommentResource::class, null, true, "\$this->visibility->only(['a'])", 'Record<string, unknown>'],
        'resource, collection column' => [CommentResource::class, null, true, "\$this->content?->only(['a'])", 'Record<string, unknown> | null'],
        'resource, encrypted:collection column through $this->resource' => [CommentResource::class, null, true, '$this->resource->featured_image_url->except($keys)', 'Record<string, unknown>'],
        'resource, accessor of models' => [CommentResource::class, null, true, "\$this->people->only(['id'])", 'Record<string, unknown>'],
        'resource, accessor of models ?-> through $this->resource' => [CommentResource::class, null, true, "\$this->resource->people?->except(['id'])", 'Record<string, unknown> | null'],
        'method body, collection column' => [CollectionMemberModel::class, null, false, "\$this->content->only(['a'])", 'Record<string, unknown>'],
        'method body, accessor of models' => [CollectionMemberModel::class, null, false, "\$this->people->only(['id'])", 'Record<string, unknown>'],
        'getter body, encrypted:collection column' => [CollectionMemberModel::class, ResourceExpressionHandlers::forModelClosures(), true, "\$this->featured_image_url->only(['a'])", 'Record<string, unknown>'],
        'getter body, accessor of models' => [CollectionMemberModel::class, ResourceExpressionHandlers::forModelClosures(), true, "\$this->people->except(['id'])", 'Record<string, unknown>'],
        'resource, accessor' => [CommentResource::class, null, true, "\$this->stats->except(['a'])", 'Record<string, unknown>'],
        'resource, method' => [CommentResource::class, null, true, '$this->tally()->only($keys)', 'Record<string, unknown>'],
        'resource, method ?-> through $this->resource' => [CommentResource::class, null, true, "\$this->resource->tally()?->only(['a'])", 'Record<string, unknown> | null'],
        'resource, a cast building an Eloquent collection' => [CommentResource::class, null, true, "\$this->metadata->only(['a'])", 'unknown'],
        'method body, cast column ?->' => [CollectionMemberModel::class, null, false, "\$this->options?->except(['a'])", 'Record<string, unknown> | null'],
        'method body, accessor' => [CollectionMemberModel::class, null, false, '$this->stats->only($keys)', 'Record<string, unknown>'],
        'method body, method' => [CollectionMemberModel::class, null, false, "\$this->tally()->except(['a'])", 'Record<string, unknown>'],
        'getter body, cast column' => [CollectionMemberModel::class, ResourceExpressionHandlers::forModelClosures(), true, '$this->options->except($keys)', 'Record<string, unknown>'],
        'getter body, accessor' => [CollectionMemberModel::class, ResourceExpressionHandlers::forModelClosures(), true, "\$this->stats->only(['a', 'b'])", 'Record<string, unknown>'],
        'getter body, method' => [CollectionMemberModel::class, ResourceExpressionHandlers::forModelClosures(), true, '$this->tally()->only($keys)', 'Record<string, unknown>'],
        'getter body, a to-many relation' => [CollectionMemberModel::class, ResourceExpressionHandlers::forModelClosures(), true, '$this->comments->only([1])', 'Comment[]'],
    ]);

    // Eloquent\Collection::only()/except() keep whole models by primary key and re-index them, so an accessor holding
    // one publishes a list of its models, as a many-relation does, whatever key type it declares. A cast building one
    // holds decoded JSON, whose elements have no key to filter by.
    test('types a filter on an accessor holding an Eloquent\Collection as a list of its models, in every scope', function (string $subject, ?array $profile, bool $carriesImports, string $php, string $type) {
        $engine = new ResourceAstAnalyzer(new ReflectionClass($subject), CollectionMemberModel::class, 'toArray', $profile, carriesImports: $carriesImports);

        expect($engine->resolve(receiverHandlerExpr($php))['type'])->toBe($type);
    })->with([
        'resource' => [CommentResource::class, null, true, '$this->kids->only([1])', 'Comment[]'],
        'resource ?-> through $this->resource' => [CommentResource::class, null, true, "\$this->resource->kids?->except(['id'])", 'Comment[] | null'],
        'resource, no element model' => [CommentResource::class, null, true, '$this->strays->only($keys)', 'unknown[]'],
        'method body' => [CollectionMemberModel::class, null, false, "\$this->kids->only(['id'])", 'unknown[]'],
        'getter body' => [CollectionMemberModel::class, ResourceExpressionHandlers::forModelClosures(), true, '$this->kids->except($keys)', 'Comment[]'],
        'getter body, no element model ?->' => [CollectionMemberModel::class, ResourceExpressionHandlers::forModelClosures(), true, '$this->strays?->only([1])', 'unknown[] | null'],
        'getter body, a cast building one' => [CollectionMemberModel::class, ResourceExpressionHandlers::forModelClosures(), true, "\$this->metadata->only(['a'])", 'unknown'],
        'resource, keyed by string, nullable' => [CommentResource::class, null, true, '$this->keyed_kids->only([1])', 'Comment[] | null'],
        'resource, keyed by string, two models ?-> through $this->resource' => [CommentResource::class, null, true, "\$this->resource->mixed_kids?->except(['x'])", '(Comment | User)[] | null'],
        'getter body, keyed by string, two models' => [CollectionMemberModel::class, ResourceExpressionHandlers::forModelClosures(), true, '$this->mixed_kids->only($keys)', '(Comment | User)[]'],
        'method body, keyed by string, nullable' => [CollectionMemberModel::class, null, false, '$this->keyed_kids->except([1])', 'unknown[] | null'],
        'method body, keyed by string, two models' => [CollectionMemberModel::class, null, false, "\$this->mixed_kids->only(['x'])", 'unknown[]'],
    ]);

    test('types a Support\Collection member\'s filter through the real getter and method-body paths', function () {
        expect(resolve(ModelAttributeResolver::class)->resolveAttribute(CollectionMemberModel::class, 'option_picks')['type'])
            ->toBe('{ options: Record<string, unknown>; id: number }')
            ->and(resolve(MethodReturnTypeResolver::class)->resolve(CollectionMemberModel::class, 'optionFields'))
            ->toBe(['type' => '{ options: Record<string, unknown>; id: number }', 'optional' => false]);
    });

    // Model::only() keys a name it cannot find to null, so a literal list naming nothing typed still returns an
    // attribute-keyed array. RelationFilterHandler declines the relation spelling, and the receiver rules answer it.
    test('types a literal key list that names nothing as Record<string, unknown>, in every scope', function (string $subject, ?array $profile, bool $carriesImports, string $php, string $type) {
        $engine = new ResourceAstAnalyzer(new ReflectionClass($subject), Post::class, 'toArray', $profile, carriesImports: $carriesImports);

        expect($engine->resolve(receiverHandlerExpr($php))['type'])->toBe($type);
    })->with([
        'resource, own model' => [PostResource::class, null, true, "\$this->only(['nope'])", 'Record<string, unknown>'],
        'resource, relation' => [PostResource::class, null, true, "\$this->author->only(['nope'])", 'Record<string, unknown>'],
        'resource, relation ?-> through $this->resource' => [PostResource::class, null, true, "\$this->resource->author?->only(['nope'])", 'Record<string, unknown> | null'],
        'method body, relation' => [Post::class, null, false, "\$this->author->only(['nope'])", 'Record<string, unknown>'],
        'getter body, relation' => [Post::class, ResourceExpressionHandlers::forModelClosures(), true, "\$this->author->only(['nope'])", 'Record<string, unknown>'],
    ]);

    test('an earlier ?-> in the chain makes the call nullable once', function () {
        $scope = new AnalysisScope(new ReflectionClass(ReceiverMethodResource::class), Post::class);
        $handler = new ReceiverMethodCallHandler;

        expect($handler->resolve(receiverHandlerExpr('$this->resource?->author->getMorphClass()'), $scope, chainHandlersThrowingEngine())['type'] ?? null)
            ->toBe('string | null')
            ->and($handler->resolve(receiverHandlerExpr('$this->resource?->author?->getMorphClass()'), $scope, chainHandlersThrowingEngine())['type'] ?? null)
            ->toBe('string | null');
    });
});

describe('a nullsafe method on a chain reads each step as one model, through the engine', function () {
    test('a nullsafe method types from the one model each step holds, and a step holding none leaves it unknown', function (string $subject, string $model, string $php, string $type) {
        expect(new ResourceAstAnalyzer(new ReflectionClass($subject), $model)->resolve(receiverHandlerExpr($php))['type'])->toBe($type);
    })->with([
        'a to-one relation' => [ClosureResourceRootResource::class, Post::class, '$this->author?->nameTitled()', 'string | null'],
        'an unresolved morphTo' => [ImageResource::class, Image::class, '$this->imageable?->getTable()', 'string | null'],
        'an attribute, not a relation' => [ReceiverMethodResource::class, Post::class, '$this->priority?->label()', 'string | null'],
        'a dynamic-key except() on a to-one relation' => [CommentResource::class, Comment::class, '$this->post?->except($fields)', 'Record<string, unknown> | null'],
        'a nested dynamic-key only() on a relation' => [CommentResource::class, Comment::class, '$this->resource->post?->only($ids)', 'Record<string, unknown> | null'],
        'a to-many only()' => [CommentResource::class, Comment::class, '$this->replies?->only($ids)', 'Comment[] | null'],
        'a to-many middle step' => [ClosureResourceRootResource::class, Post::class, '$this->comments?->user?->nameTitled()', 'unknown'],
        'a column shadowing the relation' => [ProxyDepotResource::class, Depot::class, '$this->supervisor?->nameTitled()', 'unknown'],
        'an accessor shadowing the relation' => [ClosureResourceRootResource::class, ProxyShadowPost::class, '$this->author?->nameTitled()', 'unknown'],
    ]);

    test('inside a whenLoaded closure the chain still roots at the resource model', function () {
        $props = collect(new ResourceAstAnalyzer(new ReflectionClass(ClosureResourceRootResource::class), Post::class)->analyze()->properties)
            ->mapWithKeys(fn (array $p): array => [$p['name'] => $p['type']]);

        expect($props['author_titled_inside'])->toBe('string | null')
            ->and($props['profile_bio_inside'])->toBe('string | null');
    });

    // RosterSlot has both methods too, so answering from the resource's own model would type the Model-bound pair.
    test('types a method on a morphTo from the targets its generic names, not from the resource model', function () {
        $props = collect(resolve(AstEngine::class)->analyze(RosterSlotResource::class)->properties)
            ->mapWithKeys(fn (array $p): array => [$p['name'] => $p['type']]);

        expect($props->all())->toBe([
            'id' => 'number',
            'assignable_label' => 'unknown',
            'assignable_title' => 'unknown',
            'assignee_label' => 'string | null',
            'assignee_title' => 'string | null',
        ]);
    });

    test('leaves every spelling of a call on a morphTo that names no targets unknown, and types the narrowed one', function () {
        $props = collect(resolve(AstEngine::class)->analyze(MorphSpellingsResource::class)->properties)
            ->mapWithKeys(fn (array $p): array => [$p['name'] => $p['type']]);

        expect($props->all())->toBe([
            'proxy_title' => 'unknown',
            'proxy_title_nullsafe' => 'unknown',
            'resource_title' => 'unknown',
            'resource_title_nullsafe' => 'unknown',
            'static_label' => 'unknown',
            'assignee_title' => 'string',
            'assignee_title_nullsafe' => 'string | null',
        ]);
    });
});

describe('ReceiverPropertyFetchHandler', function () {
    test('types property chains from a local variable holding a model', function () {
        expect(receiverPropertyTypes())->toMatchArray([
            'post_title' => 'string | null',
            'post_published_at' => 'string | null',
            'post_author_name' => 'string | null',
            'post_title_direct' => 'string',
            'post_title_via_this' => 'string | null',
            'post_title_via_resource' => 'string | null',
        ]);
    });

    test('a chain read through $this->resource publishes the type of its $this twin', function () {
        $props = receiverPropertyTypes();

        expect($props['resource_post_title'])->toBe($props['post_title'])
            ->and($props['resource_post_published_at'])->toBe($props['post_published_at'])
            ->and($props['resource_post_author_name'])->toBe($props['post_author_name'])
            ->and($props['resource_post_title_direct'])->toBe($props['post_title_direct'])
            ->and($props['post_title_via_resource'])->toBe($props['post_title_via_this']);
    });

    test('declines a $this->prop leaf, which belongs to ThisPropertyHandler', function () {
        expect(new ReceiverPropertyFetchHandler()->resolve(receiverHandlerExpr('$this->title'), receiverProbeScope(), chainHandlersThrowingEngine()))
            ->toBeNull();
    });

    test('a reflected property json_encode() writes as an object publishes the object, one it writes as a string types', function () {
        $handler = new ReceiverPropertyFetchHandler;
        $scope = receiverProbeScope();
        $scope->localVarBindings['probe'] = receiverHandlerExpr('new '.ReceiverVarProbe::class);
        $date = LaravelTsPublishService::DATE_TIME_OBJECT_TYPE;

        expect(LaravelTsPublish::propertyTypes(new ReflectionClass(ReceiverVarProbe::class), 'plainDate')['type'])->toBe($date)
            ->and($handler->resolve(receiverHandlerExpr('$probe->plainDate'), $scope, chainHandlersThrowingEngine())['type'] ?? null)->toBe($date)
            ->and($handler->resolve(receiverHandlerExpr('$probe->text'), $scope, chainHandlersThrowingEngine())['type'] ?? null)->toBe('string');
    });

    test('a union receiver types each arm as json_encode() writes it: one an object, one a string', function () {
        $handler = new ReceiverPropertyFetchHandler;
        $scope = receiverProbeScope();
        $union = '($flag ? new '.ReceiverVarProbe::class.' : new '.ReceiverStringDateProbe::class.')->plainDate';

        expect(LaravelTsPublish::propertyTypes(new ReflectionClass(ReceiverStringDateProbe::class), 'plainDate')['type'])->toBe('string')
            ->and($handler->resolve(receiverHandlerExpr('(new '.ReceiverStringDateProbe::class.')->plainDate'), $scope, chainHandlersThrowingEngine())['type'] ?? null)
            ->toBe('string')
            ->and($handler->resolve(receiverHandlerExpr($union), $scope, chainHandlersThrowingEngine())['type'] ?? null)
            ->toBe(LaravelTsPublishService::DATE_TIME_OBJECT_TYPE.' | string');
    });

    test('a reflected property naming a model no file is published for declines', function () {
        $handler = new ReceiverPropertyFetchHandler;
        $scope = receiverProbeScope();
        $scope->localVarBindings['probe'] = receiverHandlerExpr('new '.ReceiverVarProbe::class);

        expect(LaravelTsPublish::propertyTypes(new ReflectionClass(ReceiverVarProbe::class), 'anyModel')['classFqcns'])->toBe([Model::class])
            ->and($handler->resolve(receiverHandlerExpr('$probe->anyModel'), $scope, chainHandlersThrowingEngine()))->toBeNull();
    });

    // A new Product holds no attributes, so its `id` reads null, as Laravel sends it, beside the author's key; a `?->`
    // adds no second `null`.
    test('a union receiver merges what each model arm types', function (string $php, string $type) {
        $scope = new AnalysisScope(new ReflectionClass(ReceiverMethodResource::class), Post::class);

        expect(new ReceiverPropertyFetchHandler()->resolve(receiverHandlerExpr($php), $scope, chainHandlersThrowingEngine())['type'] ?? null)
            ->toBe($type)
            ->and((new Product)->id)->toBeNull();
    })->with([
        ['($this->status ? new '.Product::class.' : $this->author)->id', 'string | number | null'],
        ['($this->status ? new '.Product::class.' : $this->author)?->id', 'string | number | null'],
    ]);

    // PHP sends an outside read of a non-public or static property to __get(), never to the declaration.
    test('only a public instance property types a read from outside the class', function () {
        $handler = new ReceiverPropertyFetchHandler;
        $scope = receiverProbeScope();
        $scope->localVarBindings['post'] = receiverHandlerExpr('new '.MagicPost::class);

        expect($handler->resolve(receiverHandlerExpr('$post->title'), $scope, chainHandlersThrowingEngine())['type'] ?? null)->toBe('string')
            ->and($handler->resolve(receiverHandlerExpr('$post->status'), $scope, chainHandlersThrowingEngine()))->toBeNull()
            ->and($handler->resolve(receiverHandlerExpr('$post->data'), $scope, chainHandlersThrowingEngine()))->toBeNull()
            ->and($handler->resolve(receiverHandlerExpr('$post->kind'), $scope, chainHandlersThrowingEngine()))->toBeNull()
            ->and($handler->resolve(receiverHandlerExpr('(new '.Fluent::class.')->attributes'), $scope, chainHandlersThrowingEngine()))->toBeNull();
    });
});

describe('a property the subject declares wins over the model', function () {
    test('a promoted resource property wins over the model and chains through its own class', function () {
        $props = collect(new ResourceAstAnalyzer(new ReflectionClass(PostStatsResource::class), Post::class)->analyze()->properties)
            ->mapWithKeys(fn (array $p): array => [$p['name'] => $p['type']]);

        expect($props->all())->toMatchArray([
            // Post::$title is a string column; the subject's own int declaration wins.
            'title' => 'number',
            'stats' => '{ views: number; shares: number } | null',
            'views' => 'number | null',
            'share_count' => 'number | null',
        ]);
    });

    // `resource` is declared by JsonResource, so it is never the subject's own; if that exclusion were
    // dropped, every `$this->resource->…` receiver in the package would stop resolving.
    test('$this->resource->prop still types, whether or not the subject redeclares $resource', function () {
        $inherited = new ResourceAstAnalyzer(new ReflectionClass(ReceiverProbeResource::class), Post::class);
        $redeclared = new ResourceAstAnalyzer(new ReflectionClass(ModelWrappedPropResource::class), Post::class);

        expect($inherited->resolve(receiverHandlerExpr('$this->resource->title'))['type'])->toBe('string')
            ->and($redeclared->resolve(receiverHandlerExpr('$this->resource->title'))['type'])->toBe('string');
    });
});

// The MethodCall/NullsafeMethodCall rows' inert claim: both handlers answer a relation's only() with the same Pick<>,
// so reordering the pair cannot change an answer.
describe('RelationFilterHandler and ReceiverMethodCallHandler are inert against each other', function () {
    test('both claim $this->relation->only([...]) and answer it the same', function (string $php, array $expected) {
        $expr = receiverHandlerExpr($php);
        $scope = fn (): AnalysisScope => new AnalysisScope(new ReflectionClass(CommentResource::class), Comment::class);

        $filter = new RelationFilterHandler()->resolve($expr, $scope(), chainHandlersThrowingEngine());
        $receiver = new ReceiverMethodCallHandler()->resolve($expr, $scope(), chainHandlersThrowingEngine());

        expect($filter)->toBe($expected)
            ->and($receiver)->toBe($filter);
    })->with([
        // The Pick<> branch, both spellings, carrying the modelFqcn channel.
        ['$this->post->only([\'id\', \'title\'])', ['type' => "Pick<Post, 'id' | 'title'>", 'optional' => false, 'modelFqcn' => Post::class]],
        ['$this->post?->only([\'id\', \'title\'])', ['type' => "Pick<Post, 'id' | 'title'> | null", 'optional' => false, 'modelFqcn' => Post::class]],
        // The same relation read through the $this->resource proxy.
        ['$this->resource->post->only([\'id\', \'title\'])', ['type' => "Pick<Post, 'id' | 'title'>", 'optional' => false, 'modelFqcn' => Post::class]],
        ['$this->resource->post?->only([\'id\', \'title\'])', ['type' => "Pick<Post, 'id' | 'title'> | null", 'optional' => false, 'modelFqcn' => Post::class]],
        // A runtime key list: both build Record<string, unknown> from ResolvesFilteredRelationTypes.
        ['$this->post->except($fields)', ['type' => 'Record<string, unknown>', 'optional' => false]],
        ['$this->resource->post?->only($fields)', ['type' => 'Record<string, unknown> | null', 'optional' => false]],
        // The inline branch: 'excerpt' is an accessor rather than a column, so relationFilterModelReference()
        // declines on both sides and each falls to resolveFilteredRelationType() — a different channel set.
        ['$this->post->only([\'id\', \'excerpt\'])', [
            'type' => '{ id: number; excerpt: string | null }',
            'optional' => false,
            'embeddedEnumFqcns' => [],
            'embeddedModelFqcns' => [],
            'customImports' => [],
        ]],
    ]);

    // An accessor typed Collection<int, User> names a model, but its filter is the collection's, so both answer
    // Record<string, unknown>. The receiver rules decline an Eloquent\Collection, which RelationFilterHandler types.
    test('agree on a filter on an accessor holding a collection of models', function (string $php, ?array $expected, ?array $receiverExpected) {
        $expr = receiverHandlerExpr($php);
        $scope = fn (): AnalysisScope => new AnalysisScope(new ReflectionClass(CommentResource::class), CollectionMemberModel::class);
        $engine = new ResourceAstAnalyzer(new ReflectionClass(CommentResource::class), CollectionMemberModel::class);

        expect(new RelationFilterHandler()->resolve($expr, $scope(), $engine))->toBe($expected)
            ->and(new ReceiverMethodCallHandler()->resolve($expr, $scope(), chainHandlersThrowingEngine()))->toBe($receiverExpected);
    })->with([
        'Support\Collection of models' => ["\$this->people->only(['id'])", ['type' => 'Record<string, unknown>', 'optional' => false], ['type' => 'Record<string, unknown>', 'optional' => false]],
        'Support\Collection of models ?->' => ["\$this->resource->people?->except(['id'])", ['type' => 'Record<string, unknown> | null', 'optional' => false], ['type' => 'Record<string, unknown> | null', 'optional' => false]],
        'Eloquent\Collection of models' => ['$this->kids->only([1])', ['type' => 'Comment[]', 'optional' => false, 'modelFqcn' => Comment::class], null],
    ]);
});

// The inert half of the ordering inventory's PropertyFetch row: both handlers really claim
// `$this->relation?->attr`, and they answer it identically, which is why their order is free.
// Swapping the two registrations changed no golden line; see docs/components/ast-engine.md.
describe('PropertyChainHandler and ReceiverPropertyFetchHandler are inert against each other', function () {
    test('both claim $this->relation?->attr and answer it the same', function () {
        $expr = receiverHandlerExpr('$this->post?->title');

        $chain = new PropertyChainHandler()->resolve($expr, receiverPropertyScope(), chainHandlersThrowingEngine());
        $receiver = new ReceiverPropertyFetchHandler()->resolve($expr, receiverPropertyScope(), chainHandlersThrowingEngine());

        expect($chain)->toBe(['type' => 'string | null', 'optional' => false])
            ->and($receiver)->toBe($chain);
    });

    // A camelCase key reaches the accessor the model lists under its snake_case name. An untyped getter's model comes
    // from its body, and a name only an `@property` tag types from the tag, since a dynamic or set relation fills it.
    test('both walk a step to the one model it holds', function (string $resource, string $model, string $php, string $type) {
        $expr = receiverHandlerExpr($php);
        $scope = fn (): AnalysisScope => new AnalysisScope(new ReflectionClass($resource), $model);

        $chain = new PropertyChainHandler()->resolve($expr, $scope(), chainHandlersThrowingEngine());
        $receiver = new ReceiverPropertyFetchHandler()->resolve($expr, $scope(), chainHandlersThrowingEngine());

        expect(new ResourceAstAnalyzer(new ReflectionClass($resource), $model)->resolve($expr)['type'])->toBe($type)
            ->and($chain)->toBe(['type' => $type, 'optional' => false])
            ->and($receiver)->toBe($chain);
    })->with([
        'a camelCase accessor' => [ModelWrappedPropResource::class, Post::class, '$this->latestComment->is_flagged', 'boolean'],
        'an untyped getter' => [AccessorNamedKeysResource::class, AuthoredPost::class, '$this->lead?->email', 'string | null'],
        'a name only an @property tag types, nullsafe' => [ClosureResourceRootResource::class, SingleModelStepPost::class, '$this->ghost?->name', 'string | null'],
        'a name only an @property tag types' => [ClosureResourceRootResource::class, SingleModelStepPost::class, '$this->ghost->name', 'string'],
    ]);
});

describe('a read on $this->prop types from the class the property holds', function () {
    test('a column the held model does not have is unknown, where a column it has still types', function (string $php, string $type) {
        $analyzer = new ResourceAstAnalyzer(new ReflectionClass(ModelWrappedPropResource::class), Post::class);

        expect($analyzer->resolve(receiverHandlerExpr($php))['type'])->toBe($type);
    })->with([
        'a held model without the column' => ['$this->author->title', 'unknown'],
        'a held model with the column' => ['$this->author->name', 'string'],
        'the resource model\'s own column' => ['$this->resource->title', 'string'],
    ]);

    test('an accessor typed as one model is followed, and a to-many relation is not, in every spelling', function (string $php, string $type) {
        $analyzer = new ResourceAstAnalyzer(new ReflectionClass(ModelWrappedPropResource::class), Post::class);

        expect($analyzer->resolve(receiverHandlerExpr($php))['type'])->toBe($type);
    })->with([
        'an accessor column' => ['$this->latestComment->content', 'string'],
        'an accessor key' => ['$this->latestComment->id', 'number'],
        'an accessor flag' => ['$this->resource->latestComment->is_flagged', 'boolean'],
        'a nullsafe accessor date' => ['$this->latestComment?->created_at', 'string | null'],
        'through the resource' => ['$this->resource->latestComment->id', 'number'],
        'inside whenLoaded' => ['$this->whenLoaded("author", fn () => $this->latestComment->id)', 'number'],
        'a morph-many column' => ['$this->tags->slug', 'unknown'],
        'a has-many key' => ['$this->comments->id', 'unknown'],
        'a to-many nullsafe' => ['$this->comments?->id', 'unknown'],
        'a to-many inside whenLoaded' => ['$this->whenLoaded("author", fn () => $this->tags->slug)', 'unknown'],
        'a to-many nullsafe inside whenLoaded' => ['$this->whenLoaded("author", fn () => $this->tags?->slug)', 'unknown'],
    ]);

    // Model::__get() reads the `supervisor` column, a string, before the same-named relation, so each read throws.
    test('a column shadowing a relation names no class, in dispatch and in each receiver handler', function (string $php, string $handler) {
        $expr = receiverHandlerExpr($php);
        $scope = new AnalysisScope(new ReflectionClass(ProxyDepotResource::class), Depot::class);

        expect(new ResourceAstAnalyzer(new ReflectionClass(ProxyDepotResource::class), Depot::class)->resolve($expr)['type'])->toBe('unknown')
            ->and(new $handler()->resolve($expr, $scope, chainHandlersThrowingEngine()))->toBeNull();
    })->with([
        ['$this->supervisor?->name', ReceiverPropertyFetchHandler::class],
        ['$this->supervisor->name', ReceiverPropertyFetchHandler::class],
        ['$this->supervisor?->nameTitled()', ReceiverMethodCallHandler::class],
        ['$this->supervisor->nameTitled()', ReceiverMethodCallHandler::class],
    ]);

    test('a model-less resource reading a property it does not declare is unknown', function () {
        $analyzer = new ResourceAstAnalyzer(new ReflectionClass(MediaTypeResource::class));

        expect($analyzer->resolve(receiverHandlerExpr('$this->other->name'))['type'])->toBe('unknown');
    });

    test('inside a whenLoaded closure a read still types from the property, never from the closure relation', function (string $php, string $type) {
        $analyzer = new ResourceAstAnalyzer(new ReflectionClass(PostResource::class), Post::class);

        expect($analyzer->resolve(receiverHandlerExpr($php))['type'])->toBe($type);
    })->with([
        'a column the relation lacks' => ['$this->whenLoaded("author", fn () => $this->categoryRel->email)', 'unknown'],
        'the same read nullsafe' => ['$this->whenLoaded("author", fn () => $this->categoryRel?->email)', 'unknown'],
    ]);
});

describe('PropertyChainHandler declines an unknown-only chain', function () {
    test('a nullsafe chain rooted at a variable declines for the receiver handler', function () {
        expect(new PropertyChainHandler()->resolve(receiverHandlerExpr('$post?->title'), receiverPropertyScope(), chainHandlersThrowingEngine()))
            ->toBeNull();
    });

    test('a $this->prop->subProp chain it cannot type declines for the receiver handler', function () {
        expect(new PropertyChainHandler()->resolve(receiverHandlerExpr('$this->post->nonexistent_column'), receiverPropertyScope(), chainHandlersThrowingEngine()))
            ->toBeNull();
    });
});

/**
 * The classes of each guard binding collectInstanceofGuards() writes for one parsed method body.
 *
 * @return array<string, non-empty-list<class-string>>
 */
function narrowingBindings(string $body): array
{
    $scope = new AnalysisScope(new ReflectionClass(NarrowedParentResource::class), Attachment::class);
    $stmts = new AstParser()->parseSource('<?php '.$body);

    $host = new class
    {
        use CollectsInstanceofGuards;
        use CollectsLocalVarBindings;

        /** @param  array<Stmt>  $stmts */
        public function run(array $stmts, AnalysisScope $scope): void
        {
            $this->collectInstanceofGuards($stmts, $scope);
        }
    };

    $host->run($stmts, $scope);

    return array_map(fn (array $guard): array => $guard['classes'], $scope->varGuardBindings);
}

describe('narrowing', function () {
    test('an early-return instanceof guard narrows a closure-local variable', function () {
        $props = collect(new ResourceAstAnalyzer(new ReflectionClass(NarrowedParentResource::class), Attachment::class)->analyze()->properties)->keyBy('name');

        expect($props['parent']['type'])->toBe('{ title: string; class: string; morph: string } | null')
            ->and($props['parent']['optional'])->toBeTrue()
            ->and($props['record_title']['type'])->toBe('string | null');
    });

    test('a local assigned from an instanceof ternary keeps the narrowing of the arm the test proves', function () {
        config()->set('ts-publish.output_to_files', false);

        expect(resolve(ResourceGenerator::class, ['findable' => TeamSubscriberLocalResource::class])->content)->toContain(<<<'TS'
            export interface TeamSubscriberLocalResource
            {
                subscriber_name: string | null;
                subscriber_id: number | null;
                inline_name: string | null;
                negated_name: string | null;
                via_local_email: string | null;
                unproven_name: unknown;
                negated_true_arm_name: unknown;
            }
            TS);
    });

    test('a test naming a supertype, interface or sibling of what the subject holds never widens the arm it proves', function () {
        config()->set('ts-publish.output_to_files', false);

        expect(resolve(ResourceGenerator::class, ['findable' => NarrowedWiderTestResource::class])->content)
            ->toContain("import type { User } from '../../models';")
            ->toContain(<<<'TS'
                export interface NarrowedWiderTestResource
                {
                    negated_model_title: string | null;
                    negated_interface_title: string | null;
                    negated_interface_email: string | null;
                    negated_resource_title: string | null;
                    negated_model_author: User | null;
                    sibling_chain_title: string | null;
                    supertype_chain_title: string | null;
                    interface_chain_email: string | null;
                    negated_chain_email: string | null;
                    positive_model_title: string | null;
                }
                TS);
    });

    test('a $this->resource instanceof ternary narrows the model for its true arm', function () {
        $props = collect(new ResourceAstAnalyzer(new ReflectionClass(TeamSubscriberResource::class), Team::class)->analyze()->properties)->keyBy('name');

        expect($props['subscriber_name']['type'])->toBe('string | null');
    });

    test('an instanceof ternary on a $this->prop narrows the receiver a local variable binds through it', function (string $key, string $type) {
        resolve(ModelAttributeResolver::class)->buildMorphTargetMap([Image::class, Post::class, Product::class, User::class, CrmUser::class]);

        $props = collect(new ResourceAstAnalyzer(new ReflectionClass(NarrowedImageableResource::class), Image::class)->analyze()->properties)->keyBy('name');

        expect($props[$key]['type'])->toBe($type);
    })->with([
        'an || chain' => ['either_id', 'number | null'],
        'a single test' => ['single_id', 'number | null'],
        'the un-narrowed control' => ['open_id', 'number | string | null'],
        'a variable ternary over the narrowed binding' => ['either_title', 'string | null'],
    ]);

    test('a guard whose body reads the variable leaves it un-narrowed, while a clean guard still narrows after it', function () {
        $props = collect(new ResourceAstAnalyzer(new ReflectionClass(NarrowingGuardBodyResource::class), Post::class)->analyze()->properties)->keyBy('name');

        // dirty_label is read inside the branch proving $parent is NOT a Post; User has no `title`, so it drops.
        expect($props['dirty_label']['type'])->toBe('number')
            ->and($props['clean_label']['type'])->toBe('string');
    });

    test('a narrowed variable outranks both its model binding and the local-assignment fallback', function () {
        $scope = new AnalysisScope(new ReflectionClass(NarrowedParentResource::class), Attachment::class);
        $scope->varClassBindings['x'] = [Post::class];
        $scope->varModelBindings['x'] = User::class;
        $scope->localVarBindings['x'] = receiverHandlerExpr('$this->filename');

        expect(resolve(ReceiverClassResolver::class)->resolve(new Variable('x'), $scope)?->classes)->toBe([Post::class]);
    });

    test('a guard binds a variable exactly when no write to it sits after the guard tests it', function (string $body, array $bindings) {
        expect(narrowingBindings($body))->toBe($bindings);
    })->with([
        'no write at all' => ['if (! $a instanceof \Workbench\App\Models\Post) { return; }', ['a' => [Post::class]]],
        'two writes, both before the guard' => ['$a = 1; $a = 2; if (! $a instanceof \Workbench\App\Models\Post) { return; }', ['a' => [Post::class]]],
        'a write in the condition before the test' => ['if (($a = $this->author) === null || ! $a instanceof \Workbench\App\Models\Post) { return; }', ['a' => [Post::class]]],
        'a write in the guard\'s own exiting body' => ['$a = $this->author; if (! $a instanceof \Workbench\App\Models\Post) { $a = null; return; }', ['a' => [Post::class]]],
        'one write after the guard' => ['if (! $a instanceof \Workbench\App\Models\Post) { return; } $a = $this->author;', []],
        'a write before the guard and one after it' => ['$a = $this->author; if (! $a instanceof \Workbench\App\Models\Post) { return; } foreach ([] as $a) {}', []],
        'a write in the condition after the test' => ['if (! $a instanceof \Workbench\App\Models\Post || ! ($a = $this->author)) { return; }', []],
    ]);

    test('a guard narrows a variable never written or written only before it, and not one written after it', function () {
        $resource = new GuardWritePostResource(null);
        $props = collect(resolve(AstEngine::class)->analyze(GuardWritePostResource::class)->properties)
            ->mapWithKeys(fn (array $p): array => [$p['name'] => ($p['optional'] ? '?' : '').$p['type']]);

        // After the guard $source is reassigned, so the read holds 'draft' although the guard proved a score source.
        expect($props->all())->toBe(['zero' => '?number', 'before' => '?number', 'after' => '?string'])
            ->and(new ReflectionMethod($resource, 'writtenAfterGuard')->invoke($resource, new PostScoreSource))
            ->toBe(['after' => 'draft']);
    });

    test('a guard narrows a whenLoaded() closure parameter the closure never writes', function () {
        $props = collect(new ResourceAstAnalyzer(new ReflectionClass(ParamGuardAttachmentResource::class), Attachment::class)->analyze()->properties)
            ->keyBy('name');

        expect($props['guarded']['type'])->toBe('{ title: string } | null')
            ->and($props['unguarded']['type'])->toBe('{ title: unknown }');
    });

    test('a guard body that reads the guarded variable still binds, for the statements after the guard', function () {
        expect(narrowingBindings('$a = $this->author; if (! $a instanceof \Workbench\App\Models\Post) { return $a->title; }'))
            ->toBe(['a' => [Post::class]]);
    });

    test('an early-exit guard narrows only the statements after it, in a method body and in a closure body', function () {
        $props = collect(resolve(AstEngine::class)->analyze(GuardOrderResource::class)->properties)
            ->mapWithKeys(fn (array $p): array => [$p['name'] => $p['type']]);

        expect($props->all())->toBe([
            'early' => 'number | string',
            'late' => 'number',
            'deferred' => 'number | string | null',
        ]);
    });

    test('a guard body that reads nothing binds, including through an || chain and a throw exit', function () {
        expect(narrowingBindings('$a = $this->author; if (! $a instanceof \Workbench\App\Models\Post) { return null; }'))
            ->toBe(['a' => [Post::class]])
            ->and(narrowingBindings('$a = $this->author; if (! $a || ! $a instanceof \Workbench\App\Models\Post) { return null; }'))
            ->toBe(['a' => [Post::class]])
            ->and(narrowingBindings('$a = $this->author; if (! $a instanceof \Workbench\App\Models\Post) { throw new \RuntimeException(); }'))
            ->toBe(['a' => [Post::class]]);
    });

    test('a guard binds only negated tests on a variable, through every || operand and never through &&', function (string $body, array $bindings) {
        expect(narrowingBindings($body))->toBe($bindings);
    })->with([
        'a positive test whose body reads nothing' => ['$a = $this->author; if ($a instanceof \Workbench\App\Models\Post) { return null; }', []],
        'an && of negated tests' => ['$a = $this->author; $b = $this->author; if (! $a instanceof \Workbench\App\Models\Post && ! $b instanceof \Workbench\App\Models\User) { return null; }', []],
        'every negated || operand' => ['$a = $this->author; $b = $this->author; if (! $a instanceof \Workbench\App\Models\Post || ! $b instanceof \Workbench\App\Models\User) { return null; }', ['a' => [Post::class], 'b' => [User::class]]],
        'a property subject' => ['if (! $this->author instanceof \Workbench\App\Models\User) { return null; }', []],
    ]);

    test('an elseif, an else, or a positive instanceof test binds nothing', function () {
        expect(narrowingBindings('$a = $this->author; if (! $a instanceof \Workbench\App\Models\Post) { return null; } elseif ($a) { return null; }'))
            ->toBe([])
            ->and(narrowingBindings('$a = $this->author; if (! $a instanceof \Workbench\App\Models\Post) { return null; } else { return null; }'))
            ->toBe([])
            ->and(narrowingBindings('$a = $this->author; if ($a instanceof \Workbench\App\Models\Post) { return $a->title; }'))
            ->toBe([]);
    });
});

describe('getKey()', function () {
    test('publishes the key type an abstract model declares in every spelling, where it once guessed string', function (string $php, string $type) {
        $analyzer = new ResourceAstAnalyzer(new ReflectionClass(ReceiverProbeResource::class), CallPathAbstractModel::class);

        expect($analyzer->resolve(receiverHandlerExpr($php))['type'])->toBe($type);
    })->with([
        ['$this->resource->getKey()', 'number'],
        ['$this->resource?->getKey()', 'number | null'],
        ['$this->getKey()', 'number'],
    ]);

    test('declines in every spelling on an abstract model whose key code decides', function (string $php) {
        $analyzer = new ResourceAstAnalyzer(new ReflectionClass(ReceiverProbeResource::class), AbstractKeyTypeOverrideModel::class);

        expect($analyzer->resolve(receiverHandlerExpr($php))['type'])->toBe('unknown');
    })->with(['$this->resource->getKey()', '$this->resource?->getKey()', '$this->getKey()']);

    test('reads an abstract model\'s literal uniqueIds() list for its key type in every spelling', function (string $php, string $type) {
        $analyzer = new ResourceAstAnalyzer(new ReflectionClass(ReceiverProbeResource::class), AbstractUniqueIdsKeyModel::class);

        expect($analyzer->resolve(receiverHandlerExpr($php))['type'])->toBe($type);
    })->with([
        ['$this->resource->getKey()', 'number'],
        ['$this->resource?->getKey()', 'number | null'],
        ['$this->getKey()', 'number'],
    ]);

    test('unions an abstract model\'s key type with a published subclass that keys differently', function (string $php, string $type) {
        PublishedModelRegistry::register([IntKeyedModel::class, UuidKeyedModel::class, Post::class]);
        $analyzer = new ResourceAstAnalyzer(new ReflectionClass(ReceiverProbeResource::class), AbstractKeyedModel::class);

        expect($analyzer->resolve(receiverHandlerExpr($php))['type'])->toBe($type);
    })->with([
        ['$this->resource->getKey()', 'number | string'],
        ['$this->resource?->getKey()', 'number | string | null'],
        ['$this->getKey()', 'number | string'],
    ]);

    test('adds null for a receiver no row backs yet, and keeps the bare key type for one a row backs', function (string $php, string $type) {
        $analyzer = new ResourceAstAnalyzer(new ReflectionClass(ReceiverProbeResource::class), Post::class);

        expect($analyzer->resolve(receiverHandlerExpr($php))['type'])->toBe($type);
    })->with([
        ['(new \Workbench\App\Models\Post)->getKey()', 'number | null'],
        ['(new \Workbench\App\Models\Product)->getKey()', 'string | null'],
        ['$this->resource->replicate()->getKey()', 'number | null'],
        ['$this->replicate()->getKey()', 'number | null'],
        ['$this->resource->replicateQuietly()->getKey()', 'number | null'],
        ['$this->resource->newInstance()->getKey()', 'number | null'],
        ['$this->resource?->replicate()->getKey()', 'number | null'],
        ['$this->resource->getKey()', 'number'],
        ['$this->getKey()', 'number'],
        ['$this->author->getKey()', 'number'],
        ['$this->resource->fresh()->getKey()', 'number'],
        ['(true ? new \Workbench\App\Models\Post : $this->resource)->getKey()', 'number | null'],
        ['($this->resource ?? new \Workbench\App\Models\Post)->getKey()', 'number | null'],
        ['(new \Workbench\App\Models\Post)->fill([\'title\' => \'x\'])->getKey()', 'number | null'],
        ['$this->resource->replicate()->forceFill([])->getKey()', 'number | null'],
        ['$this->resource->fill([\'title\' => \'x\'])->getKey()', 'number'],
    ]);

    // Model::__callStatic() runs an undeclared static call on a new query, whose docblocks return TModel or $this.
    test('names the model a query or relation builds, adding null where it may be one no row backs yet', function (string $php, string $type) {
        $analyzer = new ResourceAstAnalyzer(new ReflectionClass(ReceiverProbeResource::class), Post::class);

        expect($analyzer->resolve(receiverHandlerExpr($php))['type'])->toBe($type);
    })->with([
        ['\Workbench\App\Models\Post::make([\'title\' => \'x\'])->getKey()', 'number | null'],
        ['\Workbench\App\Models\Post::firstOrNew([\'title\' => \'x\'])->getKey()', 'number | null'],
        ['\Workbench\App\Models\Post::newModelInstance()->getKey()', 'number | null'],
        ['\Workbench\App\Models\Post::query()->make()->getKey()', 'number | null'],
        ['\Workbench\App\Models\Post::where(\'title\', \'x\')->latest()->make()->getKey()', 'number | null'],
        ['$this->resource->comments()->make()->getKey()', 'number | null'],
        ['$this->resource->comments()->firstOrNew([])->getKey()', 'number | null'],
        ['\Workbench\App\Models\Post::query()->getModel()->getKey()', 'number | null'],
        ['$this->resource->newQuery()->getModel()->getKey()', 'number | null'],
        ['$this->author->profile()->newRelatedInstanceFor($this->author)->getKey()', 'number | null'],
        ['\Workbench\App\Models\Post::create([])->getKey()', 'number'],
        ['\Workbench\App\Models\Post::query()->firstOrFail()->getKey()', 'number'],
        ['$this->resource->comments()->create([])->getKey()', 'number'],
        ['\Workbench\App\Models\Post::find(1)->getKey()', 'unknown'],
        ['\Workbench\App\Models\Post::query()->get()->getKey()', 'unknown'],
        ['\Workbench\App\Models\Post::query()->getRelated()->getKey()', 'unknown'],
    ]);

    test('names the model a factory builds while no count is set', function (string $php, string $type) {
        $guesser = new ReflectionProperty(Factory::class, 'factoryNameResolver');
        $previous = $guesser->getValue();
        Factory::guessFactoryNamesUsing(fn (string $model): string => 'Workbench\\Database\\Factories\\'.class_basename($model).'Factory');

        try {
            $analyzer = new ResourceAstAnalyzer(new ReflectionClass(ReceiverProbeResource::class), Post::class);

            expect($analyzer->resolve(receiverHandlerExpr($php))['type'])->toBe($type);
        } finally {
            $guesser->setValue(null, $previous);
        }
    })->with([
        ['\Workbench\App\Models\User::factory()->make()->getKey()', 'number | null'],
        ['\Workbench\App\Models\User::factory()->state([])->makeOne()->getKey()', 'number | null'],
        ['\Workbench\App\Models\User::factory()->create()->getKey()', 'number'],
        ['\Workbench\App\Models\User::factory([\'name\' => \'x\'])->make()->getKey()', 'number | null'],
        ['\Workbench\App\Models\User::factory()->newModel()->getKey()', 'number | null'],
        ['\Workbench\App\Models\User::factory()->count(2)->make()->getKey()', 'unknown'],
        ['\Workbench\App\Models\User::factory(2)->make()->getKey()', 'unknown'],
    ]);

    test('declines a factory it cannot build, and a spread argument list, without failing the analysis', function (string $php, bool $guessed) {
        $guesser = new ReflectionProperty(Factory::class, 'factoryNameResolver');
        $previous = $guesser->getValue();

        if ($guessed) {
            Factory::guessFactoryNamesUsing(fn (string $model): string => 'Workbench\\Database\\Factories\\'.class_basename($model).'Factory');
        }

        try {
            $analyzer = new ResourceAstAnalyzer(new ReflectionClass(ReceiverProbeResource::class), Post::class);

            expect($analyzer->resolve(receiverHandlerExpr($php))['type'])->toBe('unknown');
        } finally {
            $guesser->setValue(null, $previous);
        }
    })->with([
        // TestCase's guesser names a factory class that does not exist, so User::factory() throws.
        'a factory class that cannot be resolved' => ['\Workbench\App\Models\User::factory()->make()->getKey()', false],
        'a spread argument list' => ['\Workbench\App\Models\User::factory(...$args)->make()->getKey()', true],
    ]);

    test('a local bound to a new model carries the unsaved flag to its getKey()', function (string $bound, string $type) {
        $scope = new AnalysisScope(new ReflectionClass(ReceiverProbeResource::class), Post::class);
        $scope->localVarBindings['draft'] = receiverHandlerExpr($bound);

        expect(resolve(ReceiverClassResolver::class)->resolve(receiverHandlerExpr('$draft'), $scope)?->unsaved)->toBeTrue()
            ->and(resolve(ReceiverMethodReturnResolver::class)->resolve(
                resolve(ReceiverClassResolver::class)->resolve(receiverHandlerExpr('$draft'), $scope),
                'getKey',
                $scope,
            )['type'] ?? null)->toBe($type);
    })->with([
        ['new \Workbench\App\Models\Post', 'number | null'],
        ['$this->resource->replicate()', 'number | null'],
    ]);

    // Past the write that ended its parameter binding the variable reads its local again, so the flag survives.
    test('a parameter reassigned to a new model carries the unsaved flag past the write', function () {
        $scope = new AnalysisScope(new ReflectionClass(ReceiverProbeResource::class), Post::class);
        $scope->varModelBindings['draft'] = User::class;
        $scope->localVarBindings['draft'] = receiverHandlerExpr('new \Workbench\App\Models\Post');
        $scope->bindingEnds['draft'] = 0;

        expect(resolve(ReceiverClassResolver::class)->resolve(receiverHandlerExpr('$draft'), $scope))
            ->toEqual(new ReceiverType([Post::class], unsaved: true, attributeless: true));
    });

    // VariableHandler leaves getKey() to the receiver rules, so a closure parameter keys as the model it holds.
    test('a variable bound to a loaded model keys as that model, through a closure parameter and a map', function () {
        $props = collect(resolve(AstEngine::class)->analyze(ReceiverMethodResource::class)->properties)->keyBy('name');

        expect($props['loaded_author_key'])->toMatchArray(['type' => 'number', 'optional' => true])
            ->and($props['comment_keys']['type'])->toBe('number[]');
    });

    // The package does not follow save(), so the key keeps the `| null` of a new model though Laravel sends a number.
    test('a new model saved before its key is read still keys as possibly null', function () {
        $props = collect(resolve(AstEngine::class)->analyze(VariableHeldModelResource::class, 'savedDraft', LabeledPost::class)->properties);

        expect($props->pluck('type', 'name')->all())->toBe(['saved_draft_key' => 'number | null']);
    });

    // VariableHandler declines every method typesByRule() names, so a rule ruleFor() gains reaches a variable unedited.
    test('typesByRule() names exactly the methods ruleFor() answers', function (string $class, array $named) {
        $rules = resolve(ReceiverMethodReturnResolver::class);
        $ruleFor = new ReflectionMethod($rules, 'ruleFor');
        $scope = new AnalysisScope(new ReflectionClass(ReceiverProbeResource::class), $class);
        $methods = get_class_methods($class);

        $typed = array_values(array_filter($methods, fn (string $method): bool => $rules->typesByRule($class, $method)));
        $answered = array_values(array_filter(
            $methods,
            fn (string $method): bool => $ruleFor->invoke($rules, ReceiverType::of($class), $class, $method, $scope, null) !== null,
        ));

        expect($typed)->toBe($answered)->toContain(...$named)->not->toContain('only', 'except');
    })->with([
        'a concrete model' => [Post::class, ['getKey', 'toArray', 'all', 'getCasts', 'resolveRouteBinding']],
        'an authenticatable model' => [User::class, ['getKey', 'getAuthIdentifier', 'toArray', 'all', 'resolveRouteBinding']],
        'one identified by another column' => [EmailIdentifierUser::class, ['getKey', 'getAuthIdentifier']],
        'an abstract model' => [CallPathAbstractModel::class, ['getKey']],
    ]);

    test('a null key prints null once and an abstract model\'s union gains one null arm when unsaved', function () {
        PublishedModelRegistry::register([IntKeyedModel::class, UuidKeyedModel::class, Post::class]);
        $nullKey = new ResourceAstAnalyzer(new ReflectionClass(ReceiverProbeResource::class), Post::class);
        $abstract = new ResourceAstAnalyzer(new ReflectionClass(ReceiverProbeResource::class), AbstractKeyedModel::class);

        expect($nullKey->resolve(receiverHandlerExpr('(new \\'.NullKeyPost::class.')->replicate()->getKey()'))['type'])->toBe('null')
            ->and($abstract->resolve(receiverHandlerExpr('$this->resource->replicate()->getKey()'))['type'])->toBe('number | string | null')
            ->and($abstract->resolve(receiverHandlerExpr('$this->replicate()->getKey()'))['type'])->toBe('number | string | null');
    });

    test('getAuthIdentifier() types the column the model identifies by, with null where no row backs it yet', function (string $php, string $type) {
        $analyzer = new ResourceAstAnalyzer(new ReflectionClass(ReceiverProbeResource::class), EmailIdentifierUser::class);

        expect($analyzer->resolve(receiverHandlerExpr($php))['type'])->toBe($type);
    })->with([
        ['$this->resource->getAuthIdentifier()', 'string'],
        ['$this->getAuthIdentifier()', 'string'],
        ['(new \\'.EmailIdentifierUser::class.')->getAuthIdentifier()', 'string | null'],
        ['(new \\'.User::class.')->getAuthIdentifier()', 'number | null'],
        ['(new \\'.ComputedIdentifierUser::class.')->getAuthIdentifier()', 'string'],
        ['(new \\'.Post::class.')->getAuthIdentifier()', 'unknown'],
    ]);

    test('the dispatch floor no longer answers getKey()', function () {
        $scope = new AnalysisScope(new ReflectionClass(ReceiverProbeResource::class), CallPathAbstractModel::class);

        expect(new KnownMethodRuleHandler()->resolve(receiverHandlerExpr('$this->resource->getKey()'), $scope, chainHandlersThrowingEngine()))
            ->toBeNull();
    });
});

describe('a call on $this->prop types from the class the property holds, never from the scope model', function () {
    test('a value object a resource holds publishes its own declarations, not its model namesakes', function () {
        $props = collect(new ResourceAstAnalyzer(new ReflectionClass(PostStatsResource::class), Post::class)->analyze()->properties)
            ->mapWithKeys(fn (array $p): array => [$p['name'] => $p['type']]);

        expect($props['blockers'])->toBe('{ message: string; route?: string }[]')
            ->and($props['checks_count'])->toBe('string')
            ->and($props['loaded_checks_count'])->toBe('string')
            ->and($props['loaded_checks_label'])->toBe('string');
    });

    test('a relation call reads the related model, so a method only the scope model declares declines', function (string $php, string $type) {
        $analyzer = new ResourceAstAnalyzer(new ReflectionClass(ReceiverMethodResource::class), Post::class);

        expect($analyzer->resolve(receiverHandlerExpr($php))['type'])->toBe($type);
    })->with([
        ['$this->author->commentsCount()', 'unknown'],
        ['$this->author->toArray()', 'Record<string, unknown>'],
        ['$this->resource->commentsCount()', 'number'],
        ['$this->resource->toArray()', 'Record<string, unknown>'],
        ['$this->comments->toArray()', 'Record<string, unknown>[]'],
        ['$this->resource->comments->toArray()', 'Record<string, unknown>[]'],
        ['$this->author?->toArray()', 'Record<string, unknown> | null'],
        ['$this->comments?->toArray()', 'Record<string, unknown>[] | null'],
        ['$this->author->attributesToArray()', 'Record<string, unknown>'],
        ['$this->author->getAttributes()', 'Record<string, unknown>'],
        ['$this->author->getDirty()', 'Record<string, unknown>'],
        ['$this->author->getAppends()', 'string[]'],
        ['$this->author->getMutatedAttributes()', 'string[]'],
        ['$this->author->getTouchedRelations()', 'string[]'],
        ['$this->author->uniqueIds()', 'string[]'],
        ['$this->author->getQueueableRelations()', 'string[]'],
        ['$this->author->getCasts()', 'Record<string, string>'],
        ['$this->author->getGlobalScopes()', 'Record<string, unknown>'],
        ['$this->author->getRelations()', 'Record<string, unknown>'],
        ['$this->author->relationsToArray()', 'Record<string, unknown>'],
        ['$this->author->guessResourceName()', 'string[]'],
        ['$this->author->qualifyColumns([\'id\'])', 'string[]'],
        ['$this->author->resolveGlobalScopeAttributes()', 'string[]'],
        ['$this->author->resolveObserveAttributes()', 'string[]'],
        ['$this->author->getAllGlobalScopes()', 'Record<string, Record<string, unknown>>'],
        ['$this->author->all()', 'unknown[]'],
        ['$this->author?->getCasts()', 'Record<string, string> | null'],
        ['$this->resource->getCasts()', 'Record<string, string>'],
        ['$this->resource->getAppends()', 'string[]'],
        ['$this->getCasts()', 'Record<string, string>'],
        ['$this::getAppends()', 'string[]'],
        ['$this::all()', 'unknown[]'],
        ['$this::getCasts()', 'Record<string, string>'],
        ['$this->resource::getAppends()', 'string[]'],
        ['$this->resource::all()', 'unknown[]'],
        ['$this->resource::getCasts()', 'Record<string, string>'],
        ['$this->comments?->all()', 'Comment[] | null'],
    ]);

    test('inside a relation closure, $this still names the resource, whatever the related model declares', function (string $php, string $type) {
        $analyzer = new ResourceAstAnalyzer(new ReflectionClass(PostStatsResource::class), ClosureNamesakePost::class);

        expect($analyzer->resolve(receiverHandlerExpr($php))['type'])->toBe($type);
    })->with([
        ['$this->whenLoaded(\'namesakeAuthor\', fn () => $this->published_at?->format(\'Y\'))', 'string | null'],
        ['$this->whenLoaded(\'namesakeAuthor\', fn () => $this->published_at->format(\'Y\'))', 'string'],
        ['$this->whenLoaded(\'namesakeAuthor\', fn () => $this->checks->commentsCount())', 'string'],
        ['$this->whenLoaded(\'namesakeAuthor\', fn () => $this->checks::label())', 'string'],
        ['$this->whenLoaded(\'namesakeAuthor\', fn () => $this->namesakeAuthor?->format())', 'number | null'],
    ]);

    test('a model-less scope types a filter on a held model by the receiver rules', function (string $php, string $type) {
        $analyzer = new ResourceAstAnalyzer(new ReflectionClass(PostReceiverController::class), null, '__construct', ControllerExpressionHandlers::make());

        expect($analyzer->resolve(receiverHandlerExpr($php))['type'])->toBe($type);
    })->with([
        ['$this->post->except($keys)', 'Record<string, unknown>'],
        ['$this->post->only([\'id\', \'title\'])', "Pick<Post, 'id' | 'title'>"],
        ['$this->checks->toArray()', '{ message: string; route?: string }[]'],
        ['$this->post->commentsCount()', 'number'],
        ['$this->post->toArray()', 'Record<string, unknown>'],
        ['$this->formatter->title()', 'string'],
        // PHP calls no protected method from outside the class, so the read declines.
        ['$this->formatter->heading()', 'unknown'],
    ]);

    test('a resource types a property it holds from that class, nullable or not, and never from its own model', function (string $php, string $type) {
        $analyzer = new ResourceAstAnalyzer(new ReflectionClass(HeldReceiversCommentResource::class), Comment::class);

        expect($analyzer->resolve(receiverHandlerExpr($php))['type'])->toBe($type);
    })->with([
        ['$this->checks?->toArray()', '{ message: string; route?: string }[] | null'],
        ['$this->other->commentsCount()', 'number'],
        ['$this->other->only([\'id\'])', "Pick<Post, 'id'>"],
        ['$this->other->toArray()', 'Record<string, unknown>'],
    ]);
});

describe('$this->resource->m() publishes what the bare $this->m() does', function () {
    test('a vague array helper types from its body in every spelling, a trait\'s included', function (string $method, string $type) {
        $analyzer = new ResourceAstAnalyzer(new ReflectionClass(ReceiverMethodResource::class), ClosureNamesakePost::class);

        expect($analyzer->resolve(receiverHandlerExpr('$this->resource->'.$method.'()'))['type'])->toBe($type)
            ->and($analyzer->resolve(receiverHandlerExpr('$this->'.$method.'()'))['type'])->toBe($type)
            ->and($analyzer->resolve(receiverHandlerExpr('$this->resource?->'.$method.'()'))['type'])->toBe($type.' | null');
    })->with([
        ['meta', '{ a: number; b: string }'],
        ['traitMeta', '{ c: boolean; d: number }'],
    ]);

    test('the workbench summary publishes the same shape through the proxy', function () {
        $props = collect(new ResourceAstAnalyzer(new ReflectionClass(CommentRelationFiltersResource::class), Comment::class)->analyze()->properties)
            ->mapWithKeys(fn (array $p): array => [$p['name'] => $p['type']]);

        expect($props['summary_via_resource'])->toBe($props['summary'])
            ->and($props['summary'])->toStartWith('{ id: number;');
    });
});

describe('a forwarded model method reads the same in every spelling', function () {
    $spellings = ['$this->%s()', '$this::%s()', '$this->resource::%s()', '$this->resource->%s()'];

    test('the wrapped class answers when it declares the method', function (string $method, string $type) use ($spellings) {
        $analyzer = new ResourceAstAnalyzer(new ReflectionClass(ForwardedMethodResource::class), ForwardedMethodPost::class);

        foreach ($spellings as $spelling) {
            expect($analyzer->resolve(receiverHandlerExpr(sprintf($spelling, $method)))['type'])->toBe($type);
        }
    })->with([
        ['onBoth', 'number'],
        ['onWrapped', 'boolean'],
    ]);

    // The wrapped class an `@var` names is final, so the model's onModel() never answers: Laravel throws
    // BadMethodCallException.
    test('a protected method, or one the wrapped class lacks, is unknown, as the forwarded call throws', function (string $method) use ($spellings) {
        $analyzer = new ResourceAstAnalyzer(new ReflectionClass(ForwardedMethodResource::class), ForwardedMethodPost::class);

        foreach ($spellings as $spelling) {
            expect($analyzer->resolve(receiverHandlerExpr(sprintf($spelling, $method)))['type'])->toBe('unknown');
        }
    })->with(['guarded', 'hiddenOnWrapped', 'onModel']);

    // The model declares each method public and typed. A protected method throws Error, which forwardCallTo() rethrows.
    test('a wrapped method that types nothing never borrows the model\'s typed namesake', function (string $resource, string $method) use ($spellings) {
        $analyzer = new ResourceAstAnalyzer(new ReflectionClass($resource), NamesakeInvoice::class);

        foreach ($spellings as $spelling) {
            expect($analyzer->resolve(receiverHandlerExpr(sprintf($spelling, $method)))['type'])->toBe('unknown');
        }
    })->with([
        'a protected method' => [NamesakeProtectedResource::class, 'isFirst'],
        'a mixed method whose body types nothing' => [NamesakeWrappedResource::class, 'opaque'],
    ]);
});

describe('the method declaration PHP runs is final', function () {
    test('the subject\'s own method, else the wrapped class\'s, types the call, else it publishes unknown', function (string $resource, string $key, string $type) {
        expect(collect(pipelineAnalyzer($resource)->analyze()->properties)->keyBy('name')[$key]['type'])->toBe($type);
    })->with([
        [NamesakeWrappedResource::class, 'status', 'string'],
        [NamesakeWrappedResource::class, 'status_via_resource', 'string'],
        [NamesakeWrappedResource::class, 'kind', 'string'],
        [NamesakeWrappedResource::class, 'reference', 'string'],
        [NamesakeWrappedResource::class, 'total', 'number'],
        [NamesakeWrappedResource::class, 'opaque', 'unknown'],
        [NamesakeWrappedResource::class, 'opaque_via_resource', 'unknown'],
        [NamesakeProtectedResource::class, 'first', 'unknown'],
        [NamesakeProtectedResource::class, 'first_via_resource', 'unknown'],
        [NamesakeInvoiceResource::class, 'opaque', 'unknown'],
        [NamesakeInvoiceResource::class, 'untyped_opaque', 'unknown'],
    ]);
});

// Every API resource has the proxy, model or not: PHP reads the declared JsonResource::$resource before any __get().
// No other subject has it, so in a model's own body or a controller `$this->resource` is a member like any other.
describe('the resource proxy', function () {
    test('a resource over a model with a resource relation reads $this->resource as that model', function (string $php, string $type) {
        $analyzer = new ResourceAstAnalyzer(new ReflectionClass(ProxyRelationPostResource::class), ProxyRelationPost::class);

        expect($analyzer->resolve(receiverHandlerExpr($php))['type'])->toBe($type);
    })->with([
        ['$this->resource', 'ProxyRelationPost'],
        ['$this->resource->title', 'string'],
        ['$this->resource->headline()', 'string'],
        ['$this->resource?->headline()', 'string | null'],
        // Only the related User has nameTitled(); the post throws BadMethodCallException.
        ['$this->resource?->nameTitled()', 'unknown'],
        ['$this->resource->resource', 'User'],
        ['$this->resource->resource->name', 'string'],
        ['$this->resource->resource?->nameTitled()', 'string | null'],
        ['$this->resource->only([\'id\', \'title\'])', "Pick<ProxyRelationPost, 'id' | 'title'>"],
        ['$this->resource->resource->only([\'id\', \'name\'])', "Pick<User, 'id' | 'name'>"],
        ['$this->resource->author?->nameTitled()', 'string | null'],
    ]);

    test('a model\'s own body reads $this->resource as a member, never the proxy', function (string $model, string $php, string $type) {
        $analyzer = new ResourceAstAnalyzer(new ReflectionClass($model), $model);

        expect($analyzer->resolve(receiverHandlerExpr($php))['type'])->toBe($type);
    })->with([
        // Post has no `resource` member, so each read throws or is always null.
        [Post::class, '$this->resource->title', 'unknown'],
        [Post::class, '$this->resource?->title', 'unknown'],
        [Post::class, '$this->resource->commentsCount()', 'unknown'],
        [Post::class, '$this->resource->author->name', 'unknown'],
        [ProxyRelationPost::class, '$this->resource->name', 'string'],
        [ProxyRelationPost::class, '$this->resource?->nameTitled()', 'string | null'],
        [ProxyRelationPost::class, '$this->resource->only([\'id\', \'name\'])', "Pick<User, 'id' | 'name'>"],
    ]);

    test('a model-less subject reads $this->resource as its own property', function (string $php, string $type) {
        $analyzer = new ResourceAstAnalyzer(new ReflectionClass(ProxyRelationController::class), null, '__construct', ControllerExpressionHandlers::make());

        expect($analyzer->resolve(receiverHandlerExpr($php))['type'])->toBe($type);
    })->with([
        ['$this->resource', 'Post'],
        ['$this->resource->title', 'string'],
        ['$this->resource?->title', 'string | null'],
        ['$this->resource->author->name', 'string'],
        ['$this->resource?->author?->name', 'string | null'],
        ['$this->resource->author?->nameTitled()', 'string | null'],
        ['$this->resource?->commentsCount()', 'number | null'],
        ['$this->resource->only([\'id\'])', "Pick<Post, 'id'>"],
        ['$this->post->resource->name', 'string'],
        ['$this->post->resource?->nameTitled()', 'string | null'],
        ['$this->post->resource->only([\'id\', \'name\'])', "Pick<User, 'id' | 'name'>"],
    ]);

    test('a filter on a member the model does not have is unknown, through the proxy or not', function (string $php) {
        $analyzer = new ResourceAstAnalyzer(new ReflectionClass(ReceiverMethodResource::class), Post::class);

        expect($analyzer->resolve(receiverHandlerExpr($php))['type'])->toBe('unknown');
    })->with([
        '$this->not_a_member->only([\'id\'])',
        '$this->resource->not_a_member->only([\'id\'])',
    ]);

    test('the bare $this->resource publishes the backing model with its import', function () {
        $analyzer = new ResourceAstAnalyzer(new ReflectionClass(ReceiverMethodResource::class), Post::class);

        expect($analyzer->resolve(receiverHandlerExpr('$this->when($flag, $this->resource)')))
            ->toBe(['type' => 'Post', 'optional' => true, 'modelFqcn' => Post::class]);
    });

    // Laravel sends `null` for a top-level `PostResource::make(null)`; only a redeclaration says a resource gets one.
    test('the bare $this->resource keeps the null arm a redeclaration of the backing model states', function (string $resource, string $type) {
        $analyzer = new ResourceAstAnalyzer(new ReflectionClass($resource), Post::class);

        expect($analyzer->resolve(receiverHandlerExpr('$this->resource')))
            ->toBe(['type' => $type, 'optional' => false, 'modelFqcn' => Post::class]);
    })->with([
        '@var Post|null' => [ModelWrappedPropResource::class, 'Post | null'],
        '@var ?Post' => [NullableShorthandWrappedResource::class, 'Post | null'],
        '@var null | Post' => [SpacedDocblockResource::class, 'Post | null'],
        '@var Model' => [ProxyModelPostResource::class, 'Post'],
        'undeclared' => [ReceiverMethodResource::class, 'Post'],
    ]);

    // A test for the backing model rules out the null a redeclaration admits, so the proven arm holds the model alone.
    test('an instanceof proof keeps the null a nullable redeclaration states out of its arm', function (string $resource, string $php, string $type) {
        $analyzer = new ResourceAstAnalyzer(new ReflectionClass($resource), Post::class);

        expect($analyzer->resolve(receiverHandlerExpr($php))['type'])->toBe($type);
    })->with([
        '@var Post|null' => [ModelWrappedPropResource::class, '$this->resource instanceof '.Post::class.' ? $this->resource : \'none\'', 'Post | string'],
        '@var ?Post' => [NullableShorthandWrappedResource::class, '$this->resource instanceof '.Post::class.' ? $this->resource : false', 'Post | boolean'],
        '@var ?Post, inside when()' => [NullableShorthandWrappedResource::class, '$this->when($flag, fn () => $this->resource instanceof '.Post::class.' ? $this->resource : 0)', 'Post | number'],
    ]);

    test('an early-return instanceof guard keeps the null a nullable redeclaration states off the guarded read', function () {
        $properties = new ResourceAstAnalyzer(new ReflectionClass(NullableGuardedPostResource::class), Post::class)->analyze()->properties;

        expect(collect($properties)->firstWhere('name', 'guarded'))
            ->toMatchArray(['name' => 'guarded', 'type' => 'Post', 'optional' => true]);
    });

    test('a model-backed resource reads the class it redeclares $resource as, whole and through every forwarded member', function (string $resource, string $model, string $php, string $type) {
        $analyzer = new ResourceAstAnalyzer(new ReflectionClass($resource), $model);

        expect($analyzer->resolve(receiverHandlerExpr($php))['type'])->toBe($type);
    })->with([
        [MediaTypeResource::class, Post::class, '$this->resource', 'MediaTypeType | null'],
        [MediaTypeResource::class, Post::class, '$this->maxSizeMb()', 'number'],
        [MediaTypeResource::class, Post::class, '$this::maxSizeMb()', 'number'],
        [MediaTypeResource::class, Post::class, '$this->resource->maxSizeMb()', 'number'],
        [MediaTypeResource::class, Post::class, '$this->resource::mimePrefixes()', '{ image: string; video: string; audio: string; document: string; archive: string }'],
        // Post's commentsCount() never answers: MediaType has none, so Laravel throws.
        [MediaTypeResource::class, Post::class, '$this->commentsCount()', 'unknown'],
        [NamesakeWrappedResource::class, NamesakeInvoice::class, '$this->resource', '{ code: number }'],
        [NamesakeWrappedResource::class, NamesakeInvoice::class, '$this->resource->code', 'number'],
        [NamesakeWrappedResource::class, NamesakeInvoice::class, '$this->status()', 'string'],
    ]);

    test('a model-less resource reads its redeclared $resource as before', function (string $php, string $type) {
        $analyzer = new ResourceAstAnalyzer(new ReflectionClass(MediaTypeResource::class));

        expect($analyzer->resolve(receiverHandlerExpr($php))['type'])->toBe($type);
    })->with([
        ['$this->resource', 'MediaTypeType | null'],
        ['$this->resource->name', 'string'],
        ['$this->resource->value', 'string'],
        // MediaType::extensions() declares only array, so its match of string lists types it.
        ['$this->resource->extensions()', 'string[]'],
    ]);
});

describe('what $this->resource holds, through every path', function () {
    $prefixes = '{ image: string; video: string; audio: string; document: string; archive: string }';

    test('a redeclared model supertype holds the backing model', function (string $php, string $type) {
        expect(pipelineAnalyzer(ProxyModelPostResource::class)->resolve(receiverHandlerExpr($php))['type'])->toBe($type);
    })->with([
        ['$this->resource', 'Post'],
        ['$this->commentsCount()', 'number'],
        ['$this->resource->commentsCount()', 'number'],
        ['$this->getKey()', 'number'],
        ['$this->fresh()', 'Post | null'],
        ['$this->author->getKey()', 'number'],
    ]);

    test('an instanceof test narrows a redeclared union to the class it proves', function (?string $model, string $php, string $type) {
        $analyzer = new ResourceAstAnalyzer(new ReflectionClass(ProxyPostOrImageResource::class), $model);

        expect($analyzer->resolve(receiverHandlerExpr('$this->resource instanceof '.Post::class.' ? '.$php.' : null'))['type'])->toBe($type);
    })->with([
        [null, '$this->resource', 'Post | null'],
        [null, '$this->fresh()', 'Post | null'],
        [null, '$this->author->getKey()', 'number | null'],
        [null, '$this->author->fresh()', 'User | null'],
        [Post::class, '$this->resource', 'Post | null'],
        [Post::class, '$this->fresh()', 'Post | null'],
        [Post::class, '$this->author->getKey()', 'number | null'],
        [Post::class, '$this->author->fresh()', 'User | null'],
    ]);

    // A forwarded call runs on the first class that declares it; a receiver on `$this->resource` holds every class.
    test('a redeclared union of models forwards a call to the first class that declares it', function (string $php, string $type) {
        $analyzer = new ResourceAstAnalyzer(new ReflectionClass(ProxyPostOrImageResource::class));

        expect($analyzer->resolve(receiverHandlerExpr($php))['type'])->toBe($type);
    })->with([
        ['$this->resource', 'Post | Image'],
        ['$this->fresh()', 'Post | null'],
        ['$this->resource->fresh()', 'Post | Image | null'],
        ['$this->commentsCount()', 'number'],
        ['$this->resource->commentsCount()', 'number'],
    ]);

    // A method call cannot run on the string arm, so the enum answers it, and the model never does.
    test('a redeclaration with a builtin arm still names its class arm', function (?string $model, string $php, string $type) use ($prefixes) {
        $analyzer = new ResourceAstAnalyzer(new ReflectionClass(ProxyMediaOrStringResource::class), $model);

        expect($analyzer->resolve(receiverHandlerExpr($php))['type'])->toBe($type === 'prefixes' ? $prefixes : $type);
    })->with([
        [null, '$this->maxSizeMb()', 'number'],
        [null, '$this->resource->maxSizeMb()', 'number'],
        [null, '$this->resource::mimePrefixes()', 'prefixes'],
        [Post::class, '$this->maxSizeMb()', 'number'],
        [Post::class, '$this->resource->maxSizeMb()', 'number'],
        [Post::class, '$this->resource::mimePrefixes()', 'prefixes'],
        [Post::class, '$this->commentsCount()', 'unknown'],
    ]);

    // The pipeline backs `@var Post|Image` with its first model. Read whole it publishes that model; every other read
    // holds each class the redeclaration names, and an instanceof proof holds the class it proves.
    test('a redeclaration naming several classes holds each of them, and a proof holds the one it proves', function (string $resource, string $php, string $type) use ($prefixes) {
        expect(pipelineAnalyzer($resource)->resolve(receiverHandlerExpr($php))['type'])->toBe($type === 'prefixes' ? $prefixes : $type);
    })->with([
        [ProxyPostOrImageResource::class, '$this->resource', 'Post'],
        [ProxyPostOrImageResource::class, '$this->resource instanceof '.Image::class.' ? $this->resource : null', 'Image | null'],
        [ProxyPostOrImageResource::class, '$this->resource instanceof '.Image::class.' ? $this->fresh() : null', 'Image | null'],
        [ProxyPostOrImageResource::class, '$this->resource instanceof '.Image::class.' ? $this->resource->fresh() : null', 'Image | null'],
        [ProxyPostOrImageResource::class, '$this->resource->fresh()', 'Post | Image | null'],
        [ProxyPostOrImageResource::class, '$this->resource->replicate()', 'Post | Image'],
        [ProxyPostOrImageResource::class, '$this->fresh()', 'Post | null'],
        [ProxyPostOrImageResource::class, '$this->commentsCount()', 'number'],
        [ProxyModelPostResource::class, '$this->resource instanceof '.Image::class.' ? $this->resource : null', 'Image | null'],
        [ProxyMediaOrPostResource::class, '$this->resource', 'Post'],
        [ProxyMediaOrPostResource::class, '$this->maxSizeMb()', 'number'],
        [ProxyMediaOrPostResource::class, '$this->resource->maxSizeMb()', 'number'],
        [ProxyMediaOrPostResource::class, '$this->resource::mimePrefixes()', 'prefixes'],
        [ProxyMediaOrPostResource::class, '$this->resource instanceof '.MediaType::class.' ? $this->resource->maxSizeMb() : null', 'number | null'],
        [ProxyMediaOrPostResource::class, '$this->fresh()', 'Post | null'],
        // A proven class that lacks the method hands the call on: to the redeclared classes in order, then the model.
        [ProxyPostOrImageResource::class, '$this->resource instanceof '.Image::class.' ? $this->commentsCount() : null', 'number | null'],
        [ProxyMediaOrPostResource::class, '$this->resource instanceof '.MediaType::class.' ? $this->fresh() : null', 'Post | null'],
    ]);

    // Under its own proof `$this->resource` read whole is the proven class; one that renders as nothing leaves the
    // expression unknown rather than publishing the other arm alone.
    test('an instanceof proof renders the proven class whole', function (string $resource, string $class, string $type) {
        $php = '$this->resource instanceof '.$class.' ? $this->resource : null';

        expect(pipelineAnalyzer($resource)->resolve(receiverHandlerExpr($php))['type'])->toBe($type);
    })->with([
        [ProxyMediaOrPostResource::class, MediaType::class, 'MediaTypeType | null'],
        [MediaTypeInstanceOfResource::class, MediaType::class, 'MediaTypeType | null'],
        [MediaTypeInstanceOfResource::class, NamesakeStatusCard::class, '{ code: number } | null'],
        [MediaTypeInstanceOfResource::class, JsonSerializable::class, 'unknown'],
    ]);

    // Each redeclared supertype admits the backing model, so a test that model passes proves it; only a test it fails
    // narrows the declaration itself, as `instanceof Image` does.
    test('a test the backing model passes holds that model, never the supertype $resource is redeclared as', function (string $resource, string $php, string $type) {
        expect(pipelineAnalyzer($resource)->resolve(receiverHandlerExpr($php))['type'])->toBe($type);
    })->with([
        [ProxyAuthenticatableResource::class, '$this->resource instanceof '.CanResetPassword::class.' ? $this->resource : null', 'User | null'],
        [ProxyAuthenticatableResource::class, '$this->resource instanceof '.CanResetPassword::class.' ? $this->resource->getKey() : null', 'number | null'],
        [ProxyModelPostResource::class, '$this->resource instanceof '.Model::class.' ? $this->resource : null', 'Post | null'],
        [ProxyModelPostResource::class, '$this->resource instanceof '.Arrayable::class.' ? $this->resource : null', 'Post | null'],
        [ProxyModelPostResource::class, '$this->resource instanceof '.Model::class.' ? $this->resource->getKey() : null', 'number | null'],
        [ProxyModelPostResource::class, '$this->resource instanceof '.Model::class.' ? $this->title : null', 'string | null'],
        [ProxyModelPostResource::class, '$this->resource instanceof '.Model::class.' ? $this->commentsCount() : null', 'number | null'],
        [ProxyModelPostResource::class, '$this->resource instanceof '.Model::class.' ? $this->fresh() : null', 'Post | null'],
        [ProxyModelPostResource::class, '$this->resource instanceof '.Model::class.' ? $this->author->getKey() : null', 'number | null'],
        [ProxyModelPostResource::class, '$this->resource instanceof '.Image::class.' ? $this->resource : null', 'Image | null'],
    ]);

    test('a resource redeclaring $resource as an interface its model implements publishes that model under a test it passes', function () {
        $props = collect(pipelineAnalyzer(ProxyAuthenticatableResource::class)->analyze()->properties)->keyBy('name');

        expect($props['user']['type'])->toBe('User | null')
            ->and($props['key']['type'])->toBe('number | null');
    });

    // Read whole, the pipeline's `@var Post|Image` publishes its backing model; a test that rules out neither class
    // proves nothing more, so it keeps that answer rather than widen it, while a receiver still holds both.
    test('a test that rules out no class the redeclaration names keeps the unproven read, and one that rules one out narrows', function (string $php, string $type) {
        expect(pipelineAnalyzer(ProxyPostOrImageResource::class)->resolve(receiverHandlerExpr($php))['type'])->toBe($type);
    })->with([
        ['$this->resource instanceof '.Model::class.' ? $this->resource : null', 'Post | null'],
        ['$this->resource instanceof '.Post::class.' || $this->resource instanceof '.Image::class.' ? $this->resource : null', 'Post | null'],
        ['$this->resource instanceof '.Model::class.' ? $this->resource->fresh() : null', 'Post | Image | null'],
        ['$this->resource instanceof '.Model::class.' ? $this->fresh() : null', 'Post | null'],
        ['$this->resource instanceof '.Image::class.' ? $this->resource : null', 'Image | null'],
    ]);

    test('a redeclaration whose one class arm is the backing model holds it beside a builtin arm', function (string $php, string $type) {
        expect(pipelineAnalyzer(ProxyPostOrStringResource::class)->resolve(receiverHandlerExpr($php))['type'])->toBe($type);
    })->with([
        ['$this->resource', 'Post'],
        ['$this->resource->getKey()', 'number'],
        ['$this->resource->fresh()', 'Post | null'],
        ['$this->resource->author->getKey()', 'number'],
        ['$this->getKey()', 'number'],
    ]);

    // After the negated early return PHP holds the media type for the rest of toArray().
    test('a guard on a model-backed resource answers a method it declares', function (string $key, string $type) use ($prefixes) {
        $props = collect(pipelineAnalyzer(ProxyGuardedMediaResource::class)->analyze()->properties)->keyBy('name');

        expect($props[$key]['type'])->toBe($type === 'prefixes' ? $prefixes : $type);
    })->with([
        ['max', 'number'],
        ['max_via_resource', 'number'],
        ['prefixes', 'prefixes'],
    ]);
});

/**
 * An analyzer over a resource with the model the publish pipeline resolves for it, as ResourceTransformer builds it.
 *
 * @param  class-string  $resource
 */
function pipelineAnalyzer(string $resource): ResourceAstAnalyzer
{
    $reflection = new ReflectionClass($resource);

    return new ResourceAstAnalyzer($reflection, resolve(ModelClassResolver::class)->resolve($reflection));
}

describe('a ResourceCollection holds its items, never the collected model', function () {
    test('a collection whose @mixin model shares Support\Collection\'s method names reads its items', function (string $php, string $type) {
        expect(pipelineAnalyzer(ProxyCollectedPostCollection::class)->resolve(receiverHandlerExpr($php))['type'])->toBe($type);
    })->with([
        ['$this->first()', 'ProxyCollectedPostResource | null'],
        ['$this->resource->first()', 'ProxyCollectedPostResource | null'],
        ['$this->resource', 'ProxyCollectedPostResource[] | LengthAwarePaginator<ProxyCollectedPostResource> | SimplePaginator<ProxyCollectedPostResource> | CursorPaginator<ProxyCollectedPostResource>'],
        ['$this->resource->toArray()', 'ProxyCollectedPostResource[] | LengthAwarePaginator<ProxyCollectedPostResource> | SimplePaginator<ProxyCollectedPostResource> | CursorPaginator<ProxyCollectedPostResource>'],
        ['$this->resource->count()', 'number'],
        ['$this->only([\'id\'])', 'ProxyCollectedPostResource[] | Record<string, ProxyCollectedPostResource>'],
        // A collection pipeline on the items, or a model member Laravel throws on.
        ['$this->values()', 'unknown'],
        ['$this->sum(\'id\')', 'unknown'],
        ['$this->title', 'unknown'],
        ['$this->resource->title', 'unknown'],
        ['$this->getKey()', 'unknown'],
        ['$this->resource->pluck(\'title\')', 'unknown'],
        ['$this->collection', 'ProxyCollectedPostResource[]'],
    ]);

    // A guard holds only inside its branch, so `page`, read past it, keeps every class the items may arrive in.
    test('a guard on a paginator keeps the items answer', function (string $key, string $type) {
        expect(collect(pipelineAnalyzer(ProxyGuardedPageCollection::class)->analyze()->properties)->keyBy('name')[$key]['type'])->toBe($type);
    })->with([
        ['total', 'number'],
        ['total_via_resource', 'number'],
        ['page_items', 'ProxyCollectedPostResource[]'],
        ['page', 'ProxyCollectedPostResource[] | LengthAwarePaginator<ProxyCollectedPostResource> | SimplePaginator<ProxyCollectedPostResource> | CursorPaginator<ProxyCollectedPostResource>'],
    ]);

    test('a collection that redeclares $resource holds its items in the class it names, whole and through every member', function (string $php, string $type) {
        expect(pipelineAnalyzer(ProxyDeclaredPageCollection::class)->resolve(receiverHandlerExpr($php))['type'])->toBe($type);
    })->with([
        ['$this->resource', 'LengthAwarePaginator<ProxyCollectedPostResource>'],
        ['$this->resource->toArray()', 'LengthAwarePaginator<ProxyCollectedPostResource>'],
        ['$this->total()', 'number'],
        ['$this->resource->total()', 'number'],
        ['$this->first()', 'ProxyCollectedPostResource | null'],
        ['$this->resource->first()', 'ProxyCollectedPostResource | null'],
        ['$this->items()', 'ProxyCollectedPostResource[]'],
    ]);

    test('a collection that collects no resource holds unknown items', function () {
        expect(pipelineAnalyzer(ProxyRawPostCollection::class)->resolve(receiverHandlerExpr('$this->resource->toArray()'))['type'])
            ->toBe('unknown[] | LengthAwarePaginator<unknown> | SimplePaginator<unknown> | CursorPaginator<unknown>');
    });
});

// A resource can wrap null, which ends the whole `?->` chain as null, so the published type carries one `| null` and
// a resource wrapping a model reads the pipeline's own answer.
describe('a pipeline on a relation read through $this->resource?->', function () {
    /** Runs the PHP expression as a resource body would, around the model the resource wraps. */
    $runAsResource = function (string $php, ?Post $wrapped): mixed {
        $resource = new class($wrapped) extends JsonResource {};

        return Closure::bind(eval('return fn () => '.$php.';'), $resource, $resource)();
    };

    test('types as the pipeline with null added once, and the runtime sends that', function (string $php, string $type, array $sent) use ($runAsResource) {
        $analyzer = new ResourceAstAnalyzer(new ReflectionClass(PostResource::class), Post::class);
        $post = new Post;
        $post->setRelation('comments', new EloquentCollection([(new Comment)->forceFill(['id' => 1])]));

        expect($analyzer->resolve(receiverHandlerExpr($php))['type'])->toBe($type)
            ->and(json_decode((string) json_encode($runAsResource($php, $post)), true))->toBe($sent)
            ->and($runAsResource($php, null))->toBeNull();
    })->with([
        'map' => ['$this->resource?->comments->map(fn ($c) => $c->id)', 'number[] | null', [1]],
        'take' => ['$this->resource?->comments->take(1)', 'Comment[] | null', [['id' => 1]]],
    ]);

    test('keeps the bare answer where the read is not nullsafe', function (string $php, string $type) {
        $analyzer = new ResourceAstAnalyzer(new ReflectionClass(PostResource::class), Post::class);

        expect($analyzer->resolve(receiverHandlerExpr($php))['type'])->toBe($type);
    })->with([
        ['$this->resource->comments->map(fn ($c) => $c->id)', 'number[]'],
        ['$this->resource->comments->take(1)', 'Comment[]'],
    ]);

    test('adds no second null to a terminal that already carries one', function () {
        $analyzer = new ResourceAstAnalyzer(new ReflectionClass(PostResource::class), Post::class);

        expect($analyzer->resolve(receiverHandlerExpr('$this->resource?->comments->first()'))['type'])->toBe('Comment | null');
    });
});

describe('a relation collection\'s first(), last() and firstWhere() hold its element model', function () {
    /** Runs the PHP expression as a resource body would, around a post whose comments are loaded. */
    $runAsResource = function (string $php, EloquentCollection $comments): mixed {
        $post = new Post;
        $post->setRelation('comments', $comments);
        $resource = new class($post) extends JsonResource {};

        return Closure::bind(eval('return fn () => '.$php.';'), $resource, $resource)();
    };

    test('types a read on the element, and the runtime sends that', function (string $php, string $type) use ($runAsResource) {
        $analyzer = new ResourceAstAnalyzer(new ReflectionClass(ReceiverProbeResource::class), Post::class);
        $comment = (new Comment)->forceFill(['id' => 1, 'content' => 'Hi']);

        expect($analyzer->resolve(receiverHandlerExpr($php))['type'])->toBe($type)
            ->and($runAsResource($php, new EloquentCollection([$comment])))->toBe('Hi')
            ->and($runAsResource($php, new EloquentCollection))->toBeNull();
    })->with([
        'first()' => ['$this->comments->first()?->content', 'string | null'],
        'last()' => ['$this->comments->last()?->content', 'string | null'],
        'firstWhere()' => ['$this->comments->firstWhere(\'id\', 1)?->content', 'string | null'],
        'a nullsafe read of the collection' => ['$this->comments?->first()?->content', 'string | null'],
        'through $this->resource' => ['$this->resource->comments->first()?->content', 'string | null'],
        'find(), whose return depends on its argument' => ['$this->comments->find(1)?->content', 'unknown'],
    ]);

    test('reads the element through a plain arrow, which fails where no item is there', function () use ($runAsResource) {
        $analyzer = new ResourceAstAnalyzer(new ReflectionClass(ReceiverProbeResource::class), Post::class);
        $php = '$this->comments->first()->content';

        expect($analyzer->resolve(receiverHandlerExpr($php))['type'])->toBe('string')
            ->and($runAsResource($php, new EloquentCollection([(new Comment)->forceFill(['content' => 'Hi'])])))->toBe('Hi')
            ->and(fn () => $runAsResource($php, new EloquentCollection))->toThrow(ErrorException::class);
    });

    test('declines first() or last() given a default, which Laravel returns where no item matches', function (string $php) {
        $analyzer = new ResourceAstAnalyzer(new ReflectionClass(ReceiverProbeResource::class), Post::class);

        expect($analyzer->resolve(receiverHandlerExpr($php))['type'])->toBe('unknown');
    })->with([
        '$this->comments->first(null, \'none\')?->content',
        '$this->comments->last(default: \'none\')?->content',
    ]);
});

/**
 * What the final `return` of a body written with fully-qualified names publishes on a Post resource, read with the
 * locals the body binds.
 */
function collectionFactsType(string $body): string
{
    $scope = new AnalysisScope(new ReflectionClass(ReceiverProbeResource::class), Post::class);
    /** @var list<Stmt> $stmts */
    $stmts = new AstParser()->parseSource('<?php '.$body);

    $host = new class
    {
        use CollectsLocalVarBindings;

        /** @param  list<Stmt>  $stmts */
        public function run(array $stmts, AnalysisScope $scope): void
        {
            $this->collectLocalVarBindings($stmts, $scope);
        }
    };

    $host->run($stmts, $scope);
    $returned = $stmts[array_key_last($stmts)];

    if (! $returned instanceof Return_ || $returned->expr === null) {
        throw new LogicException('The body must end in a return.');
    }

    return new ResourceAstAnalyzer(new ReflectionClass(ReceiverProbeResource::class), Post::class, 'toArray', null, $scope)
        ->resolve($returned->expr)['type'];
}

describe('a collection step that keeps its items', function () {
    /** Runs a resource body over a saved post whose comments `beta` and `alpha` are loaded, as Laravel would. */
    $run = function (string $body): mixed {
        $user = User::create(['name' => 'Ada', 'email' => 'ada@example.com', 'password' => 'secret']);
        $post = Post::create(['title' => 'First', 'content' => 'Body', 'user_id' => $user->id]);

        foreach (['beta', 'alpha'] as $content) {
            Comment::create(['content' => $content, 'post_id' => $post->id, 'user_id' => $user->id]);
        }

        $resource = new class(Post::with('comments')->findOrFail($post->id)) extends JsonResource {};

        return Closure::bind(eval('return function () { '.$body.' };'), $resource, $resource)();
    };

    /** Each comment's content under the key it was sent under, which tells a list from an object. */
    $contents = fn (array $comments): array => array_map(fn (Comment $comment): string => $comment->content, $comments);
    $keys = fn (array $items): array => array_keys($items);
    $same = fn (mixed $value): mixed => $value;
    $query = '\Workbench\App\Models\Comment::query()->get()';

    test('keeps a collection\'s element and keys through item-keeping steps, a query, Model::all() and a @var', function (string $body, string $type, Closure $read, mixed $sent) use ($run) {
        expect(collectionFactsType($body))->toBe($type)
            ->and($read($run($body)))->toBe($sent);
    })->with([
        'sortBy()->first()' => [
            'return $this->comments->sortBy(\'content\')->first()?->content;',
            'string | null',
            $same,
            'alpha',
        ],
        'keyBy()->all(), whose string keys json_encode() writes as an object' => [
            'return $this->comments->keyBy(\'content\')->all();',
            'Comment[] | Record<string, Comment>',
            $contents,
            ['beta' => 'beta', 'alpha' => 'alpha'],
        ],
        'Model::all()->toResourceCollection()' => [
            'return \Workbench\App\Models\Post::all()->toResourceCollection();',
            'PostResource[]',
            fn (PostCollection $posts): array => $posts->collection->map(fn (object $item): string => $item::class)->all(),
            [PostResource::class],
        ],
        'a query\'s get()->first()' => [
            'return '.$query.'->first()?->content;',
            'string | null',
            $same,
            'beta',
        ],
        'a @var collection over a value that names no class' => [
            '/** @var \Illuminate\Database\Eloquent\Collection<int, \Workbench\App\Models\Comment> $vague */ '
                .'$vague = $this->resource->getRelationValue(\'comments\'); return $vague->first()?->content;',
            'string | null',
            $same,
            'beta',
        ],
        'a @var collection that admits null' => [
            '/** @var \Illuminate\Database\Eloquent\Collection<int, \Workbench\App\Models\Comment>|null $maybe */ '
                .'$maybe = $this->resource->getRelationValue(\'comments\'); return $maybe?->first()?->content;',
            'string | null',
            $same,
            'beta',
        ],
        'a @var collection keyed by string' => [
            '/** @var \Illuminate\Database\Eloquent\Collection<string, \Workbench\App\Models\Comment> $byName */ '
                .'$byName = $this->resource->getRelationValue(\'comments\')->keyBy(\'content\'); return $byName->all();',
            'Comment[] | Record<string, Comment>',
            $contents,
            ['beta' => 'beta', 'alpha' => 'alpha'],
        ],
        'values()->all(), a list' => [
            'return $this->comments->values()->all();',
            'Comment[]',
            $contents,
            ['beta', 'alpha'],
        ],
        'keyBy()->values()->all(), a list again' => [
            'return $this->comments->keyBy(\'content\')->values()->all();',
            'Comment[]',
            $contents,
            ['beta', 'alpha'],
        ],
        'a ternary of a keyed and a listed collection' => [
            'return ($this->resource->id > 0 ? $this->comments->keyBy(\'content\') : $this->comments)->all();',
            'Comment[] | Record<string, Comment>',
            $contents,
            ['beta' => 'beta', 'alpha' => 'alpha'],
        ],
        'take() of a literal count on a list, a list' => [
            'return '.$query.'->take(1)->all();',
            'Comment[]',
            $contents,
            ['beta'],
        ],
        'take() of a literal count, which keeps the keys a sortBy() left' => [
            'return '.$query.'->sortBy(\'content\')->take(1)->all();',
            'Comment[] | Record<string, Comment>',
            $contents,
            [1 => 'alpha'],
        ],
        'take() of a count a variable holds, which may take from the end' => [
            '$n = -1; return '.$query.'->take($n)->all();',
            'Comment[] | Record<string, Comment>',
            $contents,
            [1 => 'alpha'],
        ],
        'sortKeys() with no arguments on a list, a list' => [
            'return '.$query.'->sortKeys()->all();',
            'Comment[]',
            $contents,
            ['beta', 'alpha'],
        ],
        'sortKeysUsing(), whose callback may reverse a list' => [
            'return '.$query.'->sortKeysUsing(fn ($a, $b) => $b <=> $a)->all();',
            'Comment[] | Record<string, Comment>',
            $contents,
            [1 => 'alpha', 0 => 'beta'],
        ],
        'only() on an Eloquent collection, which re-indexes the models it keeps' => [
            'return '.$query.'->sortBy(\'content\')->only([1, 2])->all();',
            'Comment[]',
            $contents,
            ['alpha', 'beta'],
        ],
        'except(null) on an Eloquent collection, which keeps the keys a sortBy() left' => [
            'return '.$query.'->sortBy(\'content\')->except(null)->all();',
            'Comment[] | Record<string, Comment>',
            $contents,
            [1 => 'alpha', 0 => 'beta'],
        ],
        'unique() without a key on an Eloquent collection, which re-indexes' => [
            'return '.$query.'->sortBy(\'content\')->unique()->all();',
            'Comment[]',
            $contents,
            ['alpha', 'beta'],
        ],
        'unique() by a key, which keeps the keys a sortBy() left' => [
            'return '.$query.'->sortBy(\'content\')->unique(\'content\')->all();',
            'Comment[] | Record<string, Comment>',
            $contents,
            [1 => 'alpha', 0 => 'beta'],
        ],
        'diff() on an Eloquent collection, which re-indexes' => [
            'return '.$query.'->sortBy(\'content\')->diff([])->all();',
            'Comment[]',
            $contents,
            ['alpha', 'beta'],
        ],
        'unique() on a base collection, which keeps the keys' => [
            'return '.$query.'->sortBy(\'content\')->toBase()->unique()->all();',
            'Comment[] | Record<string, Comment>',
            $contents,
            [1 => 'alpha', 0 => 'beta'],
        ],
        'modelKeys() of a keyed collection' => [
            'return '.$query.'->sortBy(\'content\')->modelKeys();',
            'number[] | Record<string, number>',
            $keys,
            [1, 0],
        ],
        'toArray() of a keyed collection' => [
            'return '.$query.'->sortBy(\'content\')->toArray();',
            'Record<string, unknown>[] | Record<string, Record<string, unknown>>',
            $keys,
            [1, 0],
        ],
    ]);

    test('declines a step the receiver class does not run, which throws', function (string $body) use ($run) {
        expect(collectionFactsType($body))->toBe('unknown')
            ->and(fn () => $run($body))->toThrow(BadMethodCallException::class);
    })->with([
        'modelKeys() once toBase() hands the models over in a base collection' => ['return $this->comments->toBase()->modelKeys();'],
        'load() on a base collection' => ['return $this->comments->toBase()->load(\'user\')->first()?->content;'],
    ]);

    test('names no element for makeMany(), whose models no row backs yet', function () use ($run) {
        $body = 'return $this->resource->comments()->makeMany([[\'content\' => \'new\']])->first()->getKey();';

        expect(collectionFactsType($body))->toBe('unknown')
            ->and($run($body))->toBeNull();
    });
});
