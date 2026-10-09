<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Support;

use Illuminate\Support\ConfigurationUrlParser;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\View;
use Illuminate\View\ViewName;
use InvalidArgumentException;
use Throwable;

class ConfigFingerprint
{
    /**
     * The template key of each feature whose cache hit never renders, with the view its writer falls back to when the
     * key is unset. The other templates render on every run, so an edit to them already reaches the output.
     */
    private const array CACHED_TEMPLATES = [
        'enums.template' => null,
        'models.template' => null,
        'model_metadata.template' => 'laravel-ts-publish::model-meta',
        'resources.template' => null,
        'routes.template' => null,
        'form_requests.template' => null,
        'broadcast_events.template' => null,
    ];

    /** A Blade directive that renders another view, which may be named by a string literal among its arguments. */
    private const string VIEW_DIRECTIVE = '/@(?:include(?:If|When|Unless|First)?|extends|each|component)\s*\(([^)]*)\)/';

    /**
     * Hash the output-affecting `ts-publish` config, each database connection's driver, and the templates the cached
     * features render, so the cache busts when any changes: a relation aggregate publishes as its connection's driver
     * returns it, and a cache hit never renders its template again.
     *
     * The `cache` sub-array is excluded: toggling the cache must not bust outputs.
     */
    public static function compute(): string
    {
        /** @var array<string, mixed> $config */
        $config = Config::array('ts-publish');

        unset($config['cache']);

        try {
            $config = [
                'ts-publish' => $config,
                'database.default' => Config::get('database.default'),
                'database.drivers' => array_map(self::connectionDriver(...), Config::array('database.connections', [])),
                'templates' => self::templateFiles(),
            ];

            self::ksortRecursive($config);

            return hash('xxh128', serialize($config));
        } catch (Throwable) {
            // A non-serializable config value (e.g. a closure) or a malformed connection url must not crash generation.
            // A per-run token can never match a stored manifest header, forcing a full rebuild over stale output.
            return 'unfingerprintable-'.bin2hex(random_bytes(16));
        }
    }

    /**
     * The content hash of each cached feature's template as the view finder resolves it, and of each view it includes
     * by a literal name.
     *
     * @return array<string, string> view name => content hash
     */
    private static function templateFiles(): array
    {
        $files = [];

        foreach (self::CACHED_TEMPLATES as $key => $default) {
            $name = Config::get('ts-publish.'.$key, $default);

            if (is_string($name) && $name !== '') {
                self::addViewFile($name, $files, true);
            }
        }

        return $files;
    }

    /**
     * Add one view's content hash, then each view its Blade directives name by a string literal, which it renders too.
     *
     * @param  array<string, string>  $files  view name => content hash
     */
    private static function addViewFile(string $name, array &$files, bool $configured): void
    {
        if (isset($files[$name])) {
            return;
        }

        try {
            $path = View::getFinder()->find(ViewName::normalize($name));
        } catch (InvalidArgumentException) {
            // A stable marker, so a missing template is reported by its render rather than rebuilding every run.
            // A literal among a directive's arguments need not name a view at all.
            if ($configured) {
                $files[$name] = 'unresolved';
            }

            return;
        }

        $source = is_file($path) ? (string) file_get_contents($path) : '';

        // The content, not the path: publishing an unedited copy moves the resolution but not the output.
        $files[$name] = hash('xxh128', $source);

        preg_match_all(self::VIEW_DIRECTIVE, $source, $directives);

        foreach ($directives[1] as $arguments) {
            // Quoted names only: a double-quoted string holding a `$` interpolates, so it names no fixed view.
            preg_match_all('/\'([^\'\\\\]+)\'|"([^"\\\\$]+)"/', $arguments, $literals);

            foreach (array_filter([...$literals[1], ...$literals[2]]) as $included) {
                self::addViewFile($included, $files, false);
            }
        }
    }

    /**
     * The driver Laravel connects a configured connection with: the one its `url` names, else its `driver` key.
     */
    private static function connectionDriver(mixed $connection): mixed
    {
        return is_array($connection)
            ? new ConfigurationUrlParser()->parseConfiguration([
                'driver' => $connection['driver'] ?? null,
                'url' => $connection['url'] ?? null,
            ])['driver'] ?? null
            : null;
    }

    /**
     * Recursively sort an array by key so the fingerprint ignores config declaration order.
     *
     * @param  array<array-key, mixed>  $array
     */
    private static function ksortRecursive(array &$array): void
    {
        foreach ($array as &$value) {
            if (is_array($value)) {
                self::ksortRecursive($value);
            }
        }

        unset($value);

        ksort($array);
    }
}
