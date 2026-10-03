<?php

declare(strict_types=1);

use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\FullListsModelTransformer;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\ShadowedAccessorPost;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\ShadowedEnumParcel;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\SwappedRelationModelTransformer;
use AbeTwoThree\LaravelTsPublish\Transformers\ModelTransformer;
use AbeTwoThree\LaravelTsPublish\Writers\ModelWriter;
use Illuminate\Filesystem\Filesystem;
use Workbench\App\Models\Depot;
use Workbench\App\Models\Parcel;
use Workbench\App\Models\Post;
use Workbench\App\Models\Team;
use Workbench\App\Models\User;
use Workbench\App\Models\Warehouse;
use Workbench\Crm\Models\Deal;

test('writes model content from transformer', function () {
    $writer = new ModelWriter(new Filesystem);
    $transformer = new ModelTransformer(User::class);

    config()->set('ts-publish.output_to_files', false);

    $content = $writer->write($transformer);

    expect($content)
        ->toContain('export interface User')
        ->not->toContain('UserMorphClass')
        ->not->toContain('UserModelMetadata')
        ->toContain('id: number')
        ->toContain('name: string')
        ->toContain('email: string');
});

test('keeps model metadata out of model templates', function (string $template) {
    $writer = new ModelWriter(new Filesystem);
    $transformer = new ModelTransformer(User::class);

    config()->set('ts-publish.output_to_files', false);
    config()->set('ts-publish.models.template', $template);

    $content = $writer->write($transformer);

    expect($content)
        ->toContain(<<<'TYPESCRIPT'
/**
 * Application user account
 *
 * @see Workbench\App\Models\User
 */
export interface User
TYPESCRIPT)
        ->and($content)->not->toContain('UserMorphClass')
        ->and($content)->not->toContain('UserModelMetadata');
})->with([
    'full template' => 'laravel-ts-publish::model-full',
    'split template' => 'laravel-ts-publish::model-split',
]);

test('writes model file to disk when output_to_files is enabled', function () {
    $filesystem = Mockery::mock(Filesystem::class);
    $filesystem->shouldReceive('exists')->once()->andReturn(false);
    $filesystem->shouldReceive('put')->once()
        ->withArgs(function (string $path, string $content) {
            return str_contains($path, 'user.ts') && str_contains($content, 'export interface User');
        });

    $writer = new ModelWriter($filesystem);
    $transformer = new ModelTransformer(User::class);

    config()->set('ts-publish.output_to_files', true);

    $writer->write($transformer);
});

test('does not write model file to disk when output_to_files is disabled', function () {
    $filesystem = Mockery::mock(Filesystem::class);
    $filesystem->shouldNotReceive('exists');
    $filesystem->shouldNotReceive('put');

    $writer = new ModelWriter($filesystem);
    $transformer = new ModelTransformer(User::class);

    config()->set('ts-publish.output_to_files', false);

    $writer->write($transformer);
});

test('writes model relations interfaces', function () {
    $writer = new ModelWriter(new Filesystem);
    $transformer = new ModelTransformer(User::class);

    config()->set('ts-publish.output_to_files', false);

    $content = $writer->write($transformer);

    expect($content)
        ->toContain('export interface UserRelations');
});

test('writes model mutators interface', function () {
    $writer = new ModelWriter(new Filesystem);
    $transformer = new ModelTransformer(User::class);

    config()->set('ts-publish.output_to_files', false);

    $content = $writer->write($transformer);

    expect($content)->toContain('export interface UserMutators');
});

