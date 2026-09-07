/** @see Workbench\App\Events\DeclaredPropsEvent */
export interface DeclaredPropsEvent {
    label?: string;
    tags: string[];
    id: number;
    note: string | null;
}
