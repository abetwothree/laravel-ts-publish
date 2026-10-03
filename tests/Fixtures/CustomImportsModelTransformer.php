<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures;

use AbeTwoThree\LaravelTsPublish\Transformers\ModelTransformer;
use Override;
use Workbench\App\Enums\Role;

/**
 * A test-only model transformer that shapes its imports through the methods a project overrides, with the signatures
 * they have always had: it drops the Role enum's const, and builds only the imports every interface uses.
 */
class CustomImportsModelTransformer extends ModelTransformer
{
    /** @return list<string> */
    #[Override]
    protected function enumPropertyFqcns(): array
    {
        return array_values(array_diff(parent::enumPropertyFqcns(), [Role::class]));
    }

    /** @return array{typeImports: array<string, list<string>>, valueImports: array<string, list<string>>} */
    #[Override]
    protected function buildResolvedImports(): array
    {
        $imports = parent::buildResolvedImports();

        return ['typeImports' => $imports['typeImports'], 'valueImports' => $imports['valueImports']];
    }
}
