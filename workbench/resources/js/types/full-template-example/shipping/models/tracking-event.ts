import type { Shipment } from '.';

/**
 * @see Workbench\Shipping\Models\TrackingEvent
 */
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
    // Relations
    shipment: Shipment;
    // Counts
    shipment_count: number;
    // Exists
    shipment_exists: boolean;
}
