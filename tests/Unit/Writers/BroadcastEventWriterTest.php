<?php

declare(strict_types=1);

use AbeTwoThree\LaravelTsPublish\Generators\BroadcastEventGenerator;
use AbeTwoThree\LaravelTsPublish\Transformers\BroadcastEventTransformer;
use AbeTwoThree\LaravelTsPublish\Writers\BroadcastEventWriter;
use Illuminate\Filesystem\Filesystem;
use Workbench\App\Events\OrderShipped;
use Workbench\App\Events\ServerCreated;
use Workbench\App\Events\TeamMessageSent;

describe('BroadcastEventWriter', function () {
    beforeEach(function () {
        config()->set('ts-publish.output_to_files', false);
        config()->set('ts-publish.namespace_strip_prefix', '');
        config()->set('ts-publish.broadcast_events.transformer_class', BroadcastEventTransformer::class);
        config()->set('ts-publish.broadcast_events.writer_class', BroadcastEventWriter::class);
        config()->set('ts-publish.broadcast_events.template', 'laravel-ts-publish::broadcast-event');
    });

    it('generates a TypeScript interface for OrderShipped', function () {
        $transformer = app(BroadcastEventTransformer::class, ['findable' => OrderShipped::class]);
        $writer = app(BroadcastEventWriter::class);

        $content = $writer->write($transformer);

        expect($content)->toContain('export interface OrderShipped');
        expect($content)->toContain('orderId: number');
        expect($content)->toContain('trackingNumber: `${string}-${string}-${string}`');
        expect($content)->toContain('carrier: string');
        expect($content)->toContain('metadata?: Record<string, unknown>');
    });

    it('includes a @see jsdoc for the FQCN', function () {
        $transformer = app(BroadcastEventTransformer::class, ['findable' => OrderShipped::class]);
        $writer = app(BroadcastEventWriter::class);

        $content = $writer->write($transformer);

        expect($content)->toContain('@see');
        expect($content)->toContain('OrderShipped');
    });

    it('generates a TypeScript interface for TeamMessageSent using broadcastWith()', function () {
        $transformer = app(BroadcastEventTransformer::class, ['findable' => TeamMessageSent::class]);
        $writer = app(BroadcastEventWriter::class);

        $content = $writer->write($transformer);

        expect($content)->toContain('export interface TeamMessageSent');
        expect($content)->toContain('teamId: number');
        expect($content)->toContain('content: string');
        expect($content)->not->toContain('senderToken');
    });

    it('generates a TypeScript interface for ServerCreated', function () {
        $transformer = app(BroadcastEventTransformer::class, ['findable' => ServerCreated::class]);
        $writer = app(BroadcastEventWriter::class);

        $content = $writer->write($transformer);

        expect($content)->toContain('export interface ServerCreated');
        expect($content)->toContain('serverId: number');
        expect($content)->toContain('serverName: string');
    });

    it('writes to disk when output_to_files is true', function () {
        $tmpDir = sys_get_temp_dir().'/ts-publish-events-test-'.uniqid();
        config()->set('ts-publish.output_to_files', true);
        config()->set('ts-publish.output_directory', $tmpDir);

        $transformer = app(BroadcastEventTransformer::class, ['findable' => OrderShipped::class]);
        $writer = app(BroadcastEventWriter::class);

        $writer->write($transformer);

        $expectedPath = $tmpDir.'/'.$transformer->namespacePath.'/OrderShipped.ts';
        expect(file_exists($expectedPath))->toBeTrue();

        app(Filesystem::class)->deleteDirectory($tmpDir);
    });

    it('can be driven from BroadcastEventGenerator', function () {
        config()->set('ts-publish.broadcast_events.generator_class', BroadcastEventGenerator::class);

        $generator = app(BroadcastEventGenerator::class, ['findable' => OrderShipped::class]);

        expect($generator->content)->toContain('export interface OrderShipped');
        expect($generator->filename())->toBe('OrderShipped');
    });
});
