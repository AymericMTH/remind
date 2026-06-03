import { router } from '@inertiajs/react';
import { ChevronRight } from 'lucide-react';
import { useState } from 'react';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Collapsible,
    CollapsibleContent,
    CollapsibleTrigger,
} from '@/components/ui/collapsible';
import type { Reminder } from '@/types/remind';

type Props = {
    reminders: Reminder[];
};

export function CompletedAccordion({ reminders }: Props) {
    const [open, setOpen] = useState(false);

    if (reminders.length === 0) {
        return null;
    }

    function uncomplete(id: number) {
        router.post(
            `/reminders/${id}/complete`,
            { done: false },
            { preserveScroll: true },
        );
    }

    return (
        <Collapsible
            open={open}
            onOpenChange={setOpen}
            className="mx-4 my-4 border-t border-amber-100/60 pt-3 dark:border-amber-900/30"
        >
            <CollapsibleTrigger className="flex items-center gap-1 text-sm text-muted-foreground hover:text-foreground">
                <ChevronRight
                    className={`h-4 w-4 transition-transform ${open ? 'rotate-90' : ''}`}
                />
                <span>
                    {open ? 'Hide' : 'Show'} {reminders.length} completed
                </span>
            </CollapsibleTrigger>
            <CollapsibleContent>
                <div className="mt-2 space-y-0.5">
                    {reminders.map((r) => (
                        <div
                            key={r.id}
                            className="flex items-center gap-3 rounded px-2 py-1.5 opacity-60 hover:bg-amber-50/50 hover:opacity-100 dark:hover:bg-amber-950/10"
                        >
                            <Checkbox
                                checked
                                onCheckedChange={() => uncomplete(r.id)}
                            />
                            <span className="flex-1 text-sm line-through">
                                {r.title}
                            </span>
                            <span className="text-xs text-muted-foreground">
                                {r.completed_at
                                    ? new Date(
                                          r.completed_at,
                                      ).toLocaleDateString()
                                    : ''}
                            </span>
                        </div>
                    ))}
                </div>
            </CollapsibleContent>
        </Collapsible>
    );
}
