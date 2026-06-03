import type { ReactNode } from 'react';
import { Toaster } from '@/components/ui/sonner';

export function DashboardLayout({ children }: { children: ReactNode }) {
    return (
        <div className="flex h-screen bg-amber-50/40 text-foreground dark:bg-amber-950/10">
            {children}
            <Toaster richColors position="top-right" />
        </div>
    );
}
