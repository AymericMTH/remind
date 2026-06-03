import { router } from '@inertiajs/react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import type { ReminderList } from '@/types/remind';

type Strategy = 'move_to_inbox' | 'cascade';

type Props = {
    open: boolean;
    onClose: () => void;
    list: ReminderList;
    reminderCount: number;
};

export function DeleteListDialog({
    open,
    onClose,
    list,
    reminderCount,
}: Props) {
    const [strategy, setStrategy] = useState<Strategy>('move_to_inbox');
    const [submitting, setSubmitting] = useState(false);

    function confirm() {
        setSubmitting(true);
        router.delete(`/lists/${list.id}`, {
            data: { strategy },
            preserveScroll: true,
            onFinish: () => {
                setSubmitting(false);
                onClose();
            },
        });
    }

    return (
        <Dialog open={open} onOpenChange={(o) => !o && onClose()}>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Delete &quot;{list.name}&quot;</DialogTitle>
                    <DialogDescription>
                        What should happen to the {reminderCount} reminder
                        {reminderCount === 1 ? '' : 's'} in this list?
                    </DialogDescription>
                </DialogHeader>

                <div className="my-2 space-y-3">
                    <Label className="flex cursor-pointer items-start gap-3">
                        <input
                            type="radio"
                            name="strategy"
                            value="move_to_inbox"
                            checked={strategy === 'move_to_inbox'}
                            onChange={() => setStrategy('move_to_inbox')}
                            className="mt-1"
                        />
                        <span>
                            <span className="block font-medium">
                                Move them to Inbox
                            </span>
                            <span className="block text-sm text-muted-foreground">
                                The list disappears, reminders move to Inbox.
                            </span>
                        </span>
                    </Label>
                    <Label className="flex cursor-pointer items-start gap-3">
                        <input
                            type="radio"
                            name="strategy"
                            value="cascade"
                            checked={strategy === 'cascade'}
                            onChange={() => setStrategy('cascade')}
                            className="mt-1"
                        />
                        <span>
                            <span className="block font-medium text-red-700 dark:text-red-400">
                                Delete list and all reminders
                            </span>
                            <span className="block text-sm text-muted-foreground">
                                Permanent. Cannot be undone.
                            </span>
                        </span>
                    </Label>
                </div>

                <DialogFooter>
                    <Button
                        type="button"
                        variant="outline"
                        onClick={onClose}
                        disabled={submitting}
                    >
                        Cancel
                    </Button>
                    <Button
                        type="button"
                        variant={
                            strategy === 'cascade' ? 'destructive' : 'default'
                        }
                        onClick={confirm}
                        disabled={submitting}
                    >
                        {strategy === 'cascade'
                            ? 'Delete everything'
                            : 'Move and delete'}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
