<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures;

use AbeTwoThree\LaravelTsPublish\Dtos\TsModelDto;
use AbeTwoThree\LaravelTsPublish\Transformers\ModelTransformer;
use Override;

/**
 * A test-only model transformer whose data() builds the DTO from the full lists alone, as an override written before
 * the DTO carried the shared keys and the combined lists does.
 */
class FullListsModelTransformer extends ModelTransformer
{
    #[Override]
    public function data(): TsModelDto
    {
        $hasEnums = $this->shouldGenerateHasEnums();
        $imports = $this->buildResolvedImports();

        return new TsModelDto(
            modelName: $this->modelName,
            description: $this->description,
            fqcn: $this->fqcn(),
            filePath: $this->filePath,
            filename: $this->filename(),
            columns: $this->columns,
            mutators: $this->mutators,
            appends: $this->appends,
            relations: $this->relations,
            typeImports: $imports['typeImports'],
            valueImports: $imports['valueImports'],
            enumColumns: $hasEnums ? $this->buildEnumColumns() : [],
            enumMutators: $hasEnums ? $this->buildEnumMutators() : [],
            enumAppends: $hasEnums ? $this->buildEnumAppends() : [],
            tsExtends: $this->tsExtends,
        );
    }
}
