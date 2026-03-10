import type { Order } from '../../app/models';
import type { CarrierType, ShipmentStatusType } from '../enums';
import type { TrackingEvent } from '.';

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
