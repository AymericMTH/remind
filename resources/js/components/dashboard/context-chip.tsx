import type { ReminderContext } from '@/types/remind';

export function ContextChip({ context }: { context: ReminderContext | null }) {
    if (!context) return null;

    const label =
        context.repo_label ??
        (context.cwd ? context.cwd.split('/').filter(Boolean).pop() : null);

    const fileFragment = context.file
        ? `${context.file}${context.line_start ? `:${context.line_start}` : ''}`
        : null;

    const text = [label, fileFragment].filter(Boolean).join(' · ');

    if (!text) return null;

    return (
        <span className="font-mono text-xs bg-amber-100 dark:bg-amber-900/30 text-amber-900 dark:text-amber-200 rounded px-1.5 py-0.5 truncate max-w-[280px]">
            📁 {text}
        </span>
    );
}
