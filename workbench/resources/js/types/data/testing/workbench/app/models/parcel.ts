import { type AsEnum } from '@tolki/ts';

import { Priority, Role } from '../enums';
import type { ParcelManifest } from '@js/types/manifest';
import type { PriorityType, RoleType } from '../enums';
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
    id: number;
    handler: RoleType;
    handler_id: number;
    sender_id: number;
    manifest: ParcelManifest;
    manifest_id: number;
    priority: PriorityType;
    created_at: string | null;
    updated_at: string | null;
    /** The role the parcel was sent under. */
    sender: RoleType;
}

export interface ParcelResource extends Omit<Parcel, 'handler' | 'priority' | 'sender'>
{
    handler: AsEnum<typeof Role>;
    priority: AsEnum<typeof Priority>;
    sender: AsEnum<typeof Role>;
}

export interface ParcelRelations
{
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

export interface ParcelAll extends Omit<Parcel, 'handler' | 'sender' | 'manifest'>, ParcelRelations {}

export interface ParcelAllResource extends Omit<ParcelResource, 'handler' | 'sender' | 'manifest'>, ParcelRelations {}
