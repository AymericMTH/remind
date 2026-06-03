import { Toaster } from '@/components/ui/sonner';
import type { ReactNode } from 'react';

export function DashboardLayout({ children }: { children: ReactNode }) {
    return (
        <div className="flex h-screen bg-amber-50/40 dark:bg-amber-950/10 text-foreground">
            {children}
            <Toaster richColors position="top-right" />
        </div>
    );
}
