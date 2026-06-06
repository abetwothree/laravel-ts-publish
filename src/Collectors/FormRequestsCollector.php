<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Collectors;

use Illuminate\Foundation\Http\FormRequest;
use ReflectionClass;

/**
 * @extends CoreCollector<FormRequest>
 */
class FormRequestsCollector extends CoreCollector
{
    protected function defaultDirectory(): string
    {
        return app_path('Http/Requests');
    }

    /** @param ReflectionClass<object> $reflection */
    protected function classFilter(ReflectionClass $reflection): bool
    {
        return $this->validateFormRequest($reflection);
    }

    protected function finderSettings(): array
    {
        return [
            'included' => $this->sanitizeAllowSetting(config()->array('ts-publish.form_requests.included')),
            'excluded' => $this->sanitizeAllowSetting(config()->array('ts-publish.form_requests.excluded')),
            'additional_directories' => $this->sanitizeAllowSetting(config()->array('ts-publish.form_requests.additional_directories')),
        ];
    }
}
