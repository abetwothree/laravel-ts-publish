import type { Shipment } from '../../models';

/** Exercises: direct enum property access ($this->status), whenLoaded bare on same-module relation (Shipment). */
export interface TrackingEventResource
{
    id: number;
    status: string;
    location: string | null;
    description: string | null;
    occurred_at: string;
    shipment?: Shipment;
}
