import { router } from '@inertiajs/react';
import { forwardRef, useImperativeHandle, useRef, useState } from 'react';

export type AddReminderRowHandle = {
    focus: () => void;
};

type Props = {
    listId: number;
};

export const AddReminderRow = forwardRef<AddReminderRowHandle, Props>(
    function AddReminderRow({ listId }, ref) {
        const [title, setTitle] = useState('');
        const [submitting, setSubmitting] = useState(false);
        const inputRef = useRef<HTMLInputElement>(null);

        useImperativeHandle(ref, () => ({
            focus: () => inputRef.current?.focus(),
        }));

        function submit() {
            const t = title.trim();

            if (!t) {
                return;
            }

            setSubmitting(true);
            router.post(
                '/reminders',
                { list_id: listId, title: t },
                {
                    preserveScroll: true,
                    onSuccess: () => {
                        setTitle('');
                        inputRef.current?.focus();
                    },
                    onFinish: () => setSubmitting(false),
                },
            );
        }

        return (
            <div className="mx-4 mb-2 flex items-center gap-2 rounded border border-amber-100/60 bg-amber-50/70 px-3 py-2 dark:border-amber-900/30 dark:bg-amber-950/20">
                <span className="text-amber-700 dark:text-amber-300">+</span>
                <input
                    ref={inputRef}
                    value={title}
                    onChange={(e) => setTitle(e.target.value)}
                    onKeyDown={(e) => {
                        if (e.key === 'Enter') {
                            submit();
                        }

                        if (e.key === 'Escape') {
                            setTitle('');
                            inputRef.current?.blur();
                        }
                    }}
                    placeholder="Add a reminder…"
                    disabled={submitting}
                    className="flex-1 bg-transparent text-sm outline-none placeholder:text-amber-900/40 dark:placeholder:text-amber-200/40"
                />
            </div>
        );
    },
);
