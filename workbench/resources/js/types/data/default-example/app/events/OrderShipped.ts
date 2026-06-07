/** @see Workbench\App\Events\OrderShipped */
export interface OrderShipped {
    orderId: number;
    trackingNumber: string;
    carrier: string;
}
