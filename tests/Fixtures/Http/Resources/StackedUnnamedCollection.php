<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use Workbench\App\Http\Resources\SupplierSummaryCollection;

/**
 * Names no collected resource and matches no naming convention, so Laravel collects its raw models, while its parent
 * collects SupplierSummaryResource through its own name.
 */
class StackedUnnamedCollection extends SupplierSummaryCollection {}
