<?php

declare(strict_types=1);

namespace Workbench\App\Http\Controllers;

/**
 * Used exclusively by the route-signature cache-bust regression test.
 * Do NOT add routes to this controller in web.php.
 */
class CacheBustController
{
    public function baseline(): void {}

    public function probe(): void {}
}
