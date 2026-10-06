/**
 * Regression fixture for Task 17C: a fluent method chained onto a receiver that resolves to a
 * resource (`new self($x)`, `self::make($x)`, or a chain of both) keeps the receiver's type when
 * the method's declared return type hands the same instance back.
 *
 * @see Workbench\App\Http\Resources\FluentSelfResource
 */
export interface FluentSelfResource
{
    id: number;
    name: string;
    parent_fluent?: FluentSelfResource | null;
    parent_fluent_make?: FluentSelfResource | null;
    parent_fluent_chain?: FluentSelfResource | null;
    parent_fluent_docblock?: FluentSelfResource | null;
    parent_summary?: { id: number } | null;
    foreign_summary?: { slug: string } | null;
    parent_fluent_nullable?: FluentSelfResource | null;
}
