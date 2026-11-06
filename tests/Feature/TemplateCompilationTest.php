<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\View;
use Illuminate\View\Compilers\BladeCompiler;
use Illuminate\View\Engines\CompilerEngine;
use Workbench\App\Enums\Priority;

/**
 * A Blade compiler that counts its compiles by view path, installed as the app's Blade engine compiles with.
 */
function countingBladeCompiler(): BladeCompiler
{
    $compiler = new class(app('files'), Config::string('view.compiled')) extends BladeCompiler
    {
        /** @var array<string, int> view path => compiles */
        public array $compiles = [];

        /**
         * Count the compile, then compile as Blade does.
         */
        public function compile($path = null): void
        {
            $this->compiles[(string) $path] = ($this->compiles[(string) $path] ?? 0) + 1;

            parent::compile($path);
        }
    };

    View::getEngineResolver()->register('blade', fn () => new CompilerEngine($compiler));

    return $compiler;
}

/**
 * Write the template a case renders, at the version given, and return the path of the file that changes between
 * versions: the template itself, or the view it includes.
 */
function writeCaseTemplate(string $case, string $views, string $version): string
{
    $package = (string) file_get_contents(__DIR__.'/../../resources/views/enum.blade.php');

    if ($case === 'include') {
        file_put_contents($views.'/enum.blade.php', "@include('laravel-ts-publish::banner')\n".$package);
        file_put_contents($views.'/banner.blade.php', "// {$version}\n");

        return $views.'/banner.blade.php';
    }

    $template = $views.($case === 'custom' ? '/custom-enum.blade.php' : '/enum.blade.php');
    file_put_contents($template, "// {$version}\n".$package);

    return $template;
}

beforeEach(function () {
    $this->out = sys_get_temp_dir().'/ts-publish-out-'.uniqid();
    $this->enumFile = $this->out.'/workbench/app/enums/priority.ts';
    mkdir($this->out.'-views');
    // The view finder resolves symlinks, and a compiled copy is named by the path it resolves.
    $this->views = (string) realpath($this->out.'-views');

    Config::set('ts-publish.output_to_files', true);
    Config::set('ts-publish.output_directory', $this->out);

    // A published override directory ahead of the package's views, and a location for a custom template name.
    View::prependNamespace('laravel-ts-publish', $this->views);
    View::addLocation($this->views);
    View::getFinder()->flush();
});

afterEach(function () {
    foreach ([$this->out, $this->views, $this->out.'-cache'] as $dir) {
        if (is_dir($dir)) {
            $items = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS),
                RecursiveIteratorIterator::CHILD_FIRST,
            );
            foreach ($items as $item) {
                $item->isDir() ? @rmdir($item->getPathname()) : @unlink($item->getPathname());
            }
            @rmdir($dir);
        }
    }
});

// A dist install unzips each file with its release's time, so the source an upgrade brings can be older than the copy
// an earlier run compiled from the previous version.
test('a run renders a template from its source when its compiled copy is newer', function (string $case, bool $earlierRun) {
    if ($case === 'custom') {
        Config::set('ts-publish.enums.template', 'custom-enum');
    }

    $changing = writeCaseTemplate($case, $this->views, 'v1');

    if ($earlierRun) {
        expect(Artisan::call('ts:publish', ['--only-enums' => true, '--quiet' => true]))->toBe(0)
            ->and(file_get_contents($this->enumFile))->toContain("// v1\n");
    } else {
        // An earlier process compiled v1, so this process's engine has never checked the view.
        foreach (glob($this->views.'/*.blade.php') ?: [] as $view) {
            app('blade.compiler')->compile($view);
        }
    }

    writeCaseTemplate($case, $this->views, 'v2');
    touch($changing, time() - 3600);

    expect(Artisan::call('ts:publish', ['--only-enums' => true, '--quiet' => true]))->toBe(0)
        ->and(file_get_contents($this->enumFile))->toContain("// v2\n")
        ->not->toContain("// v1\n");
})->with([
    'a published override' => 'override',
    'a custom template name' => 'custom',
    'a view the template includes' => 'include',
])->with([
    'after a run in this process' => true,
    'in a fresh process' => false,
]);

test('a --source run renders a template from its source when its compiled copy is newer', function () {
    $template = writeCaseTemplate('override', $this->views, 'v1');

    expect(Artisan::call('ts:publish', ['--source' => Priority::class, '--quiet' => true]))->toBe(0)
        ->and(file_get_contents($this->enumFile))->toContain("// v1\n");

    writeCaseTemplate('override', $this->views, 'v2');
    touch($template, time() - 3600);

    expect(Artisan::call('ts:publish', ['--source' => Priority::class, '--quiet' => true]))->toBe(0)
        ->and(file_get_contents($this->enumFile))->toStartWith("// v2\n");
});

test('with the cache on, the rebuild renders the new template and a later cached run keeps it', function () {
    Config::set('ts-publish.cache.enabled', true);
    Config::set('ts-publish.cache.store', null);
    Config::set('ts-publish.cache.directory', $this->out.'-cache');
    $template = writeCaseTemplate('override', $this->views, 'v1');

    expect(Artisan::call('ts:publish', ['--only-enums' => true, '--quiet' => true]))->toBe(0)
        ->and(file_get_contents($this->enumFile))->toStartWith("// v1\n");

    // The template's hash moves the cache header, so this run rebuilds and caches what it renders.
    writeCaseTemplate('override', $this->views, 'v2');
    touch($template, time() - 3600);

    expect(Artisan::call('ts:publish', ['--only-enums' => true, '--quiet' => true]))->toBe(0)
        ->and(file_get_contents($this->enumFile))->toStartWith("// v2\n");

    $compiler = countingBladeCompiler();

    expect(Artisan::call('ts:publish', ['--only-enums' => true, '--quiet' => true]))->toBe(0)
        ->and(file_get_contents($this->enumFile))->toStartWith("// v2\n")
        // Served from the cache: a hit never renders the enum template.
        ->and($compiler->compiles[$template] ?? 0)->toBe(0);
});

test('a run compiles each template it renders once, however many times it renders it', function () {
    $compiler = countingBladeCompiler();
    $enumTemplate = View::getFinder()->find('laravel-ts-publish::enum');
    $enumFiles = fn (): int => count(glob($this->out.'/workbench/app/enums/*.ts') ?: []);

    expect(Artisan::call('ts:publish', ['--quiet' => true]))->toBe(0)
        ->and($enumFiles())->toBeGreaterThan(1)
        ->and($compiler->compiles[$enumTemplate] ?? 0)->toBe(1)
        ->and(array_values(array_unique($compiler->compiles)))->toBe([1]);

    expect(Artisan::call('ts:publish', ['--quiet' => true]))->toBe(0)
        ->and($compiler->compiles[$enumTemplate] ?? 0)->toBe(2)
        ->and(array_values(array_unique($compiler->compiles)))->toBe([2]);
});

test('a run leaves the app\'s Blade engine as it found it, even when a template throws', function () {
    $engine = View::getEngineResolver()->resolve('blade');

    expect(Artisan::call('ts:publish', ['--only-enums' => true, '--quiet' => true]))->toBe(0)
        ->and(View::getEngineResolver()->resolve('blade'))->toBe($engine);

    file_put_contents($this->views.'/enum.blade.php', "@php throw new RuntimeException('template failed'); @endphp\n");

    expect(Artisan::call('ts:publish', ['--only-enums' => true, '--quiet' => true]))->toBe(1)
        ->and(View::getEngineResolver()->resolve('blade'))->toBe($engine);
});
