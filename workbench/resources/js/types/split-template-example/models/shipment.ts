import { type AsEnum } from '@tolki/enum';

import type { Carrier, CarrierType, ShipmentStatus, ShipmentStatusType } from '../enums';
import type { Order, TrackingEvent } from './';

export interface Shipment
{
    id: number;
    order_id: number;
    tracking_number: string | null;
    carrier: CarrierType;
    status: ShipmentStatusType;
    weight_grams: number | null;
    estimated_delivery_at: string | null;
    shipped_at: string | null;
    delivered_at: string | null;
    created_at: string | null;
    updated_at: string | null;
}

export interface ShipmentResource extends Omit<Shipment, 'carrier' | 'status'>
{
    carrier: AsEnum<typeof Carrier>;
    status: AsEnum<typeof ShipmentStatus>;
}

export interface ShipmentRelations
{
    // Relations
    order: Order;
    tracking_events: TrackingEvent[];
    // Counts
    order_count: number;
    tracking_events_count: number;
    // Exists
    order_exists: boolean;
    tracking_events_exists: boolean;
}

export interface ShipmentAll extends Shipment, ShipmentRelations {}

export interface ShipmentAllResource extends ShipmentResource, ShipmentRelations {}
