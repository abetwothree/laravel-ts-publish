import type { Order, User } from '.';

/**
 * Shares keys between attributes and relations: a `supervisor` column beside a `supervisor()` relation, and an
 * `orders_count` counter-cache column beside the `orders()` relation's own count key.
 *
 * @see Workbench\App\Models\Depot
 */
export interface Depot
{
    // Columns
    id: number;
    name: string;
    supervisor_id: number | null;
    orders_count: number | null;
    created_at: string | null;
    updated_at: string | null;
    // Relations
    /** The user who runs the depot. */
    supervisor: User | null;
    /** The orders the depot ships. */
    orders: Order[];
    // Counts
    supervisor_count: number;
    // Exists
    supervisor_exists: boolean;
    orders_exists: boolean;
}
