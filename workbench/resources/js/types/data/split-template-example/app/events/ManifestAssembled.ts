/** @see Workbench\App\Events\ManifestAssembled */
export interface ManifestAssembled {
    parcelId: number;
    carrier: string;
    priority?: string;
}
