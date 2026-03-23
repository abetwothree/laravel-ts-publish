import { type AsEnum } from '@tolki/enum';

import { Carrier, ShipmentStatus } from '../enums';
import type { Order } from '../models';
import type { TrackingEventResource } from './';

/** Exercises: EnumResource::make on two enums (Carrier, Status), when, whenNotNull, whenLoaded bare cross-module (App\Order), Resource::collection, whenCounted, whenAggregated, mergeWhen with complex expression. */
export interface ShipmentResource
{
    id: number;
    tracking_number: string | null;
    carrier: AsEnum<typeof Carrier>;
    status: AsEnum<typeof ShipmentStatus>;
    weight_grams: number | null;
    estimated_delivery_at?: string | null;
    shipped_at?: string | null;
    delivered_at?: string | null;
    order?: Order;
    tracking_events?: TrackingEventResource[];
    tracking_events_count?: number;
    events_total?: number;
    transit_time?: unknown;
}
