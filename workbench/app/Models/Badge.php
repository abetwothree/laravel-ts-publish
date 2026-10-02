<?php

declare(strict_types=1);

namespace Workbench\App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Workbench\App\Enums\Clearance;
use Workbench\App\Enums\Grade as GradeEnum;
use Workbench\Crm\Enums\ClearanceType;

/**
 * Imports names that cross between types and consts: Clearance's type and ClearanceType's const are both
 * `ClearanceType`, and the Grade model's type and the Grade enum's const are both `Grade`.
 */
class Badge extends Model
{
    protected $fillable = ['label', 'clearance', 'clearance_type', 'minimum_grade', 'grade_id'];

    /**
     * The grade record the badge was awarded for.
     *
     * @return BelongsTo<Grade, $this>
     */
    public function grade(): BelongsTo
    {
        return $this->belongsTo(Grade::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'clearance' => Clearance::class,
            'clearance_type' => ClearanceType::class,
            'minimum_grade' => GradeEnum::class,
        ];
    }
}
