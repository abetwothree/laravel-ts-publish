/**
 * Helpers whose parameters declare no type, read as values: each body reads what its call passes, a first-class
 * callable's included, which Laravel calls with the loaded relation, the attribute or the transformed value.
 *
 * @see Workbench\App\Http\Resources\PassedArgumentHelpersResource
 */
export interface PassedArgumentHelpersResource
{
    row: { pk: number; title: string };
    author_card: { name: string; email: string };
    static_row: { static_pk: number };
    outer: { inner: { pk: number; title: string } };
    loaded_card?: { name: string; email: string };
    loaded_count?: { count: number };
    has_title?: { value: string };
    transformed?: { value: string };
}
