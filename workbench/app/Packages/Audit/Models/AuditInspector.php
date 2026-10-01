<?php

declare(strict_types=1);

namespace Workbench\App\Packages\Audit\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Named only by a morphTo docblock generic on Facility, so it is published on demand through that generic.
 */
class AuditInspector extends Model
{
    protected $fillable = ['name'];
}
