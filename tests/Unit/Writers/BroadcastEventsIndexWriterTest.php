<?php

declare(strict_types=1);

use AbeTwoThree\LaravelTsPublish\Generators\BroadcastEventGenerator;
use AbeTwoThree\LaravelTsPublish\Transformers\BroadcastEventTransformer;
use AbeTwoThree\LaravelTsPublish\Writers\BroadcastEventsIndexWriter;
use AbeTwoThree\LaravelTsPublish\Writers\BroadcastEventWriter;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Collection;
use Workbench\App\Events\OrderShipped;
use Workbench\App\Events\ServerCreated;
use Workbench\App\Events\TeamMessageSent;
use Workbench\App\Events\UserNotification;
use Workbench\App\Events\UserSynced as AppUserSynced;
use Workbench\Crm\Events\UserSynced as CrmUserSynced;

describe('BroadcastEventsIndexWriter', function () {
    beforeEach(function () {
        config()->set('ts-publish.output_to_files', false);
        config()->set('ts-publish.namespace_strip_prefix', '');
        config()->set('ts-publish.broadcast_events.transformer_class', BroadcastEventTransformer::class);
        config()->set('ts-publish.broadcast_events.writer_class', BroadcastEventWriter::class);
        config()->set('ts-publish.broadcast_events.template', 'laravel-ts-publish::broadcast-event');
        config()->set('ts-publish.broadcast_events.generator_class', BroadcastEventGenerator::class);
        config()->set('ts-publish.broadcast_events.index_writer_class', BroadcastEventsIndexWriter::class);
        config()->set('ts-publish.broadcast_events.index_template', 'laravel-ts-publish::broadcast-events-index');
        config()->set('ts-publish.broadcast_events.output_path', null);
        config()->set('ts-publish.broadcast_events.index_filename', 'broadcast-events.ts');
    });

    function buildGenerators(array $classes): Collection
    {
        return collect($classes)->map(
            fn ($class) => app(BroadcastEventGenerator::class, ['findable' => $class])
        );
    }

    it('renders the BroadcastEvent union type', function () {
        $generators = buildGenerators([OrderShipped::class, ServerCreated::class]);
        $writer = app(BroadcastEventsIndexWriter::class);

        $content = $writer->write($generators);

        expect($content)->toContain('export type BroadcastEvent');
        expect($content)->toContain('.Workbench.App.Events.OrderShipped');
        expect($content)->toContain('server.created');
    });

    it('renders the flat BroadcastEvents const keyed by short class name', function () {
        $generators = buildGenerators([OrderShipped::class, UserNotification::class]);
        $writer = app(BroadcastEventsIndexWriter::class);

        $content = $writer->write($generators);

        expect($content)->toContain('export const BroadcastEvents = Object.freeze({');
        expect($content)->toContain('OrderShipped:');
        expect($content)->toContain('UserNotification:');
        expect($content)->toContain('} as const)');
    });

    it('uses the broadcastAs() value in the const for ServerCreated', function () {
        $generators = buildGenerators([ServerCreated::class]);
        $writer = app(BroadcastEventsIndexWriter::class);

        $content = $writer->write($generators);

        expect($content)->toContain("'server.created'");
        expect($content)->toContain('ServerCreated:');
    });

    it('includes import statements for each event interface', function () {
        $generators = buildGenerators([OrderShipped::class, TeamMessageSent::class]);
        $writer = app(BroadcastEventsIndexWriter::class);

        $content = $writer->write($generators);

        expect($content)->toContain('import type');
        expect($content)->toContain('OrderShipped');
        expect($content)->toContain('TeamMessageSent');
    });

    it('includes re-export type aliases', function () {
        $generators = buildGenerators([OrderShipped::class]);
        $writer = app(BroadcastEventsIndexWriter::class);

        $content = $writer->write($generators);

        expect($content)->toContain(<<<'TS'
export type {
    OrderShipped
};
TS);
    });

    it('returns an empty export when no generators provided', function () {
        $writer = app(BroadcastEventsIndexWriter::class);

        $content = $writer->write(collect());

        expect($content)->toBe("export {};\n");
    });

    it('writes to disk when output_to_files is true', function () {
        $tmpDir = sys_get_temp_dir().'/ts-publish-events-index-test-'.uniqid();
        config()->set('ts-publish.output_to_files', true);
        config()->set('ts-publish.output_directory', $tmpDir);

        $generators = buildGenerators([OrderShipped::class]);
        $writer = app(BroadcastEventsIndexWriter::class);
        $writer->write($generators);

        $expectedPath = $tmpDir.'/broadcast-events.ts';
        expect(file_exists($expectedPath))->toBeTrue();

        app(Filesystem::class)->deleteDirectory($tmpDir);
    });

    it('aliases two events that share the same short class name', function () {
        $generators = buildGenerators([AppUserSynced::class, CrmUserSynced::class]);
        $writer = app(BroadcastEventsIndexWriter::class);

        $content = $writer->write($generators);

        // Both should be imported under namespace-prefix aliases
        expect($content)->toContain('import type { UserSynced as AppUserSynced }');
        expect($content)->toContain('import type { UserSynced as CrmUserSynced }');

        // The const keys and re-export names should use the aliases, not the bare name
        expect($content)->toContain('AppUserSynced:');
        expect($content)->toContain('CrmUserSynced:');
        // No standalone bare UserSynced: key (the substring would also appear in AppUserSynced: and CrmUserSynced:)
        expect($content)->not->toMatch('/^\s+UserSynced:\s/m');

        // Re-export block should export the aliases
        expect($content)->toContain('AppUserSynced');
        expect($content)->toContain('CrmUserSynced');
    });
});
