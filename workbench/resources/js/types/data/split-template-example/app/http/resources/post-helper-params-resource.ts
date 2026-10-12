import type { StatusType } from '../../enums';

/**
 * Helpers whose typed parameters their bodies read, through every reading of a helper body: a spread, a value, a
 * nested call, a static helper and a trait's.
 *
 * @see Workbench\App\Http\Resources\PostHelperParamsResource
 */
export interface PostHelperParamsResource
{
    id: number;
    title: string;
    extra: number;
    summary: { id: number; title: string; extra: number };
    card: { card_id: number; card_title: string };
    nested: { inner: { card_id: number; card_title: string } };
    static_card: { static_id: number };
    headline_id: number;
    headline: string;
    maybe: { maybe_title: string | null };
    status_label: { label: string; value: number };
    status_payload: { payload_status: unknown; payload_name: string };
    payload_status: StatusType;
    payload_name: string;
}
