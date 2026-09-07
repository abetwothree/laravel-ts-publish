// Members exist so the generated `Omit<Timestamps, "created_at" | "updated_at">` actually removes
// something rather than omitting from an empty type.
export interface Timestamps {
    created_at: unknown;
    updated_at: unknown;
}
