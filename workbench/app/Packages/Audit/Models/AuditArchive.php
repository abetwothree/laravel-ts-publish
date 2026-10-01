<?php

declare(strict_types=1);

namespace Workbench\App\Packages\Audit\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Has no table, as a package's unused feature does, so it is never published on demand.
 */
class AuditArchive extends Model
{
    protected $table = 'audit_archives';
}
