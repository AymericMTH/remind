import { router } from '@inertiajs/react';
import { MoreHorizontal } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { Button } from '@/components/ui/button';
import type { ReminderList } from '@/types/remind';
import { ColorPicker } from './color-picker';
import { DeleteListDialog } from './delete-list-dialog';
import { ListContextMenu } from './list-context-menu';

type Props = {
    list: ReminderList;
    selected: boolean;
    reminderCount: number;
    curatedColors: string[];
    onSelect: () => void;
};

export function SidebarListItem({
    list,
    selected,
    reminderCount,
    curatedColors,
    onSelect,
}: Props) {
    const [renaming, setRenaming] = useState(false);
    const [draft, setDraft] = useState(list.name);
    const [colorOpen, setColorOpen] = useState(false);
    const [deleteOpen, setDeleteOpen] = useState(false);
    const inputRef = useRef<HTMLInputElement>(null);

    useEffect(() => {
        if (renaming) {
            inputRef.current?.select();
        }
    }, [renaming]);

    function saveName() {
        const name = draft.trim();

        if (!name || name === list.name) {
            setRenaming(false);
            setDraft(list.name);

            return;
        }

        router.put(
            `/lists/${list.id}`,
            { name },
            {
                preserveScroll: true,
                onSuccess: () => setRenaming(false),
            },
        );
    }

    function saveColor(color: string | null) {
        router.put(
            `/lists/${list.id}`,
            { color },
            {
                preserveScroll: true,
                onFinish: () => setColorOpen(false),
            },
        );
    }

    const dot = list.is_inbox ? '📥' : null;

    return (
        <>
            <div
                onClick={() => !renaming && onSelect()}
                onContextMenu={(e) => {
                    e.preventDefault();
                    (
                        e.currentTarget.querySelector(
                            '[data-context-trigger]',
                        ) as HTMLButtonElement | null
                    )?.click();
                }}
                style={
                    selected && !list.is_inbox && list.color
                        ? { borderLeftColor: list.color }
                        : undefined
                }
                className={`group -mx-2 flex cursor-pointer items-center gap-2 rounded border-l-[3px] px-2 py-1.5 text-sm ${
                    selected
                        ? 'border-l-current bg-amber-100 dark:bg-amber-900/30'
                        : 'border-l-transparent hover:bg-amber-50 dark:hover:bg-amber-950/20'
                }`}
            >
                <span className="text-sm">
                    {dot ?? (
                        <span
                            className="inline-block h-2 w-2 rounded-full"
                            style={{ backgroundColor: list.color ?? '#999' }}
                        />
                    )}
                </span>
                {renaming ? (
                    <input
                        ref={inputRef}
                        value={draft}
                        onChange={(e) => setDraft(e.target.value)}
                        onBlur={saveName}
                        onKeyDown={(e) => {
                            if (e.key === 'Enter') {
                                saveName();
                            }

                            if (e.key === 'Escape') {
                                setRenaming(false);
                                setDraft(list.name);
                            }
                        }}
                        onClick={(e) => e.stopPropagation()}
                        className="flex-1 border-b border-amber-500 bg-transparent text-sm outline-none"
                    />
                ) : (
                    <span className="flex-1 truncate">{list.name}</span>
                )}
                <span className="text-xs text-muted-foreground tabular-nums">
                    {reminderCount}
                </span>
                {!list.is_inbox && (
                    <ListContextMenu
                        onRename={() => setRenaming(true)}
                        onChangeColor={() => setColorOpen(true)}
                        onDelete={() => setDeleteOpen(true)}
                    >
                        <Button
                            data-context-trigger
                            variant="ghost"
                            size="icon"
                            className="-mr-1 h-5 w-5 opacity-0 group-hover:opacity-100"
                            onClick={(e) => e.stopPropagation()}
                        >
                            <MoreHorizontal className="h-3 w-3" />
                        </Button>
                    </ListContextMenu>
                )}
            </div>

            {colorOpen && !list.is_inbox && (
                <div className="-mx-2 rounded bg-amber-50 px-2 py-2 dark:bg-amber-950/20">
                    <ColorPicker
                        value={list.color}
                        onChange={saveColor}
                        swatches={curatedColors}
                    />
                </div>
            )}

            {!list.is_inbox && (
                <DeleteListDialog
                    open={deleteOpen}
                    onClose={() => setDeleteOpen(false)}
                    list={list}
                    reminderCount={reminderCount}
                />
            )}
        </>
    );
}
