import type { ReminderContext } from '@/types/remind';

export function ContextChip({ context }: { context: ReminderContext | null }) {
    if (!context) {
        return null;
    }

    const label =
        context.repo_label ??
        (context.cwd ? context.cwd.split('/').filter(Boolean).pop() : null);

    const fileFragment = context.file
        ? `${context.file}${context.line_start ? `:${context.line_start}` : ''}`
        : null;

    const text = [label, fileFragment].filter(Boolean).join(' · ');

    if (!text) {
        return null;
    }

    return (
        <span className="max-w-[280px] truncate rounded bg-amber-100 px-1.5 py-0.5 font-mono text-xs text-amber-900 dark:bg-amber-900/30 dark:text-amber-200">
            📁 {text}
        </span>
    );
}
