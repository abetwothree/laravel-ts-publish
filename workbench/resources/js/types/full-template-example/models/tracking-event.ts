export interface TrackingEvent
{
    // Columns
    id: number;
    shipment_id: number;
    status: string;
    location: string | null;
    description: string | null;
    occurred_at: string;
    created_at: string | null;
    updated_at: string | null;
    // Mutators
    changes: { attributes: Record<string, unknown>; old: Record<string, unknown> };
    diff: unknown[] | Record<string, unknown>;
}