describe('ModelWriter Resource interface output', function () {
    test('generates Resource interface for Post model in model-full template', function () {
        $writer = new ModelWriter(new Filesystem);
        $transformer = new ModelTransformer(Post::class);

        config()->set('ts-publish.output_to_files', false);
        config()->set('ts-publish.models.template', 'laravel-ts-publish::model-full');

        $content = $writer->write($transformer);

        expect($content)
            ->toContain('export interface PostResource extends Omit<Post,')
            ->toContain('AsEnum<typeof Status>')
            ->toContain('AsEnum<typeof Visibility> | null')
            ->toContain('AsEnum<typeof Priority> | null');
    });

    test('generates Resource interface for Post model in model-split template', function () {
        $writer = new ModelWriter(new Filesystem);
        $transformer = new ModelTransformer(Post::class);

        config()->set('ts-publish.output_to_files', false);
        config()->set('ts-publish.models.template', 'laravel-ts-publish::model-split');

        $content = $writer->write($transformer);

        expect($content)
            ->toContain('export interface PostResource extends Omit<Post,')
            ->toContain('AsEnum<typeof Status>')
            ->toContain('AsEnum<typeof Visibility> | null')
            ->toContain('AsEnum<typeof Priority> | null');
    });

    test('uses typeof for non-type imports in Resource', function () {
        $writer = new ModelWriter(new Filesystem);
        $transformer = new ModelTransformer(Post::class);

        config()->set('ts-publish.output_to_files', false);
        config()->set('ts-publish.models.template', 'laravel-ts-publish::model-full');

        $content = $writer->write($transformer);

        expect($content)
            ->toContain('AsEnum<typeof Status>')
            ->toContain('AsEnum<typeof Visibility> | null')
            ->toContain('AsEnum<typeof Priority> | null');
    });

    test('does not generate Resource when enums_use_tolki_package is disabled', function () {
        $writer = new ModelWriter(new Filesystem);
        $transformer = new ModelTransformer(Post::class);

        config()->set('ts-publish.output_to_files', false);
        config()->set('ts-publish.enums.use_tolki_package', false);

        $content = $writer->write($transformer);

        expect($content)->not->toContain('Resource');
    });

    test('does not generate Resource for model with no enum casts', function () {
        $writer = new ModelWriter(new Filesystem);
        $transformer = new ModelTransformer(User::class);

        config()->set('ts-publish.output_to_files', false);

        $content = $writer->write($transformer);

        // User has enum casts (role, membership_level) so it WILL have Resource
        expect($content)->toContain('UserResource');
    });

    test('generates Resource with aliased const names for Deal model', function () {
        $writer = new ModelWriter(new Filesystem);
        config()->set('ts-publish.namespace_strip_prefix', 'Workbench\\');
        $transformer = new ModelTransformer(Deal::class);

        config()->set('ts-publish.output_to_files', false);
        config()->set('ts-publish.models.template', 'laravel-ts-publish::model-full');
        $content = $writer->write($transformer);

        expect($content)
            ->toContain('export interface DealResource')
            ->toContain('AsEnum<typeof EnumsStatus>')
            ->toContain('AsEnum<typeof CrmStatus>');
    });

    test('model-split Resource extends Omit of column interface only when only columns have enums', function () {
        $writer = new ModelWriter(new Filesystem);
        $transformer = new ModelTransformer(Post::class);

        config()->set('ts-publish.output_to_files', false);
        config()->set('ts-publish.models.template', 'laravel-ts-publish::model-split');

        $content = $writer->write($transformer);

        // Post has enum columns but no enum mutators
        // Should extend Omit<Post, ...> and PostMutators (no Omit), and PostRelations
        expect($content)
            ->toContain('Omit<Post,')
            ->not->toContain('Omit<PostMutators');
    });

    test('Omit keys include all enum column names', function () {
        $writer = new ModelWriter(new Filesystem);
        $transformer = new ModelTransformer(Post::class);

        config()->set('ts-publish.output_to_files', false);
        config()->set('ts-publish.models.template', 'laravel-ts-publish::model-full');

        $content = $writer->write($transformer);

        expect($content)
            ->toContain("'status'")
            ->toContain("'visibility'")
            ->toContain("'priority'");
    });

    test('imports AsEnum from @tolki/enum', function () {
        $writer = new ModelWriter(new Filesystem);
        $transformer = new ModelTransformer(Post::class);

        config()->set('ts-publish.output_to_files', false);

        $content = $writer->write($transformer);

        expect($content)->toContain("import { type AsEnum } from '@tolki/ts'");
    });

    test('imports enum const names as value imports and type names as type imports', function () {
        $writer = new ModelWriter(new Filesystem);
        $transformer = new ModelTransformer(Post::class);

        config()->set('ts-publish.output_to_files', false);

        $content = $writer->write($transformer);

        // Enum const names should be value imports (no "type" keyword)
        preg_match("/import \{ (.+) \} from '\.\.\/enums'/", $content, $valueMatches);
        $valueNames = array_map('trim', explode(',', $valueMatches[1]));

        expect($valueNames)
            ->toContain('Priority')
            ->toContain('Status')
            ->toContain('Visibility');

        // Enum type names should be type imports
        preg_match("/import type \{ (.+) \} from '\.\.\/enums'/", $content, $typeMatches);
        $typeNames = array_map('trim', explode(',', $typeMatches[1]));

        expect($typeNames)
            ->toContain('PriorityType')
            ->toContain('StatusType')
            ->toContain('VisibilityType');
    });
});

