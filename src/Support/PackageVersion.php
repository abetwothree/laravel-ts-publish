<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Support;

use Composer\InstalledVersions;
use Throwable;

class PackageVersion
{
    public const PACKAGE = 'abetwothree/laravel-ts-publish';

    /**
     * Best-effort current installed version of this package.
     *
     * Falls back to the reference (commit hash) and finally 'dev' so the
     * cache always has a stable busting token even in non-Composer contexts.
     */
    public static function current(): string
    {
        try {
            if (InstalledVersions::isInstalled(self::PACKAGE)) {
                return InstalledVersions::getPrettyVersion(self::PACKAGE)
                    ?? InstalledVersions::getReference(self::PACKAGE)
                    ?? 'dev';
            }
        } catch (Throwable) {
            // Composer runtime not available; fall through.
        }

        return 'dev';
    }
}
