<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Transformers;

use AbeTwoThree\LaravelTsPublish\Analyzers\FormRequest\FormRequestRuleNode;
use AbeTwoThree\LaravelTsPublish\Analyzers\FormRequest\FormRequestRulesAnalyzer;
use AbeTwoThree\LaravelTsPublish\Concerns\ParsesTsCasts;
use AbeTwoThree\LaravelTsPublish\Dtos\TsFormRequestDto;
use AbeTwoThree\LaravelTsPublish\Facades\LaravelTsPublish;
use AbeTwoThree\LaravelTsPublish\Transformers\Concerns\ParsesTsExtends;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Str;
use Override;
use ReflectionClass;

/**
 * @phpstan-import-type FormRequestFieldData from TsFormRequestDto
 * @phpstan-import-type TypesImportMap from TsFormRequestDto
 *
 * @extends CoreTransformer<FormRequest>
 */
class FormRequestTransformer extends CoreTransformer
{
    use ParsesTsCasts;
    use ParsesTsExtends;

    public protected(set) string $typeName;

    public protected(set) string $description = '';

    public protected(set) string $filename;

    public protected(set) string $namespacePath;

    public protected(set) bool $isDynamic = false;

    /** @var list<FormRequestFieldData> */
    public protected(set) array $fields = [];

    /**
     * TsCasts attribute overrides: field path => TypeScript type string.
     *
     * @var array<string, string>
     */
    protected array $tsTypeOverrides = [];

    /**
     * Import paths declared alongside TsCasts overrides: field path => import path.
     *
     * @var array<string, string>
     */
    protected array $tsCastsImportPaths = [];

    /**
     * Optional overrides from #[TsCasts]: field path => optional flag.
     *
     * @var array<string, bool>
     */
    protected array $optionalOverrides = [];

    /**
     * Resolved type imports: import path => list of type names.
     *
     * @var TypesImportMap
     */
    public protected(set) array $typeImports = [];

    /**
     * TypeScript extends clauses parsed from #[TsExtends] attributes and config.
     *
     * @var list<string>
     */
    public protected(set) array $tsExtends = [];

    /**
     * Import entries from TsExtends to be merged into typeImports.
     *
     * @var array<string, list<string>>
     */
    protected array $tsExtendsImports = [];

    /** Reflection of the form request class, shared across transformation steps. */
    /** @var ReflectionClass<FormRequest> */
    protected ReflectionClass $reflection;

    #[Override]
    public function transform(): self
    {
        $this->initReflection()
            ->parseTsExtends()
            ->parseTsCasts()
            ->analyzeRules()
            ->applyTsCastsOverrides()
            ->buildTypeImports();

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
            typeImports: $this->typeImports,
            tsExtends: $this->tsExtends,
        );
    }

    /**
     * Initialize the reflection instance and set form request metadata.
     */
    protected function initReflection(): self
    {
        $this->reflection = new ReflectionClass($this->findable);

        $shortName = $this->reflection->getShortName();

        $this->typeName = $shortName;
        $this->filename = Str::kebab($shortName);
        $this->namespacePath = LaravelTsPublish::namespaceToPath($this->findable);

        $description = '';

        if ($this->reflection->hasMethod('rules')) {
            $description = LaravelTsPublish::parseDocBlockDescription(
                $this->reflection->getMethod('rules')->getDocComment()
            );
        }

        if ($description === '') {
            $description = LaravelTsPublish::parseDocBlockDescription($this->reflection->getDocComment());
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

    /**
     * Apply #[TsCasts] type overrides to already-analyzed fields.
     *
     * Iterates the resolved fields and replaces tsType for any field whose
     * fieldPath has an entry in $this->tsTypeOverrides. Also applies optional
     * overrides, mapping optional: true to isRequired: false and vice versa.
     */
    protected function applyTsCastsOverrides(): self
    {
        $this->fields = array_map(function (array $field): array {
            $path = $field['fieldPath'];

            if (isset($this->tsTypeOverrides[$path])) {
                $field['tsType'] = $this->tsTypeOverrides[$path];
            }

            if (isset($this->optionalOverrides[$path])) {
                $field['isRequired'] = ! $this->optionalOverrides[$path];
            }

            return $field;
        }, $this->fields);

        return $this;
    }

    /**
     * Build the TypeScript type import map from TsCasts import-path overrides
     * and store the result in $this->typeImports.
     */
    protected function buildTypeImports(): self
    {
        /** @var TypesImportMap $imports */
        $imports = [];

        foreach ($this->tsCastsImportPaths as $field => $importPath) {
            $type = $this->tsTypeOverrides[$field] ?? null;

            if ($type !== null) {
                foreach (LaravelTsPublish::extractImportableTypes($type) as $importName) {
                    $imports[$importPath][] = $importName;
                }
            }
        }

        // Include import paths declared via TsExtends attributes and config
        foreach ($this->tsExtendsImports as $importPath => $typeNames) {
            foreach ($typeNames as $typeName) {
                $imports[$importPath][] = $typeName;
            }
        }

        foreach ($imports as $path => $types) {
            $unique = array_values(array_unique($types));
            sort($unique);
            $imports[$path] = $unique;
        }

        $this->typeImports = LaravelTsPublish::sortImportPaths($imports);

        return $this;
    }

    /**
     * Parse #[TsExtends] attribute extends clauses and their import entries from the form request class.
     */
    protected function parseTsExtends(): self
    {
        $result = $this->parseTsExtendsFromReflection($this->reflection, 'form_requests');

        $this->tsExtends = $result['extends'];
        $this->tsExtendsImports = $result['imports'];

        return $this;
    }

    /**
     * Parse #[TsCasts] attribute overrides from the form request class.
     */
    protected function parseTsCasts(): self
    {
        $result = $this->parseTsCastsFromReflection($this->reflection);

        $this->tsTypeOverrides = $result['overrides'];
        $this->tsCastsImportPaths = $result['importPaths'];
        $this->optionalOverrides = $result['optionalOverrides'];

        return $this;
    }
}
