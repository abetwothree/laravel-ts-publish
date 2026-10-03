<?php

declare(strict_types=1);

use AbeTwoThree\LaravelTsPublish\Runners\Runner;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\LiteralKindPost;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\ShadowedAccessorPost;
use AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures\LiteralKindPostEvent;
use AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures\LiteralKindPostResource;
use AbeTwoThree\LaravelTsPublish\Writers\GlobalsWriter;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\View;
use Illuminate\View\View as ViewInstance;
use Workbench\App\Enums\Status;
use Workbench\App\Models\Comment;
use Workbench\App\Models\Image;
use Workbench\App\Models\Post;
use Workbench\App\Models\User;

test('writes globals content when enabled', function () {
    config()->set('ts-publish.globals.enabled', true);
    config()->set('ts-publish.output_to_files', false);

    $runner = resolve(Runner::class);
    $runner->run();

    $writer = new GlobalsWriter(new Filesystem);
    $content = $writer->write($runner);

    expect($content)
        ->toContain('declare global')
        ->toContain('export namespace workbench.app.models')
        ->toContain('export namespace workbench.app.enums');
});

test('a multi-word namespace segment is declared and referenced as an identifier', function () {
    config()->set('ts-publish.globals.enabled', true);
    config()->set('ts-publish.output_to_files', false);

    $runner = resolve(Runner::class);
    $runner->run();

    $content = (new GlobalsWriter(new Filesystem))->write($runner);

    expect($content)
        ->toContain('export namespace workbench.app.http.resources.reportCards {')
        ->toContain('summary: workbench.app.http.resources.reportCards.SummaryCardResource;')
        ->not->toContain('report-cards');
});

test('globals content emits the extends clause for form requests and events', function () {
    config()->set('ts-publish.globals.enabled', true);
    config()->set('ts-publish.output_to_files', false);

    $runner = resolve(Runner::class);
    $runner->run();

    $content = (new GlobalsWriter(new Filesystem))->write($runner);

    expect($content)->toContain('interface StringRulesRequest extends FormRequestBase')
        ->and($content)->toContain('interface ServerCreated extends BroadcastableEvent');
});

test('returns empty string when globals output is disabled', function () {
    config()->set('ts-publish.globals.enabled', false);
    config()->set('ts-publish.output_to_files', false);

    $runner = resolve(Runner::class);
    $runner->run();

    $writer = new GlobalsWriter(new Filesystem);
    $content = $writer->write($runner);

    expect($content)->toBe('');
});

test('writes globals file to disk when output_to_files is enabled', function () {
    config()->set('ts-publish.globals.enabled', true);

    $filesystem = Mockery::mock(Filesystem::class);
    $filesystem->shouldReceive('exists')->once()->andReturn(false);
    $filesystem->shouldReceive('put')->once()
        ->withArgs(function (string $path, string $content) {
            return str_contains($path, 'laravel-ts-global') && str_contains($content, 'declare global');
        });

    config()->set('ts-publish.output_to_files', true);

    $runner = resolve(Runner::class);
    $runner->shouldPublishModelMetadata = false;
    $runner->run();

    $writer = new GlobalsWriter($filesystem);
    $writer->write($runner);
});

test('globals content contains model interfaces', function () {
    config()->set('ts-publish.globals.enabled', true);
    config()->set('ts-publish.output_to_files', false);

    $runner = resolve(Runner::class);
    $runner->run();

    $writer = new GlobalsWriter(new Filesystem);
    $content = $writer->write($runner);

    expect($content)
        ->toContain('export interface User')
        ->toContain('id: number')
        ->toContain('name: string');
});

test('globals content contains enum interfaces', function () {
    config()->set('ts-publish.globals.enabled', true);
    config()->set('ts-publish.output_to_files', false);

    $runner = resolve(Runner::class);
    $runner->run();

    $writer = new GlobalsWriter(new Filesystem);
    $content = $writer->write($runner);

    expect($content)
        ->toContain('export interface Status')
        ->toContain('Draft')
        ->toContain('Published');
});

test('globals content does not contain AsEnum<typeof ...> (typeof namespace member is illegal in declare global)', function () {
    config()->set('ts-publish.globals.enabled', true);
    config()->set('ts-publish.output_to_files', false);

    $runner = resolve(Runner::class);
    $runner->run();

    $writer = new GlobalsWriter(new Filesystem);
    $content = $writer->write($runner);

    // AsEnum<typeof namespace.Member> is illegal in declare global {} — must be absent
    expect($content)->not->toContain('AsEnum<typeof');

    // Enum resource types should appear as qualified type aliases instead
    expect($content)->toContain('enums.StatusType');
});