test('renders extends clause from TsExtends attribute in split template', function () {
    $writer = new ModelWriter(new Filesystem);
    $transformer = new ModelTransformer(Warehouse::class);

    config()->set('ts-publish.output_to_files', false);
    config()->set('ts-publish.models.template', 'laravel-ts-publish::model-split');

    $content = $writer->write($transformer);

    expect($content)
        ->toContain('export interface Warehouse extends HasTimestamps, Pick<Auditable, "created_by" | "updated_by">')
        ->toContain("import type { HasTimestamps } from '@/types/common'")
        ->toContain("import type { Auditable } from '@/types/audit'");
});

test('renders extends clause from TsExtends attribute in full template', function () {
    $writer = new ModelWriter(new Filesystem);
    $transformer = new ModelTransformer(Warehouse::class);

    config()->set('ts-publish.output_to_files', false);
    config()->set('ts-publish.models.template', 'laravel-ts-publish::model-full');

    $content = $writer->write($transformer);

    expect($content)
        ->toContain('export interface Warehouse extends HasTimestamps, Pick<Auditable, "created_by" | "updated_by">')
        ->toContain("import type { HasTimestamps } from '@/types/common'");
});

test('model without TsExtends renders plain interface', function () {
    $writer = new ModelWriter(new Filesystem);
    $transformer = new ModelTransformer(User::class);

    config()->set('ts-publish.output_to_files', false);
    config()->set('ts-publish.models.template', 'laravel-ts-publish::model-split');

    $content = $writer->write($transformer);

    // User interface should not have extends (no TsExtends attribute)
    expect($content)
        ->not->toContain('export interface User extends')
        ->toContain('export interface User');
});

test('renders an enum-collection column with its [] suffix in the resource variant', function () {
    $writer = new ModelWriter(new Filesystem);
    $transformer = new ModelTransformer(Team::class);

    config()->set('ts-publish.output_to_files', false);

    $content = $writer->write($transformer);

    expect($content)
        ->toContain('week_days: AsEnum<typeof WeekDays>[] | null;')
        ->not->toContain('week_days: AsEnum<typeof WeekDays> | null;');
});

