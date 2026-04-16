/** Exercises the ModelTransformer unknown-type fallback paths using untyped SQLite columns. */
export interface UntypedColumn
{
    // Columns
    id: number | null;
    /** Accessor with no return type on the getter closure — resolves to unknown, exercises the 'attribute'/'accessor' match arm */
    accessor_col: unknown | null;
    cast_col: unknown | null;
    /** Accessor with no return type on an untyped nullable column — exercises the nullable fallback (appends ' | null'). */
    nullable_accessor_col: unknown | null;
}
