<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Transformers;

use AbeTwoThree\LaravelTsPublish\Dtos\Contracts\Datable;
use AbeTwoThree\LaravelTsPublish\Facades\TsNaming;

/**
 * @template TTransformable
 */
abstract class CoreTransformer
{
    /** Namespace-based output directory path, e.g. 'workbench/app/models'. */
    public protected(set) string $namespacePath;

    /**
     * @param  class-string<TTransformable>  $findable
     */
    public function __construct(
        protected string $findable,
    ) {
        $this->transform();
    }

    /** @return static */
    abstract public function transform(): self;

    abstract public function filename(): string;

    abstract public function data(): Datable;

    /**
     * Get the fully qualified class name being transformed.
     *
     * @return class-string<TTransformable>
     */
    public function fqcn(): string
    {
        return $this->findable;
    }

    /**
     * The global namespace this class's types are declared under in the globals file.
     */
    public function globalNamespace(): string
    {
        return TsNaming::globalNamespace($this->findable);
    }

    /**
     * The composed classes whose module this class's file imports. A cache hit runs no transform, so each publisher
     * reads them from the custom imports it keeps, which its snapshot carries.
     *
     * @return list<class-string>
     */
    public function composedClassTypes(): array
    {
        return [];
    }

    protected function resolveRelativePath(string $absolutePath): string
    {
        return TsNaming::resolveRelativePath($absolutePath);
    }
}
