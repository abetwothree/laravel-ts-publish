/**
 * Helpers whose declarations are vague or missing, each typed from what its own returns hold: a scalar, a list
 * literal, list pipelines, an attribute read and returned variables.
 *
 * @see Workbench\App\Http\Resources\PostHelperBodyResource
 */
export interface PostHelperBodyResource
{
    shout_untyped: string;
    stages: string[];
    history: string[];
    ids: number[];
    untyped_title: string;
    closure_first: { title: string; active: number };
    mixed_meta: { a: number } | { a: number; b: string };
}
