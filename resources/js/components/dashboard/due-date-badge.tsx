function isToday(date: string): boolean {
    const today = new Date().toISOString().slice(0, 10);

    return date === today;
}

function isOverdue(date: string): boolean {
    const today = new Date().toISOString().slice(0, 10);

    return date < today;
}

function formatDate(date: string): string {
    const d = new Date(date + 'T00:00:00');

    return d.toLocaleDateString(undefined, { month: 'short', day: 'numeric' });
}

export function DueDateBadge({ date }: { date: string | null }) {
    if (!date) {
        return null;
    }

    const tone = isOverdue(date)
        ? 'text-red-600 dark:text-red-400'
        : isToday(date)
          ? 'text-amber-700 dark:text-amber-300 font-medium'
          : 'text-muted-foreground';

    const label = isToday(date)
        ? 'Today'
        : isOverdue(date)
          ? `Overdue · ${formatDate(date)}`
          : formatDate(date);

    return <span className={`text-xs ${tone}`}>{label}</span>;
}
