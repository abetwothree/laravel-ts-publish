/** @see Workbench\App\Events\OrderShipped */
export interface OrderShipped {
    orderId: number;
    trackingNumber: `${string}-${string}-${string}`;
    carrier: string;
    metadata?: Record<string, unknown>;
}
