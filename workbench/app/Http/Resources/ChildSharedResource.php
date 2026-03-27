<?php

namespace Workbench\App\Http\Resources;

use Workbench\App\Http\Resources\Concerns\SharedExtendsInterface;

/**
 * Child resource that uses SharedExtendsInterface AND extends a parent that also uses it.
 * SharedExtendsInterface should appear only once in the result despite being reachable via two paths.
 */
class ChildSharedResource extends BaseSharedResource
{
    use SharedExtendsInterface;
}
