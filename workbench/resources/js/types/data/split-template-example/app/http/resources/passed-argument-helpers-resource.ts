/**
 * Helpers whose parameters declare no type, read as values: each body reads what its call passes.
 *
 * @see Workbench\App\Http\Resources\PassedArgumentHelpersResource
 */
export interface PassedArgumentHelpersResource
{
    row: { pk: number; title: string };
    author_card: { name: string; email: string };
    static_row: { static_pk: number };
    outer: { inner: { pk: number; title: string } };
}
