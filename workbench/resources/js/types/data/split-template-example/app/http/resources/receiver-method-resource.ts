/**
 * Method return types followed through every receiver kind: an enum cast, a Carbon cast, a local model,
 * a variable class for a static call, and an enum static constructor.
 *
 * @see Workbench\App\Http\Resources\ReceiverMethodResource
 */
export interface ReceiverMethodResource
{
    priority_label: string;
    priority_label_nullsafe: string | null;
    resource_priority_label: string;
    resource_priority_label_nullsafe: string | null;
    resource_published_date: string;
    published_date: string;
    author_morph: string | null;
    record_class: string;
    from_label: string;
}
