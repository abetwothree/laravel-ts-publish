/**
 * Returns an array union whole: the filtered attributes keep their own types, and the defaults add only the keys the
 * filter leaves out.
 *
 * @see Workbench\App\Http\Resources\HandoverDefaultsResource
 */
export interface HandoverDefaultsResource
{
    id: number;
    sender_id: number | null;
    note: string;
}
