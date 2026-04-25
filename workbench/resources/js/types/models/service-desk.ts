import type { Order, User } from './';

/** A help-desk ticket linked to a customer Order and optionally assigned to a CRM agent. Exercises the inline model FQCN collision scenario: two relations to classes with the same basename (App\Models\User via order.user and Crm\Models\User via crm_agent) force import aliasing. The `order_requester` property is an inline object produced by `$this->order?->only(['user'])`, whose nested model token must be rewritten via the inlineModelFqcns tracking path. */
export interface ServiceDesk
{
    id: number;
    title: string;
    order_id: number;
    crm_agent_id: number | null;
    created_at: string | null;
    updated_at: string | null;
}

export interface ServiceDeskRelations
{
    // Relations
    /** The customer order this desk ticket belongs to. */
    order: Order;
    /** The CRM user assigned as the support agent (optional). */
    crm_agent: User | null;
    // Counts
    order_count: number;
    crm_agent_count: number;
    // Exists
    order_exists: boolean;
    crm_agent_exists: boolean;
}

export interface ServiceDeskAll extends ServiceDesk, ServiceDeskRelations {}
