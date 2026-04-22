<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Analyzers\Inertia;

use AbeTwoThree\LaravelTsPublish\Analyzers\SurveyorTypeMapper;
use Illuminate\Support\Str;
use Laravel\Ranger\Collectors\InertiaComponents;
use Laravel\Ranger\Collectors\Response as ResponseCollector;
use Laravel\Ranger\Components\InertiaResponse;

/**
 * Detects Inertia::render() calls in controller actions via Ranger's static
 * analysis and extracts component names and page-prop types.
 *
 * @phpstan-type InertiaPageData = array{
 *     component: string|list<string>,
 *     pageType: string,
 * }
 */
class InertiaPageAnalyzer
{
    public function __construct(
        protected ResponseCollector $responseCollector,
    ) {}

    /**
     * Analyze a controller action and extract Inertia page data.
     *
     * Ranger's parseResponse() returns component name strings for Inertia
     * responses. We collect those names, then look up full InertiaResponse
     * objects via InertiaComponents::getComponent().
     *
     * @param  array{uses: string}  $action  The route action array with 'uses' key (Controller@method format).
     * @return InertiaPageData|null Null when the action does not render an Inertia response.
     */
    public function analyze(array $action): ?array
    {
        // Ranger's parseResponse() returns component name strings for Inertia
        // responses despite its docblock claiming InertiaResponse objects.
        /** @var list<string|mixed> $responses */
        $responses = $this->responseCollector->parseResponse($action);

        /** @var list<string> $componentNames */
        $componentNames = array_values(array_filter(
            $responses,
            fn (mixed $response): bool => is_string($response),
        ));

        if ($componentNames === []) {
            return null;
        }

        /** @var list<InertiaResponse> $inertiaResponses */
        $inertiaResponses = array_map(
            fn (string $name): InertiaResponse => InertiaComponents::getComponent($name),
            $componentNames,
        );

        return $this->buildPageData($inertiaResponses);
    }

    /**
     * Build the page data from one or more InertiaResponse instances.
     *
     * @param  list<InertiaResponse>  $responses
     * @return InertiaPageData
     */
    protected function buildPageData(array $responses): array
    {
        $components = array_map(
            fn (InertiaResponse $r): string => $r->component,
            $responses,
        );

        $pageTypes = array_map(
            fn (InertiaResponse $r): string => $this->buildPageType($r),
            $responses,
        );

        // Single component → string; multiple (conditional) → array
        $component = count($components) === 1 ? $components[0] : $components;

        // Multiple types → union
        $pageType = count($pageTypes) === 1
            ? $pageTypes[0]
            : implode(' | ', $pageTypes);

        return [
            'component' => $component,
            'pageType' => $pageType,
        ];
    }

    /**
     * Build the TypeScript type string for a single InertiaResponse.
     *
     * Transforms the component name into a fully-qualified namespace path:
     *   "Dashboard"          → "Inertia.Pages.Dashboard"
     *   "Settings/General"   → "Inertia.Pages.Settings.General"
     *   "settings/two-factor" → "Inertia.Pages.Settings.TwoFactor"
     */
    protected function buildPageType(InertiaResponse $response): string
    {
        $fqn = $this->componentToFqn($response->component);

        $sharedData = 'Inertia.SharedData';

        if (count($response->data) === 0) {
            return $sharedData;
        }

        $propsType = SurveyorTypeMapper::objectToTypeString($response->data);

        return $sharedData.' & '.$propsType;
    }

    /**
     * Convert a component name to a fully-qualified Inertia namespace path.
     *
     * @example "Dashboard" → "Inertia.Pages.Dashboard"
     * @example "Settings/General" → "Inertia.Pages.Settings.General"
     */
    public function componentToFqn(string $component): string
    {
        $normalized = str_replace('::', '/', $component);

        return collect(explode('/', $normalized))
            ->map(fn (string $part): string => Str::studly($part))
            ->prepend('Inertia.Pages')
            ->implode('.');
    }
}
