/**
 * Reads helpers that add keys to the array they are passed through each call path: a container instance, a static
 * call and the resource's own method.
 *
 * @see Workbench\App\Http\Resources\DecoratedPayloadResource
 */
export interface DecoratedPayloadResource
{
    decorated: { seen: boolean; [key: string]: unknown };
    conditional: { flag?: string; [key: string]: unknown };
    static: { seen: boolean; [key: string]: unknown };
    helper: { seen: boolean; [key: string]: unknown };
    passed_through: Record<string, string> | never[];
    rebuilt: { id: number; seen: boolean };
}