test('globals content does not contain AsEnum<typeof ...> with namespace_strip_prefix', function () {
    config()->set('ts-publish.globals.enabled', true);
    config()->set('ts-publish.output_to_files', false);
    config()->set('ts-publish.namespace_strip_prefix', 'Workbench\\');

    $runner = resolve(Runner::class);
    $runner->run();

    $writer = new GlobalsWriter(new Filesystem);
    $content = $writer->write($runner);

    // AsEnum<typeof namespace.Member> is illegal in declare global {} — must be absent
    expect($content)->not->toContain('AsEnum<typeof');

    // Enum resource types should appear as namespace-qualified type aliases instead
    expect($content)->toContain('enums.StatusType');
});

test('globals content resolves aliased types to namespace-qualified names', function () {
    config()->set('ts-publish.globals.enabled', true);
    config()->set('ts-publish.output_to_files', false);
    config()->set('ts-publish.namespace_strip_prefix', 'Workbench\\');

    $runner = resolve(Runner::class);
    $runner->run();

    $writer = new GlobalsWriter(new Filesystem);
    $content = $writer->write($runner);

    // Raw per-file import aliases must NOT appear literally in the modular globals file
    expect($content)
        ->not->toContain('ManagerUser')
        ->not->toContain('CrmUser')
        ->not->toContain('WorkbenchStatusType')
        ->not->toContain('CrmStatusType');

    // Cross-namespace relations must be fully qualified with their source namespace.
    // manager points to App\Models\User — same namespace as Warehouse → stays bare.
    // primary_contact and secondary_contact point to Crm\Models\User → qualified.
    expect($content)
        ->toContain('manager: User | null')
        ->toContain('primary_contact: crm.models.User | null')
        ->toContain('secondary_contact: crm.models.User | null');

    // Enum column/mutator types must use the correct namespace-qualified type aliases
    expect($content)
        ->toContain('status: app.enums.StatusType | null')
        ->toContain('current_crm_status: crm.enums.StatusType | null');

    // MorphTo union with same-basename models must not produce duplicate qualified names.
    // Image is in app.models, so app.models.User stays bare; crm.models.User is qualified.
    expect($content)
        ->toContain('imageable: Post | Product | User | crm.models.User')
        ->not->toContain('imageable: Post | Product | crm.models.User | crm.models.User');

    // reviewable's docblock generic is Crm-first (CrmUser|User), the opposite of imageable's
    // reverse-map order, so the qualified arm must lead: proves per-occurrence order survives.
    expect($content)
        ->toContain('reviewable: crm.models.User | User | null')
        ->not->toContain('reviewable: User | crm.models.User | null');

    // probe_nested.first is an inline array member reading a multi-FQCN accessor (CrmUser|User): both
    // arms must keep their own namespace, not collapse to the same 'app.models.User' and lose the CRM arm.
    expect($content)
        ->toContain('first: crm.models.User | app.models.User | null')
        ->toContain('second: app.models.User | null')
        ->not->toContain('first: app.models.User | app.models.User | null');
});

