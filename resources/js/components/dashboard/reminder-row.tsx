import { router } from '@inertiajs/react';
import { AnimatePresence, motion } from 'framer-motion';
import { GripVertical } from 'lucide-react';
import { Checkbox } from '@/components/ui/checkbox';
import type { Reminder } from '@/types/remind';
import { ContextChip } from './context-chip';
import { DueDateBadge } from './due-date-badge';
import { ReminderEditorCard } from './reminder-editor-card';

type Props = {
    reminder: Reminder;
    expanded: boolean;
    highlighted: boolean;
    onToggleExpand: () => void;
    onCloseExpand: () => void;
    dragHandleProps?: React.HTMLAttributes<HTMLButtonElement>;
};

export function ReminderRow({
    reminder,
    expanded,
    highlighted,
    onToggleExpand,
    onCloseExpand,
    dragHandleProps,
}: Props) {
    function toggleDone(checked: boolean) {
        router.post(
            `/reminders/${reminder.id}/complete`,
            { done: checked },
            { preserveScroll: true },
        );
    }

    return (
        <div data-reminder-id={reminder.id}>
            <div
                onClick={(e) => {
                    if (
                        (e.target as HTMLElement).closest(
                            'button, input, [data-no-expand]',
                        )
                    ) {
                        return;
                    }

                    onToggleExpand();
                }}
                className={`group flex cursor-pointer items-center gap-3 border-b border-amber-100/60 px-4 py-2.5 hover:bg-amber-50/50 dark:border-amber-900/30 dark:hover:bg-amber-950/10 ${
                    highlighted
                        ? 'ring-2 ring-amber-300 ring-inset dark:ring-amber-700'
                        : ''
                }`}
            >
                <button
                    type="button"
                    {...dragHandleProps}
                    data-no-expand
                    className="cursor-grab opacity-0 group-hover:opacity-50 active:cursor-grabbing"
                    aria-label="Drag to reorder"
                >
                    <GripVertical className="h-4 w-4" />
                </button>
                <Checkbox
                    checked={false}
                    onCheckedChange={(c) => toggleDone(Boolean(c))}
                    data-no-expand
                    onClick={(e) => e.stopPropagation()}
                />
                <span className="flex-1 truncate text-sm">
                    {reminder.title}
                </span>
                <ContextChip context={reminder.context} />
                <DueDateBadge date={reminder.soft_due_date} />
            </div>
            <AnimatePresence initial={false}>
                {expanded && (
                    <motion.div
                        initial={{ height: 0, opacity: 0 }}
                        animate={{ height: 'auto', opacity: 1 }}
                        exit={{ height: 0, opacity: 0 }}
                        transition={{ duration: 0.15, ease: 'easeOut' }}
                        className="overflow-hidden"
                    >
                        <ReminderEditorCard
                            reminder={reminder}
                            onSaved={onCloseExpand}
                            onCancel={onCloseExpand}
                        />
                    </motion.div>
                )}
            </AnimatePresence>
        </div>
    );
}