describe('ModelWriter with keys an attribute and a relation both publish', function () {
    beforeEach(function () {
        config()->set('ts-publish.output_to_files', false);
    });

    test('the split template omits the shadowed column from the combined interface', function () {
        $content = (new ModelWriter(new Filesystem))->write(new ModelTransformer(Depot::class));

        expect($content)
            ->toContain("export interface DepotAll extends Omit<Depot, 'supervisor'>, DepotRelations {}")
            ->toContain('    supervisor: string | null;')
            ->toContain('    supervisor: User | null;')
            ->toContain('    orders_count: number | null;')
            ->not->toContain('orders_count: number;');
    });

    test('the split template omits a shadowed mutator from the combined interface', function () {
        $content = (new ModelWriter(new Filesystem))->write(new ModelTransformer(ShadowedAccessorPost::class));

        expect($content)->toContain(
            "export interface ShadowedAccessorPostAll extends ShadowedAccessorPost, Omit<ShadowedAccessorPostMutators, 'author'>, ShadowedAccessorPostRelations {}",
        );
    });

    test('the full template declares a shared key once, with the relation type', function () {
        config()->set('ts-publish.models.template', 'laravel-ts-publish::model-full');

        $content = (new ModelWriter(new Filesystem))->write(new ModelTransformer(Depot::class));

        expect(substr_count($content, '    supervisor: '))->toBe(1)
            ->and($content)->toContain('    supervisor: User | null;')
            ->and(substr_count($content, '    orders_count: '))->toBe(1)
            ->and($content)->toContain('    orders_count: number | null;');
    });

    test('the full template leaves a shared enum, appended enum and imported type to the relation', function () {
        config()->set('ts-publish.models.template', 'laravel-ts-publish::model-full');

        $content = (new ModelWriter(new Filesystem))->write(new ModelTransformer(Parcel::class));

        expect($content)
            ->toStartWith(<<<'TYPESCRIPT'
import { type AsEnum } from '@tolki/ts';

import { Priority } from '../enums';
import type { PriorityType } from '../enums';
import type { Order, User } from '.';

TYPESCRIPT)
            ->toContain("    handler: User;\n    sender: User;\n    manifest: Order;\n")
            ->toContain(<<<'TYPESCRIPT'
export interface ParcelResource extends Omit<Parcel, 'priority'>
{
    priority: AsEnum<typeof Priority>;
}
TYPESCRIPT)
            ->not->toContain('RoleType')
            ->not->toContain('ParcelManifest')
            ->not->toContain('// Mutators')
            ->and(substr_count($content, '    handler: '))->toBe(1)
            ->and(substr_count($content, '    sender: '))->toBe(1)
            ->and(substr_count($content, '    manifest: '))->toBe(1);
    });

    test('the full template gives no Resource and no AsEnum to a model whose every enum key is shared', function () {
        config()->set('ts-publish.models.template', 'laravel-ts-publish::model-full');

        $content = (new ModelWriter(new Filesystem))->write(new ModelTransformer(ShadowedEnumParcel::class));

        expect($content)
            ->toStartWith("import type { User } from '../../../../workbench/app/models';\n\n")
            ->toContain(<<<'TYPESCRIPT'
    // Relations
    /** The parcel's handler. */
    handler: User;
    /** The parcel's courier. */
    courier: User;
}
TYPESCRIPT)
            ->toContain("    handler_count: number;\n")
            ->toContain("    handler_exists: boolean;\n")
            ->not->toContain('ShadowedEnumParcelResource')
            ->not->toContain('AsEnum')
            ->not->toContain('Role')
            ->not->toContain('// Counts')
            ->not->toContain('// Exists');
    });

    test('the split template keeps a shared attribute and its imports, which both All interfaces omit', function () {
        $content = (new ModelWriter(new Filesystem))->write(new ModelTransformer(Parcel::class));

        expect($content)
            ->toStartWith(<<<'TYPESCRIPT'
import { type AsEnum } from '@tolki/ts';

import { Priority, Role } from '../enums';
import type { ParcelManifest } from '@js/types/manifest';
import type { PriorityType, RoleType } from '../enums';
import type { Order, User } from '.';

TYPESCRIPT)
            ->toContain("    handler: RoleType;\n")
            ->toContain("    manifest: ParcelManifest;\n")
            ->toContain("    sender: RoleType;\n")
            ->toContain("export interface ParcelResource extends Omit<Parcel, 'handler' | 'priority' | 'sender'>")
            ->toContain(<<<'TYPESCRIPT'
export interface ParcelAll extends Omit<Parcel, 'handler' | 'sender' | 'manifest'>, ParcelRelations {}

export interface ParcelAllResource extends Omit<ParcelResource, 'handler' | 'sender' | 'manifest'>, ParcelRelations {}
TYPESCRIPT);
    });

    test('the split template prints no count or exists heading for a relation whose keys accessors take', function () {
        $content = (new ModelWriter(new Filesystem))->write(new ModelTransformer(ShadowedEnumParcel::class));

        expect($content)
            ->toContain(<<<'TYPESCRIPT'
export interface ShadowedEnumParcelRelations
{
    // Relations
    /** The parcel's handler. */
    handler: User;
    /** The parcel's courier. */
    courier: User;
}
TYPESCRIPT)
            ->not->toContain('// Counts')
            ->not->toContain('// Exists');
    });
});

