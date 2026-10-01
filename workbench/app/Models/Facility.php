<?php

declare(strict_types=1);

namespace Workbench\App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Workbench\App\Packages\Audit\Models\AuditInspector;
use Workbench\App\Packages\Audit\Models\AuditTrail;
use Workbench\App\ValueObjects\OpaqueHandle;

/**
 * Relates to a model outside every configured model directory, which is published on demand, and to a
 * #[TsExclude]d model, whose relation is left out.
 */
class Facility extends Model
{
    protected $fillable = ['name'];

    /**
     * Published on demand: AuditTrail sits in no configured model directory.
     *
     * @return HasMany<AuditTrail, $this>
     */
    public function auditTrails(): HasMany
    {
        return $this->hasMany(AuditTrail::class);
    }

    /**
     * Left out: the related model carries #[TsExclude].
     *
     * @return HasMany<ExcludedModel, $this>
     */
    public function excludedRecords(): HasMany
    {
        return $this->hasMany(ExcludedModel::class, 'id');
    }

    /**
     * Names its targets in the docblock generic: AuditInspector sits in no configured model directory and is
     * published on demand, and the #[TsExclude]d model is left out of the union.
     *
     * @return MorphTo<AuditInspector|ExcludedModel|User, $this>
     */
    public function inspector(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Names a model that is never published, so it has no file to import.
     *
     * @return Attribute<ExcludedModel|null, never>
     */
    protected function lastExcluded(): Attribute
    {
        return Attribute::get(fn (): ?ExcludedModel => $this->excludedRecords->first());
    }

    /**
     * Names a class that is neither a model nor a resource, so no file is ever published for it.
     *
     * @return Attribute<OpaqueHandle, never>
     */
    protected function handle(): Attribute
    {
        return Attribute::get(fn (): OpaqueHandle => new OpaqueHandle);
    }
}
