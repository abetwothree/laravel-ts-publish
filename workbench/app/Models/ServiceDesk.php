<?php

declare(strict_types=1);

namespace Workbench\App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Workbench\Crm\Models\User as CrmUser;

/**
 * A help-desk ticket linked to a customer Order and optionally assigned to a CRM agent.
 *
 * Exercises the inline model FQCN collision scenario: two relations to classes with the
 * same basename (App\Models\User via order.user and Crm\Models\User via crm_agent) force
 * import aliasing. The `order_requester` property is an inline object produced by
 * `$this->order?->only(['user'])`, whose nested model token must be rewritten via
 * the inlineModelFqcns tracking path.
 */
class ServiceDesk extends Model
{
    protected $fillable = [
        'title',
        'order_id',
        'crm_agent_id',
    ];

    /**
     * The customer order this desk ticket belongs to.
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * The CRM user assigned as the support agent (optional).
     */
    public function crmAgent(): BelongsTo
    {
        return $this->belongsTo(CrmUser::class, 'crm_agent_id');
    }
}