describe('ModelWriter with a project\'s own model transformer', function () {
    beforeEach(function () {
        config()->set('ts-publish.output_to_files', false);
    });

    test('the shared keys follow the relations a subclass adjusts after transform()', function () {
        $content = (new ModelWriter(new Filesystem))->write(new SwappedRelationModelTransformer(Depot::class));

        expect($content)->toBe(<<<'TYPESCRIPT'
import type { Order, User } from '.';

/**
 * Shares keys between attributes and relations: a `supervisor` column beside a `supervisor()` relation, and an
 * `orders_count` counter-cache column beside the `orders()` relation's own count key.
 *
 * @see Workbench\App\Models\Depot
 */
export interface Depot
{
    id: number;
    name: string;
    /** The user who runs the depot. */
    supervisor: string | null;
    supervisor_id: number | null;
    orders_count: number | null;
    created_at: string | null;
    updated_at: string | null;
}

export interface DepotRelations
{
    // Relations
    /** The orders the depot ships. */
    orders: Order[];
    latest_order: Order | null;
    // Counts
    latest_order_count: number;
    // Exists
    orders_exists: boolean;
    latest_order_exists: boolean;
}

export interface DepotAll extends Depot, DepotRelations {}

TYPESCRIPT);
    });

    test('a DTO built from the full lists alone renders every attribute, and a count and exists key per relation', function () {
        config()->set('ts-publish.models.template', 'laravel-ts-publish::model-full');

        $content = (new ModelWriter(new Filesystem))->write(new FullListsModelTransformer(Depot::class));

        expect($content)->toBe(<<<'TYPESCRIPT'
import type { Order, User } from '.';

/**
 * Shares keys between attributes and relations: a `supervisor` column beside a `supervisor()` relation, and an
 * `orders_count` counter-cache column beside the `orders()` relation's own count key.
 *
 * @see Workbench\App\Models\Depot
 */
export interface Depot
{
    // Columns
    id: number;
    name: string;
    /** The user who runs the depot. */
    supervisor: string | null;
    supervisor_id: number | null;
    orders_count: number | null;
    created_at: string | null;
    updated_at: string | null;
    // Relations
    /** The user who runs the depot. */
    supervisor: User | null;
    /** The orders the depot ships. */
    orders: Order[];
    // Counts
    supervisor_count: number;
    orders_count: number;
    // Exists
    supervisor_exists: boolean;
    orders_exists: boolean;
}

TYPESCRIPT);
    });

    test('a DTO built from the full lists alone omits no key from the split template\'s All interface', function () {
        $content = (new ModelWriter(new Filesystem))->write(new FullListsModelTransformer(Depot::class));

        expect($content)->toEndWith(<<<'TYPESCRIPT'
export interface DepotRelations
{
    // Relations
    /** The user who runs the depot. */
    supervisor: User | null;
    /** The orders the depot ships. */
    orders: Order[];
    // Counts
    supervisor_count: number;
    orders_count: number;
    // Exists
    supervisor_exists: boolean;
    orders_exists: boolean;
}

export interface DepotAll extends Depot, DepotRelations {}

TYPESCRIPT);
    });
});
