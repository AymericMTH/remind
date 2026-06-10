import {
    closestCenter,
    DndContext,
    DragOverlay,
    PointerSensor,
    pointerWithin,
    useSensor,
    useSensors,
} from '@dnd-kit/core';
import type {
    CollisionDetection,
    DragEndEvent,
    DragStartEvent,
} from '@dnd-kit/core';
import { Head, router } from '@inertiajs/react';
import { useCallback, useMemo, useRef, useState } from 'react';
import type { AddReminderRowHandle } from '@/components/dashboard/add-reminder-row';
import {
    parseReminderId,
    parseSidebarListId,
} from '@/components/dashboard/dnd-ids';
import { MainPane } from '@/components/dashboard/main-pane';
import type {
    GlobalReminder,
    SearchMode,
    SearchState,
} from '@/components/dashboard/search-state';
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
        globalReminders: globalRemindersProp,
    } = props;
    const { select } = useSelectedList();
    const [, setHighlightIdx] = useState<number | null>(null);
    const [cheatsheetOpen, setCheatsheetOpen] = useState(false);
    const [activeDragId, setActiveDragId] = useState<string | null>(null);
    const [expandedId, setExpandedId] = useState<number | null>(null);
    const [search, setSearch] = useState<SearchState>({
        mode: null,
        query: '',
        includeCompleted: false,
    });
    const [globalLoading, setGlobalLoading] = useState(false);
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

    const openSearch = useCallback((mode: SearchMode) => {
        setSearch((cur) => ({
            mode,
            query: cur.mode === mode ? cur.query : '',
            includeCompleted: cur.includeCompleted,
        }));

        if (mode === 'global') {
            setGlobalLoading(true);
            router.reload({
                only: ['globalReminders'],
                onFinish: () => setGlobalLoading(false),
            });
        }
    }, []);

    const closeSearch = useCallback(() => {
        setSearch({ mode: null, query: '', includeCompleted: false });
    }, []);

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
        onSearchCurrent: () => openSearch('current'),
        onSearchAll: () => openSearch('global'),
    });

    const sensors = useSensors(
        useSensor(PointerSensor, { activationConstraint: { distance: 5 } }),
    );

    const collisionDetection: CollisionDetection = useCallback((args) => {
        const pointer = pointerWithin(args);

        if (pointer.length > 0) {
            return pointer;
        }

        return closestCenter(args);
    }, []);

    function handleDragStart(e: DragStartEvent) {
        setActiveDragId(String(e.active.id));
    }

    function handleDragCancel() {
        setActiveDragId(null);
    }

    function handleDragEnd(e: DragEndEvent) {
        setActiveDragId(null);

        if (!e.over || e.active.id === e.over.id) {
            return;
        }

        const activeRaw = e.active.id;
        const overRaw = e.over.id;
        const activeList = parseSidebarListId(activeRaw);
        const overList = parseSidebarListId(overRaw);
        const activeReminder = parseReminderId(activeRaw);
        const overReminder = parseReminderId(overRaw);

        if (activeList !== null && overList !== null) {
            const oldIdx = lists.findIndex((l) => l.id === activeList);
            const newIdx = lists.findIndex((l) => l.id === overList);

            if (oldIdx === -1 || newIdx === -1) {
                return;
            }

            const order = lists.slice();
            order.splice(newIdx, 0, order.splice(oldIdx, 1)[0]);
            router.put(
                '/lists/reorder',
                { order: order.map((l) => l.id) },
                { preserveScroll: true },
            );

            return;
        }

        if (activeReminder !== null && overList !== null) {
            if (overList === selectedList.id) {
                return;
            }

            router.put(
                `/reminders/${activeReminder}`,
                { list_id: overList },
                { preserveScroll: true },
            );

            return;
        }

        if (activeReminder !== null && overReminder !== null) {
            const oldIdx = reminders.findIndex((r) => r.id === activeReminder);
            const newIdx = reminders.findIndex((r) => r.id === overReminder);

            if (oldIdx === -1 || newIdx === -1) {
                return;
            }

            const reordered = reminders.slice();
            reordered.splice(newIdx, 0, reordered.splice(oldIdx, 1)[0]);
            router.put(
                '/reminders/reorder',
                {
                    list_id: selectedList.id,
                    order: reordered.map((r) => r.id),
                },
                { preserveScroll: true },
            );
        }
    }

    const overlay = useMemo(() => {
        if (!activeDragId) {
            return null;
        }

        const listId = parseSidebarListId(activeDragId);

        if (listId !== null) {
            const l = lists.find((x) => x.id === listId);

            return l ? <DragPreviewList name={l.name} color={l.color} /> : null;
        }

        const reminderId = parseReminderId(activeDragId);

        if (reminderId !== null) {
            const r = reminders.find((x) => x.id === reminderId);

            return r ? <DragPreviewReminder title={r.title} /> : null;
        }

        return null;
    }, [activeDragId, lists, reminders]);

    const globalReminders: GlobalReminder[] | null = globalRemindersProp
        ? globalRemindersProp
        : null;

    return (
        <DashboardLayout>
            <Head title={`Re:Mind — ${selectedList.name}`} />
            <DndContext
                sensors={sensors}
                collisionDetection={collisionDetection}
                onDragStart={handleDragStart}
                onDragCancel={handleDragCancel}
                onDragEnd={handleDragEnd}
            >
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
                    expandedId={expandedId}
                    onExpandChange={setExpandedId}
                    search={search}
                    onSearchClose={closeSearch}
                    onSearchQueryChange={(q) =>
                        setSearch((cur) => ({ ...cur, query: q }))
                    }
                    onSearchToggleCompleted={() =>
                        setSearch((cur) => ({
                            ...cur,
                            includeCompleted: !cur.includeCompleted,
                        }))
                    }
                    globalReminders={globalReminders}
                    globalLoading={globalLoading}
                    onPickGlobalResult={(listId, reminderId) => {
                        closeSearch();
                        setExpandedId(reminderId);

                        if (listId !== selectedList.id) {
                            select(listId);
                        }
                    }}
                />
                <DragOverlay>{overlay}</DragOverlay>
            </DndContext>
            <ShortcutsCheatsheet
                open={cheatsheetOpen}
                onClose={() => setCheatsheetOpen(false)}
            />
        </DashboardLayout>
    );
}

function DragPreviewList({
    name,
    color,
}: {
    name: string;
    color: string | null;
}) {
    return (
        <div className="flex w-56 items-center gap-2 rounded border border-amber-300 bg-white px-3 py-1.5 text-sm shadow-lg dark:border-amber-700 dark:bg-amber-950">
            <span
                className="inline-block h-2 w-2 rounded-full"
                style={{ backgroundColor: color ?? '#999' }}
            />
            <span className="truncate">{name}</span>
        </div>
    );
}

function DragPreviewReminder({ title }: { title: string }) {
    return (
        <div className="flex max-w-md items-center gap-2 rounded border border-amber-300 bg-white px-3 py-2 text-sm shadow-lg dark:border-amber-700 dark:bg-amber-950">
            <span className="truncate">{title}</span>
        </div>
    );
}
