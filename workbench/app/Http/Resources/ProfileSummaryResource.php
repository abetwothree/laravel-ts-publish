<?php

declare(strict_types=1);

namespace Workbench\App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Profile;

/**
 * Declares no toArray(), so it publishes what Profile's toArray() writes: every column, `menu_settings` through its
 * cast class's #[TsType] import, no accessor Profile does not append, and its `user` relation only when loaded.
 *
 * @mixin Profile
 */
class ProfileSummaryResource extends JsonResource {}
