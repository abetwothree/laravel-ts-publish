<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Ast;

use AbeTwoThree\LaravelTsPublish\Cache\DependencyRecorder;
use Illuminate\Http\Resources\Json\JsonResource;
use ReflectionClass;

/**
 * Whether the type an API resource publishes already holds the key its response wraps it by, so Laravel's
 * ResourceResponse sends it as it stands rather than nested under that key.
 *
 * @internal
 */
final class ResourceResponseBody
{
    /**
     * Whether a resource's published type holds a top-level key on every path (true), on some (null) or never (false):
     * a collection that sends its collected list publishes the response built around it, any other body its own keys.
     * Read once per resource and key a run.
     *
     * @param  class-string<JsonResource>  $resource
     */
    public function holdsWrapKey(string $resource, string $key): ?bool
    {
        return resolve(AnalysisMemo::class)->remember('holds-wrap-key:'.$resource.'@'.$key, function () use ($resource, $key): ?bool {
            // The caller's type follows this body; the memo replays the files recorded here on every later read.
            DependencyRecorder::recordClass($resource);

            if (resolve(ResourceCollectionBody::class)->sendsCollectedList(new ReflectionClass($resource))) {
                return true;
            }

            foreach (resolve(AstEngine::class)->analyzeMethod($resource)->properties as $property) {
                if ($property['name'] === $key) {
                    return $property['optional'] ? null : true;
                }
            }

            return false;
        });
    }
}
