<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Transformers;

use AbeTwoThree\LaravelTsPublish\Analyzers\FormRequest\FormRequestRuleNode;
use AbeTwoThree\LaravelTsPublish\Analyzers\FormRequest\FormRequestRulesAnalyzer;
use AbeTwoThree\LaravelTsPublish\Dtos\TsFormRequestDto;
use AbeTwoThree\LaravelTsPublish\Facades\LaravelTsPublish;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Str;
use Override;
use ReflectionClass;

/**
 * @phpstan-import-type FormRequestFieldData from TsFormRequestDto
 *
 * @extends CoreTransformer<FormRequest>
 */
class FormRequestTransformer extends CoreTransformer
{
    public protected(set) string $typeName;

    public protected(set) string $description = '';

    public protected(set) string $filename;

    public protected(set) string $namespacePath;

    public protected(set) bool $isDynamic = false;

    /** @var list<FormRequestFieldData> */
    public protected(set) array $fields = [];

    #[Override]
    public function transform(): self
    {
        $this->initReflection()
            ->analyzeRules();

        return $this;
    }

    #[Override]
    public function filename(): string
    {
        return $this->filename;
    }

    #[Override]
    public function data(): TsFormRequestDto
    {
        return new TsFormRequestDto(
            fqcn: $this->findable,
            filename: $this->filename,
            namespacePath: $this->namespacePath,
            typeName: $this->typeName,
            description: $this->description,
            fields: $this->fields,
            isDynamic: $this->isDynamic,
        );
    }

    /**
     * Initialize the reflection instance and set form request metadata.
     */
    protected function initReflection(): self
    {
        /** @var ReflectionClass<FormRequest> $reflection */
        $reflection = new ReflectionClass($this->findable);

        $shortName = $reflection->getShortName();

        $this->typeName = $shortName;
        $this->filename = Str::kebab($shortName);
        $this->namespacePath = LaravelTsPublish::namespaceToPath($this->findable);

        $description = '';

        if ($reflection->hasMethod('rules')) {
            $description = LaravelTsPublish::parseDocBlockDescription(
                $reflection->getMethod('rules')->getDocComment()
            );
        }

        if ($description === '') {
            $description = LaravelTsPublish::parseDocBlockDescription($reflection->getDocComment());
        }

        $this->description = $description;

        return $this;
    }

    /**
     * Analyze the form request rules and populate the fields list.
     */
    protected function analyzeRules(): self
    {
        /** @var FormRequestRulesAnalyzer $analyzer */
        $analyzer = resolve(Config::string('ts-publish.form_requests.analyzer_class', FormRequestRulesAnalyzer::class));

        $nodes = $analyzer->analyze($this->findable);
        $this->isDynamic = $analyzer->isDynamic;

        $this->fields = array_map(
            fn (FormRequestRuleNode $node): array => [
                'fieldPath' => $node->fieldPath,
                'tsType' => $node->tsType,
                'isRequired' => $node->isRequired,
                'isNullable' => $node->isNullable,
                'isProhibited' => $node->isProhibited,
                'jsDocMetadata' => $node->jsDocMetadata,
            ],
            $nodes,
        );

        return $this;
    }
}
