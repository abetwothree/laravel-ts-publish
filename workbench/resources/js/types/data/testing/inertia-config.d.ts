import { type AsEnum } from '@tolki/ts';
import { Role } from './workbench/app/enums';

declare global {
    namespace Inertia {
        type SharedData = { auth: { user: { id: number; name: string; email: string } | null }, flash: { success: string | null; error: string | null }, appName: string, role: AsEnum<typeof Role>, filters?: Record<string, string> };
    }
}

declare module '@inertiajs/core' {
    export interface InertiaConfig {
        sharedPageProps: { auth: { user: { id: number; name: string; email: string } | null }, flash: { success: string | null; error: string | null }, appName: string, role: AsEnum<typeof Role>, filters?: Record<string, string> };
        errorValueType: string[];
    }
}

export {};
