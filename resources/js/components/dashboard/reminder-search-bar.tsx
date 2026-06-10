import { Search, X } from 'lucide-react';
import { forwardRef, useEffect, useImperativeHandle, useRef } from 'react';
import type { SearchMode } from './search-state';

export type ReminderSearchBarHandle = {
    focus: () => void;
};

type Props = {
    mode: SearchMode;
    listName: string;
    query: string;
    includeCompleted: boolean;
    matchCount: number;
    totalCount: number;
    onChange: (value: string) => void;
    onToggleCompleted: () => void;
    onClose: () => void;
};

export const ReminderSearchBar = forwardRef<ReminderSearchBarHandle, Props>(
    function ReminderSearchBar(
        {
            mode,
            listName,
            query,
            includeCompleted,
            matchCount,
            totalCount,
            onChange,
            onToggleCompleted,
            onClose,
        },
        ref,
    ) {
        const inputRef = useRef<HTMLInputElement>(null);

        useImperativeHandle(ref, () => ({
            focus: () => inputRef.current?.focus(),
        }));

        useEffect(() => {
            queueMicrotask(() => inputRef.current?.focus());
        }, [mode]);

        const placeholder =
            mode === 'current'
                ? `Search in ${listName}…`
                : 'Search across all lists…';

        return (
            <div className="mx-4 mt-2 mb-2 flex items-center gap-2 rounded border border-amber-200 bg-white px-3 py-2 shadow-sm dark:border-amber-900/40 dark:bg-amber-950/30">
                <Search className="h-4 w-4 text-amber-700 dark:text-amber-300" />
                <input
                    ref={inputRef}
                    type="text"
                    value={query}
                    onChange={(e) => onChange(e.target.value)}
                    onKeyDown={(e) => {
                        if (e.key === 'Escape') {
                            e.preventDefault();

                            if (query.length > 0) {
                                onChange('');

                                return;
                            }

                            onClose();

                            return;
                        }

                        if (e.key === 'Tab') {
                            e.preventDefault();
                            onToggleCompleted();
                        }
                    }}
                    placeholder={placeholder}
                    className="flex-1 bg-transparent text-sm outline-none placeholder:text-amber-900/40 dark:placeholder:text-amber-200/40"
                />
                <span className="text-xs text-muted-foreground tabular-nums">
                    {matchCount} / {totalCount}
                </span>
                <button
                    type="button"
                    onClick={onToggleCompleted}
                    className={`rounded px-2 py-0.5 text-xs ${
                        includeCompleted
                            ? 'bg-amber-200 text-amber-900 dark:bg-amber-700/40 dark:text-amber-100'
                            : 'bg-amber-50 text-amber-700 dark:bg-amber-900/30 dark:text-amber-300'
                    }`}
                    title="Tab toggles completed"
                >
                    [Tab] done: {includeCompleted ? 'on' : 'off'}
                </button>
                <button
                    type="button"
                    onClick={onClose}
                    aria-label="Close search"
                    className="text-amber-700 hover:text-amber-900 dark:text-amber-300 dark:hover:text-amber-100"
                >
                    <X className="h-4 w-4" />
                </button>
            </div>
        );
    },
);
