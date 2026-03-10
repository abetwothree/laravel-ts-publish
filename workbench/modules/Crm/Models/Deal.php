<?php

namespace Workbench\Crm\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Workbench\App\Enums\Status;

class Deal extends Model
{
    protected $table = 'crm_deals';

    protected $fillable = [
        'customer_id',
        'admin_id',
        'title',
        'status',
        'value',
    ];

    protected function casts(): array
    {
        return [
            'status' => Status::class,
            'value' => 'decimal:2',
        ];
    }

    /**
     * The CRM customer this deal belongs to.
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'customer_id');
    }

    /**
     * The system admin/user managing this deal.
     */
    public function admin(): BelongsTo
    {
        return $this->belongsTo(\Workbench\App\Models\User::class, 'admin_id');
    }
}
