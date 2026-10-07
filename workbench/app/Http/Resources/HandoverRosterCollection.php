<?php

declare(strict_types=1);

namespace Workbench\App\Http\Resources;

/**
 * Declares nothing: the `$collects` it inherits outranks the naming convention, so Laravel collects HandoverResource,
 * not HandoverRosterResource.
 */
class HandoverRosterCollection extends HandoverCollection {}
