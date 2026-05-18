declare module '@inertiajs/core' {
    export interface InertiaConfig {
        sharedPageProps: { auth: { user: { id: number, name: string, email: string } | null }, flash: { success: string | null, error: string | null }, appName: string };
        errorValueType: string[];
    }
}
