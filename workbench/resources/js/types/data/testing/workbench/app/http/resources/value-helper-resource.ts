/**
 * Laravel's value helpers, typed as json_encode() writes what they return: a Carbon as its date string, a Stringable
 * and a URL as strings, and a collection as the list or object its items encode as. `translated` stays unknown,
 * since __() can return an array.
 *
 * @see Workbench\App\Http\Resources\ValueHelperResource
 */
export interface ValueHelperResource
{
    generated_at: string;
    day: string;
    title: string;
    link: string;
    empty_list: never[];
    list: number[];
    record: { a: number };
    names: string[];
    count_or_since: number | string;
    translated: unknown;
}
