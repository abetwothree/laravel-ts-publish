<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures\ClassQueueGuard;

use Illuminate\Support\Facades\File;
use RuntimeException;

/**
 * Loads each generated body's class from its own unguessably named directory under the system temp directory, never
 * under `workbench/`, whose resources DroppedUnionArmsAuditTest globs.
 */
final class GuardClassFiles
{
    /** @var list<string> */
    private array $directories = [];

    /**
     * Write $case's resource to a new directory, load it, and return its FQCN.
     *
     * @return class-string
     */
    public function load(GuardCase $case): string
    {
        $token = bin2hex(random_bytes(8));
        $directory = sys_get_temp_dir().'/ts-publish-class-queue-guard-'.$token;
        [$class, $source] = $case->source('GuardResource'.$token);
        $file = $directory.'/'.class_basename($class).'.php';

        throw_unless(mkdir($directory), RuntimeException::class, "Cannot create {$directory}.");
        $this->directories[] = $directory;
        throw_unless(file_put_contents($file, $source) !== false, RuntimeException::class, "Cannot write {$file}.");

        require $file;

        return $class;
    }

    /**
     * Remove every directory load() made.
     */
    public function remove(): void
    {
        foreach ($this->directories as $directory) {
            File::deleteDirectory($directory);
        }

        $this->directories = [];
    }
}
