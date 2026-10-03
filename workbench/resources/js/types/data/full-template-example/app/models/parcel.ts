import { type AsEnum } from '@tolki/ts';

import { Priority } from '../enums';
import type { PriorityType } from '../enums';
import type { Order, User } from '.';

/**
 * Shares three keys between attributes and relations, each attribute bringing an import of its own: a `handler`
 * column cast to an enum, an appended `sender` accessor typed by the same enum, and a `manifest` column typed by a
 * `#[TsCasts]` import. The `priority` column is cast to an enum no relation shares.
 *
 * @see Workbench\App\Models\Parcel
 */
export interface Parcel
{
    // Columns
    id: number;
    handler_id: number;
    sender_id: number;
    manifest_id: number;
    priority: PriorityType;
    created_at: string | null;
    updated_at: string | null;
    // Relations
    handler: User;
    sender: User;
    manifest: Order;
    // Counts
    handler_count: number;
    sender_count: number;
    manifest_count: number;
    // Exists
    handler_exists: boolean;
    sender_exists: boolean;
    manifest_exists: boolean;
}

export interface ParcelResource extends Omit<Parcel, 'priority'>
{
    priority: AsEnum<typeof Priority>;
}
