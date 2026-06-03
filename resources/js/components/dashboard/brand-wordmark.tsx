import { cn } from '@/lib/utils';

export function BrandWordmark({ className }: { className?: string }) {
    return (
        <span className={cn('font-semibold tracking-tight', className)}>
            Re<span className="text-amber-600 dark:text-amber-400">:</span>Mind
        </span>
    );
}