test('a name two namespaces publish is qualified to the class the file itself imports', function () {
    config()->set('ts-publish.globals.enabled', true);
    config()->set('ts-publish.output_to_files', false);
    config()->set('ts-publish.namespace_strip_prefix', 'Workbench\\');

    $runner = resolve(Runner::class);
    $runner->run();

    $content = (new GlobalsWriter(new Filesystem))->write($runner);

    expect($content)
        // ServiceDesk imports only Crm's User, so its file never aliases the name; app.models owns a User too.
        ->not->toContain('crm_agent: User | null;')
        ->toContain('crm_agent: crm.models.User | null;')
        // Crm's User model casts to Crm's Status, though app.enums is the first namespace to own a StatusType.
        ->toContain(<<<'TYPESCRIPT'
        export interface User {
            // Columns
            id: number;
            name: string;
            email: string;
            company: string | null;
            status: crm.enums.StatusType;
TYPESCRIPT)
        // A resource reads the Address model, though its own namespace owns a resource named Address.
        ->not->toContain('primaryAddress: Address | null;')
        ->toContain('primaryAddress: app.models.Address | null;');
});

test('the globals file qualifies every name the same way whatever order the classes were collected in', function () {
    config()->set('ts-publish.globals.enabled', true);
    config()->set('ts-publish.output_to_files', false);

    $runner = resolve(Runner::class);
    $runner->run();

    $writer = new GlobalsWriter(new Filesystem);
    $sortedLines = function () use ($writer, $runner): array {
        $lines = explode("\n", $writer->write($runner));
        sort($lines);

        return $lines;
    };

    $collected = $sortedLines();

    foreach (['enumGenerators', 'modelGenerators', 'resourceGenerators', 'formRequestGenerators', 'broadcastEventGenerators'] as $phase) {
        (new ReflectionProperty($runner, $phase))->setValue($runner, $runner->{$phase}->reverse()->values());
    }

    expect($sortedLines())->toBe($collected);
});

test('globals resources section emits export type for flat collections', function () {
    config()->set('ts-publish.globals.enabled', true);
    config()->set('ts-publish.output_to_files', false);

    $runner = resolve(Runner::class);
    $runner->run();

    $writer = new GlobalsWriter(new Filesystem);
    $content = $writer->write($runner);

    expect($content)
        ->toContain('export type PostFlatCollection =')
        ->not->toContain('export interface PostFlatCollection {');
})->skip(fn () => ! version_compare(app()->version(), '13', '>='));

test('globals content imports a form request custom type instead of leaving the name unresolved', function () {
    config()->set('ts-publish.globals.enabled', true);
    config()->set('ts-publish.output_to_files', false);

    $runner = resolve(Runner::class);
    $runner->run();

    $writer = new GlobalsWriter(new Filesystem);
    $content = $writer->write($runner);

    // UpdatePostRequest's #[TsCasts] names PostAttributes from '@js/types/posts'; the modular
    // flavor imports it, so the globals flavor must too or the bare name resolves to nothing.
    expect($content)
        ->toContain("import type { PostAttributes } from '@js/types/posts';")
        ->toContain('attributes?: PostAttributes');
});

test('globals content keeps a string literal type as written, though it spells a published class name', function () {
    config()->set('ts-publish.globals.enabled', true);
    config()->set('ts-publish.output_to_files', false);
    config()->set('ts-publish.namespace_strip_prefix', 'Workbench\\');
    config()->set('ts-publish.enums.included', [Status::class]);
    config()->set('ts-publish.models.additional_directories', [LiteralKindPost::class]);
    config()->set('ts-publish.models.included', [Comment::class, Image::class, Post::class, User::class, LiteralKindPost::class]);
    config()->set('ts-publish.resources.additional_directories', [LiteralKindPostResource::class]);
    config()->set('ts-publish.resources.included', [LiteralKindPostResource::class]);
    config()->set('ts-publish.broadcast_events.additional_directories', [LiteralKindPostEvent::class]);
    config()->set('ts-publish.broadcast_events.included', [LiteralKindPostEvent::class]);

    $runner = resolve(Runner::class);
    $runner->run();

    $content = (new GlobalsWriter(new Filesystem))->write($runner);

    // The runtime value is the string 'Post', which a type rewritten to 'app.models.Post' rejects.
    expect($content)
        ->toContain("metadata: { kind: 'Post' | 'User'; label: string } | null;")
        ->toContain("kind: 'User' | 'Comment';")
        ->toContain("status: 'Status';")
        ->toContain("note: 'User\\'s Post';")
        ->toContain("spread_kind?: 'Post' | 'Image';")
        ->toContain("kind: 'Comment' | 'Post';")
        ->not->toContain("'app.");
});

describe('the globals file with keys an attribute and a relation both publish', function () {
    beforeEach(function () {
        config()->set('ts-publish.globals.enabled', true);
        config()->set('ts-publish.output_to_files', false);
    });

    test('a column and an append a relation shares appear once, as the relation, and import nothing', function () {
        $runner = resolve(Runner::class);
        $runner->run();

        $content = (new GlobalsWriter(new Filesystem))->write($runner);

        expect($content)
            ->toContain(<<<'TYPESCRIPT'
        export interface Parcel {
            // Columns
            id: number;
            handler_id: number;
            sender_id: number;
            manifest_id: number;
            priority: workbench.app.enums.PriorityType;
            created_at: string | null;
            updated_at: string | null;
            // Relations
            handler: User;
            handler_count: number;
            handler_exists: boolean;
            sender: User;
            sender_count: number;
            sender_exists: boolean;
            manifest: Order;
            manifest_count: number;
            manifest_exists: boolean;
        }
TYPESCRIPT)
            ->not->toContain('ParcelManifest');
    });

    test('a mutator a relation shares is declared once, as the relation', function () {
        config()->set('ts-publish.models.additional_directories', [
            ...config('ts-publish.models.additional_directories'),
            ShadowedAccessorPost::class,
        ]);

        $runner = resolve(Runner::class);
        $runner->run();

        $content = (new GlobalsWriter(new Filesystem))->write($runner);
        $start = (int) strpos($content, 'export interface ShadowedAccessorPost {');
        $interface = substr($content, $start, (int) strpos($content, "\n        }", $start) - $start);

        expect($interface)
            ->toContain(<<<'TYPESCRIPT'
            // Relations
            /** The post's author. */
            author: workbench.app.models.User;

TYPESCRIPT)
            ->not->toContain('// Mutators')
            ->and(substr_count($interface, ' author: '))->toBe(1);
    });
});

test('the globals template qualifies each name through its own transformer\'s map, not the merged one', function () {
    $model = new class
    {
        public string $description = '';

        public string $modelName = 'Widget';

        public array $tsExtends = [];

        public array $relations = ['owner' => ['type' => 'RelationName', 'description' => '']];

        public function relationCountKeys(): array
        {
            return [];
        }

        public function relationExistsKeys(): array
        {
            return [];
        }

        public function combinedColumns(): array
        {
            return ['size' => ['type' => 'ColumnName', 'description' => '', 'optional' => false]];
        }

        public function combinedMutators(): array
        {
            return ['label' => ['type' => 'MutatorName', 'description' => '', 'optional' => false]];
        }

        public function combinedAppends(): array
        {
            return ['badge' => ['type' => 'AppendName', 'description' => '', 'optional' => false]];
        }

        public function globalTypeReferenceMap(): array
        {
            return [
                'ColumnName' => 'own.elsewhere.ColumnName',
                'MutatorName' => 'own.elsewhere.MutatorName',
                'AppendName' => 'own.elsewhere.AppendName',
                'RelationName' => 'own.elsewhere.RelationName',
            ];
        }
    };

    $collection = new class
    {
        public string $description = '';

        public ?string $typeAlias = 'AliasName[]';

        public string $resourceName = 'WidgetCollection';

        public function globalTypeReferenceMap(): array
        {
            return ['AliasName' => 'own.elsewhere.AliasName'];
        }
    };

    $resource = new class
    {
        public string $description = '';

        public ?string $typeAlias = null;

        public string $resourceName = 'WidgetResource';

        public array $tsExtends = [];

        public array $properties = ['widget' => ['type' => 'PropertyName', 'description' => '', 'optional' => false]];

        public function globalTypeReferenceMap(): array
        {
            return ['PropertyName' => 'own.elsewhere.PropertyName'];
        }

        public function globalEnumConstMap(): array
        {
            return [];
        }
    };

    $event = new class
    {
        public string $eventName = 'WidgetMoved';

        public array $tsExtends = [];

        public array $properties = ['moved' => ['type' => 'EventName', 'optional' => false]];

        public function globalTypeReferenceMap(): array
        {
            return ['EventName' => 'own.elsewhere.EventName'];
        }
    };

    $content = view('laravel-ts-publish::globals', [
        'globalTypesByNamespace' => [],
        'globalAliasMap' => [
            'ColumnName' => 'merged.elsewhere.ColumnName',
            'MutatorName' => 'merged.elsewhere.MutatorName',
            'AppendName' => 'merged.elsewhere.AppendName',
            'RelationName' => 'merged.elsewhere.RelationName',
            'AliasName' => 'merged.elsewhere.AliasName',
            'PropertyName' => 'merged.elsewhere.PropertyName',
            'EventName' => 'merged.elsewhere.EventName',
        ],
        'externalTypeImports' => [],
        'groupedModels' => collect(['stub.models' => collect([$model])]),
        'groupedEnums' => collect(),
        'groupedResources' => collect(['stub.resources' => collect([$collection, $resource])]),
        'groupedFormRequests' => collect(),
        'groupedBroadcastEvents' => collect(['stub.events' => collect([$event])]),
    ])->render();

    expect($content)
        ->toContain('size: own.elsewhere.ColumnName;')
        ->toContain('label: own.elsewhere.MutatorName;')
        ->toContain('badge: own.elsewhere.AppendName;')
        ->toContain('owner: own.elsewhere.RelationName;')
        ->toContain('export type WidgetCollection = own.elsewhere.AliasName[];')
        ->toContain('widget: own.elsewhere.PropertyName;')
        ->toContain('moved: own.elsewhere.EventName;')
        ->not->toContain('merged.');
});

test('the globals view still receives the merged alias map, which a published template may read', function () {
    config()->set('ts-publish.globals.enabled', true);
    config()->set('ts-publish.output_to_files', false);

    $viewData = [];
    View::composer('laravel-ts-publish::globals', function (ViewInstance $view) use (&$viewData): void {
        $viewData = $view->getData();
    });

    $runner = resolve(Runner::class);
    $runner->run();

    (new GlobalsWriter(new Filesystem))->write($runner);

    expect($viewData)->toHaveKey('globalAliasMap')
        ->and($viewData['globalAliasMap'])->toMatchArray(['CrmStatusType' => 'workbench.crm.enums.StatusType']);
});
