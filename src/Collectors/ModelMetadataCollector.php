<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Collectors;

use Illuminate\Support\Facades\Config;

class ModelMetadataCollector extends ModelsCollector
{
    /**
     * Resolve metadata finder settings with model settings as defaults.
     */
    protected function finderSettings(): array
    {
        $settings = parent::finderSettings();

        foreach (array_keys($settings) as $name) {
            $key = "ts-publish.model_metadata.{$name}";

            if (Config::has($key)) {
                $settings[$name] = $this->sanitizeAllowSetting(Config::array($key));
            }
        }

        return $settings;
    }
}
