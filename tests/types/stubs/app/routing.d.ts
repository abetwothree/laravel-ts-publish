// Members exist because the generated tree writes `Pick<Routable, "store" | "update">`,
// and `Pick` constrains its key argument to `keyof T`.
export interface Routable {
    store: unknown;
    update: unknown;
}
