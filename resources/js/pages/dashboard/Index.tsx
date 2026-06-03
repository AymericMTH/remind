import { Head } from '@inertiajs/react';
import { useCallback, useRef, useState } from 'react';
import type { AddReminderRowHandle } from '@/components/dashboard/add-reminder-row';
import { MainPane } from '@/components/dashboard/main-pane';
import { ShortcutsCheatsheet } from '@/components/dashboard/shortcuts-cheatsheet';
import { Sidebar } from '@/components/dashboard/sidebar';
import { useKeyboardShortcuts } from '@/hooks/use-keyboard-shortcuts';
import { useSelectedList } from '@/hooks/use-selected-list';
import { DashboardLayout } from '@/layouts/dashboard-layout';
import type { DashboardPageProps } from '@/types/remind';

export default function Dashboard(props: DashboardPageProps) {
    const {
        lists,
        selectedList,
        reminders,
        completedReminders,
        curatedColors,
    } = props;
    const { select } = useSelectedList();
    const [, setHighlightIdx] = useState<number | null>(null);
    const [cheatsheetOpen, setCheatsheetOpen] = useState(false);
    const addRowRef = useRef<AddReminderRowHandle | null>(null);

    const moveHighlight = useCallback(
        (delta: number) => {
            if (reminders.length === 0) {
                return;
            }

            setHighlightIdx((cur) => {
                const next =
                    cur === null
                        ? 0
                        : Math.max(
                              0,
                              Math.min(reminders.length - 1, cur + delta),
                          );

                return next;
            });
        },
        [reminders.length],
    );

    useKeyboardShortcuts({
        onNew: () => addRowRef.current?.focus(),
        onArrowUp: () => moveHighlight(-1),
        onArrowDown: () => moveHighlight(1),
        onEscape: () => setHighlightIdx(null),
        onCheatsheet: () => setCheatsheetOpen(true),
        onJumpToInbox: () => {
            const inbox = lists.find((l) => l.is_inbox);

            if (inbox) {
                select(inbox.id);
            }
        },
        onJumpToListAtIndex: (idx) => {
            const l = lists[idx];

            if (l) {
                select(l.id);
            }
        },
    });

    return (
        <DashboardLayout>
            <Head title={`Re:Mind — ${selectedList.name}`} />
            <Sidebar
                lists={lists}
                selectedListId={selectedList.id}
                curatedColors={curatedColors}
                onSelect={select}
            />
            <MainPane
                list={selectedList}
                reminders={reminders}
                completedReminders={completedReminders}
                addRef={addRowRef}
            />
            <ShortcutsCheatsheet
                open={cheatsheetOpen}
                onClose={() => setCheatsheetOpen(false)}
            />
        </DashboardLayout>
    );
}
