/**
 * @see Workbench\App\Models\TrackingEvent
 */
export interface TrackingEvent
{
    id: number;
    shipment_id: number;
    status: string;
    location: string | null;
    description: string | null;
    occurred_at: string;
    created_at: string | null;
    updated_at: string | null;
}

export interface TrackingEventMutators
{
    changes: { attributes: Record<string, unknown>; old: Record<string, unknown> };
    diff: unknown[] | Record<string, unknown>;
}

export interface TrackingEventAll extends TrackingEvent, TrackingEventMutators {}
