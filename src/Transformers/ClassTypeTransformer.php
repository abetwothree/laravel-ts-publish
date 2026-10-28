<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Transformers;

use AbeTwoThree\LaravelTsPublish\Dtos\Contracts\Datable;
use AbeTwoThree\LaravelTsPublish\Dtos\TsClassTypeDto;
use AbeTwoThree\LaravelTsPublish\Facades\TsNaming;
use AbeTwoThree\LaravelTsPublish\Facades\TsTypeString;
use AbeTwoThree\LaravelTsPublish\Support\ClassTypeOverride;
use AbeTwoThree\LaravelTsPublish\Support\ComposedClassTypes;
use AbeTwoThree\LaravelTsPublish\Transformers\Concerns\SnapshotsTransformerState;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Override;

/**
 * Builds a composed class's module: one interface, named `{Basename}Type`, extending every override the class inherits.
 *
 * @phpstan-import-type TypesImportMap from Datable
 * @phpstan-import-type Contributor from ClassTypeOverride
 *
 * @extends CoreTransformer<object>
 */
class ClassTypeTransformer extends CoreTransformer
{
    use SnapshotsTransformerState;

    public protected(set) string $typeName;

    public protected(set) string $filename;

    /** @var list<string> each contributor's type, as the interface extends it */
    public protected(set) array $extends = [];

    /** @var TypesImportMap */
    public protected(set) array $typeImports = [];

    #[Override]
    public function transform(): self
    {
        $override = ClassTypeOverride::resolve($this->findable);

        if ($override === null || ! $override['composed']) {
            throw new InvalidArgumentException(
                "{$this->findable} has no composed #[TsType]: only a class that inherits two or more that can be extended has a module.",
            );
        }

        $this->typeName = ComposedClassTypes::typeName($override['class']);
        $this->filename = Str::kebab(class_basename($override['class']));
        $this->namespacePath = TsNaming::namespaceToPath($override['class']);

        $this->resolveContributors($override['contributors']);

        return $this;
    }

    #[Override]
    public function filename(): string
    {
        return $this->filename;
    }

    #[Override]
    public function data(): TsClassTypeDto
    {
        return new TsClassTypeDto(
            fqcn: $this->findable,
            filename: $this->filename,
            namespacePath: $this->namespacePath,
            typeName: $this->typeName,
            extends: $this->extends,
            typeImports: $this->typeImports,
        );
    }

    /** @return list<string> */
    protected function transientProperties(): array
    {
        return [];
    }

    /**
     * Extend each contributor once and import what each names, aliasing an imported name the interface's own shadows.
     *
     * @param  list<Contributor>  $contributors
     */
    protected function resolveContributors(array $contributors): void
    {
        $alias = $this->typeName.'Base';
        $imports = [];
        $extends = [];

        foreach ($contributors as $contributor) {
            $type = $contributor['type'];

            foreach (ClassTypeOverride::importsOf($contributor) as $path => $names) {
                foreach ($names as $name) {
                    if ($name === $this->typeName) {
                        $type = (string) preg_replace(TsTypeString::queuedTokenPattern([$name]), $alias, $type);
                        $name = "{$name} as {$alias}";
                    }

                    $imports[$path][] = $name;
                }
            }

            $extends[] = $type;
        }

        foreach ($imports as $path => $names) {
            $names = array_values(array_unique($names));
            sort($names);
            $imports[$path] = $names;
        }

        $this->extends = array_values(array_unique($extends));
        $this->typeImports = TsNaming::sortImportPaths($imports);
    }
}
