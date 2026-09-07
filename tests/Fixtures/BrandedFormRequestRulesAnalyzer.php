<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures;

use AbeTwoThree\LaravelTsPublish\Analyzers\FormRequest\FormRequestRuleNode;
use AbeTwoThree\LaravelTsPublish\Analyzers\FormRequest\FormRequestRulesAnalyzer;
use Illuminate\Foundation\Http\FormRequest;
use Override;

/**
 * A `ts-publish.form_requests.analyzer_class` override that brands every type it resolves, so a
 * caller reading the default analyzer instead of the configured one is visible in the output.
 */
class BrandedFormRequestRulesAnalyzer extends FormRequestRulesAnalyzer
{
    /**
     * @param  class-string<FormRequest>  $fqcn
     * @return list<FormRequestRuleNode>
     */
    #[Override]
    public function analyze(string $fqcn): array
    {
        return array_map($this->brand(...), parent::analyze($fqcn));
    }

    /**
     * @param  class-string<FormRequest>  $fqcn
     */
    #[Override]
    public function analyzeField(string $fqcn, string $dottedPath): ?FormRequestRuleNode
    {
        $node = parent::analyzeField($fqcn, $dottedPath);

        if ($node === null) {
            return null;
        }

        return $this->brand($node);
    }

    /**
     * Replace one node's resolved type with the brand.
     */
    private function brand(FormRequestRuleNode $node): FormRequestRuleNode
    {
        return new FormRequestRuleNode(
            fieldPath: $node->fieldPath,
            tsType: 'Branded',
            isRequired: $node->isRequired,
            isNullable: $node->isNullable,
            isProhibited: $node->isProhibited,
            jsDocMetadata: $node->jsDocMetadata,
        );
    }
}
