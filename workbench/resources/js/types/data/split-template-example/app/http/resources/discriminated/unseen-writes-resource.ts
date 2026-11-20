/**
 * Literals whose read an item between its keys writes where the literal cannot see it: a helper's body, the model
 * through `$this->resource`, and a `->` write of a `?->` read. Each stays flat, while a helper writing nothing keeps
 * its literal split (GH-157).
 *
 * @see Workbench\App\Http\Resources\Discriminated\UnseenWritesResource
 */
export interface UnseenWritesResource
{
    by_helper: { is_pinned: boolean; reset: boolean; title: string | null };
    by_proxy: { is_pinned: boolean; reset: boolean; title: string | null };
    by_arrow: { enabled: boolean | null; off: boolean; title: string | null };
    by_reader: { is_pinned: true; label: string; title: string } | { is_pinned: false; label: string; title: null };
}
