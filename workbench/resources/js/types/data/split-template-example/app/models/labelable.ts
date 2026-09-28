import type { Artist, Venue } from '.';

/** @see Workbench\App\Models\Labelable */
export interface Labelable
{
    id: number;
    label_id: number;
    labelable_type: string;
    labelable_id: number;
}

export interface LabelableRelations
{
    // Relations
    /** Polymorphic parent (Venue or Artist) the pivot row labels */
    labelable: Artist | Venue;
    // Counts
    labelable_count: number;
    // Exists
    labelable_exists: boolean;
}

export interface LabelableAll extends Labelable, LabelableRelations {}
