<?php

declare(strict_types=1);

use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\PinnedPriorityResource;
use AbeTwoThree\LaravelTsPublish\Transformers\ResourceTransformer;
use AbeTwoThree\LaravelTsPublish\Writers\ResourceWriter;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Http\Resources\Discriminated\PinnedPostResource;
use Workbench\App\Http\Resources\Discriminated\PostPriorityResource;

/**
 * The type a resource's file declares, as the writer renders it.
 *
 * @param  class-string<JsonResource>  $resource
 */
function renderedAlias(string $resource): string
{
    config()->set('ts-publish.output_to_files', false);

    $content = new ResourceWriter(new Filesystem)->write(new ResourceTransformer($resource));
    preg_match('/^export type '.class_basename($resource).' = (.*);$/ms', $content, $match);

    return $match[1] ?? '';
}

/**
 * The exit status and output of `tsc --strict` over a snippet.
 *
 * @return array{int, string}
 */
function compileStrict(string $tsc, string $snippet): array
{
    // tempnam() creates the bare name it returns, which tsc never reads but the test must remove.
    $base = (string) tempnam(sys_get_temp_dir(), 'dn');
    $file = $base.'.ts';
    file_put_contents($file, $snippet);
    exec('cd '.escapeshellarg(dirname($file)).' && '.escapeshellarg($tsc).' --noEmit --strict '.escapeshellarg(basename($file)).' 2>&1', $output, $status);
    unlink($file);
    unlink($base);

    return [$status, implode("\n", $output)];
}

// GH-157: a published resource's arms narrow as TypeScript reads a discriminated union, and only once narrowed; two
// groups narrow apart.
it('narrows each key a resource\'s arms hold by the value of the key they follow', function () {
    $tsc = dirname(__DIR__, 2).'/node_modules/.bin/tsc';

    if (! is_executable($tsc)) {
        $this->markTestSkipped('tsc is not installed');
    }

    $pinned = renderedAlias(PinnedPostResource::class);
    $priority = renderedAlias(PostPriorityResource::class);
    $both = renderedAlias(PinnedPriorityResource::class);
    $snippet = <<<TS
        export {};
        type PinnedPlacementResource = { pinned_at: string | null };
        type FeedPlacementResource = { published_at: string | null };
        type UserResource = { id: number; name: string };
        type P = {$pinned};
        type Q = {$priority};
        type R = {$both};

        declare const p: P;
        if (p.is_pinned) { const a: PinnedPlacementResource = p.placement; }
        else { const b: FeedPlacementResource = p.placement; }
        // @ts-expect-error placement is either resource until is_pinned narrows it
        const c: PinnedPlacementResource = p.placement;

        declare const q: Q;
        const e: string | null | undefined = q.escalated_at;
        // @ts-expect-error escalated_at is missing from every arm but one
        const f: string | null = q.escalated_at;
        if (q.priority === 3) { const u: UserResource = q.badge; }
        // @ts-expect-error badge is any arm's until priority narrows it
        const v: UserResource = q.badge;

        declare const r: R;
        if (r.is_pinned && r.priority === 3) { const g: PinnedPlacementResource = r.placement; const h: string | null = r.escalated_at; }
        // @ts-expect-error each group narrows apart, so escalated_at is missing until priority narrows it
        const k: string | null = r.escalated_at;

        TS;
    $unchecked = implode("\n", array_filter(
        explode("\n", $snippet),
        static fn (string $line): bool => ! str_starts_with($line, '// @ts-expect-error'),
    ));

    [$status, $output] = compileStrict($tsc, $snippet);
    [$uncheckedStatus, $uncheckedOutput] = compileStrict($tsc, $unchecked);

    expect([$status, $output])->toBe([0, ''])
        ->and($uncheckedStatus)->not->toBe(0)
        ->and(substr_count($uncheckedOutput, 'error TS2322'))->toBe(4);
});
