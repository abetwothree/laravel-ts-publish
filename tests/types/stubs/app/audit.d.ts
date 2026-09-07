// Members exist because the generated tree writes `Pick<Auditable, "created_by" | "updated_by">`,
// and `Pick` constrains its key argument to `keyof T`.
export interface Auditable {
    created_by: unknown;
    updated_by: unknown;
}
