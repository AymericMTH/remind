import {
    SortableContext,
    useSortable,
    verticalListSortingStrategy,
} from '@dnd-kit/sortable';
import { CSS } from '@dnd-kit/utilities';
import type { Reminder, ReminderList } from '@/types/remind';
import { AddReminderRow } from './add-reminder-row';
import type { AddReminderRowHandle } from './add-reminder-row';
import { CompletedAccordion } from './completed-accordion';
import { reminderItemId } from './dnd-ids';
import { EmptyState } from './empty-state';
import { MainPaneHeader } from './main-pane-header';
import { ReminderRow } from './reminder-row';
import { ReminderSearchBar } from './reminder-search-bar';
import type { GlobalReminder, SearchMode, SearchState } from './search-state';

function SortableReminder({
    reminder,
    expanded,
    highlighted,
    onToggleExpand,
    onCloseExpand,
}: {
    reminder: Reminder;
    expanded: boolean;
    highlighted: boolean;
    onToggleExpand: () => void;
    onCloseExpand: () => void;
}) {
    const sortable = useSortable({ id: reminderItemId(reminder.id) });
    const style = {
        transform: CSS.Transform.toString(sortable.transform),
        transition: sortable.transition,
        opacity: sortable.isDragging ? 0.4 : 1,
    };

    return (
        <div ref={sortable.setNodeRef} style={style}>
            <ReminderRow
                reminder={reminder}
                expanded={expanded}
                highlighted={highlighted}
                onToggleExpand={onToggleExpand}
                onCloseExpand={onCloseExpand}
                dragHandleProps={{
                    ...sortable.attributes,
                    ...sortable.listeners,
                }}
            />
        </div>
    );
}

type Props = {
    list: Pick<ReminderList, 'id' | 'name' | 'color' | 'is_inbox'>;
    reminders: Reminder[];
    completedReminders: Reminder[];
    addRef: React.RefObject<AddReminderRowHandle | null>;
    expandedId: number | null;
    onExpandChange: (id: number | null) => void;
    search: SearchState;
    onSearchClose: () => void;
    onSearchQueryChange: (q: string) => void;
    onSearchToggleCompleted: () => void;
    globalReminders: GlobalReminder[] | null;
    globalLoading: boolean;
    onPickGlobalResult: (listId: number, reminderId: number) => void;
};

export function MainPane({
    list,
    reminders,
    completedReminders,
    addRef,
    expandedId,
    onExpandChange,
    search,
    onSearchClose,
    onSearchQueryChange,
    onSearchToggleCompleted,
    globalReminders,
    globalLoading,
    onPickGlobalResult,
}: Props) {
    const highlightedId: number | null = null;

    const query = search.query.trim().toLowerCase();
    const hasQuery = query.length > 0;
    const matches = (title: string) => title.toLowerCase().includes(query);

    const inCurrent = search.mode === 'current';
    const inGlobal = search.mode === 'global';

    const filteredOpen =
        inCurrent && hasQuery
            ? reminders.filter((r) => matches(r.title))
            : reminders;
    const filteredCompleted =
        inCurrent && hasQuery && search.includeCompleted
            ? completedReminders.filter((r) => matches(r.title))
            : completedReminders;

    const globalResults: GlobalReminder[] = (() => {
        if (!inGlobal || !globalReminders) {
            return [];
        }

        return globalReminders.filter((r) => {
            if (!search.includeCompleted && r.status === 'done') {
                return false;
            }

            return hasQuery ? matches(r.title) : true;
        });
    })();

    const openTotal = reminders.length;
    const openMatchCount = inCurrent ? filteredOpen.length : openTotal;

    return (
        <main className="flex min-w-0 flex-1 flex-col">
            <MainPaneHeader list={list} openCount={reminders.length} />
            {search.mode !== null && (
                <ReminderSearchBar
                    mode={search.mode}
                    listName={list.name}
                    query={search.query}
                    includeCompleted={search.includeCompleted}
                    matchCount={
                        inCurrent
                            ? openMatchCount +
                              (search.includeCompleted
                                  ? filteredCompleted.length
                                  : 0)
                            : globalResults.length
                    }
                    totalCount={
                        inCurrent
                            ? openTotal +
                              (search.includeCompleted
                                  ? completedReminders.length
                                  : 0)
                            : (globalReminders?.length ?? 0)
                    }
                    onChange={onSearchQueryChange}
                    onToggleCompleted={onSearchToggleCompleted}
                    onClose={onSearchClose}
                />
            )}
            {!inGlobal && <AddReminderRow ref={addRef} listId={list.id} />}
            {inGlobal ? (
                <GlobalResults
                    results={globalResults}
                    loading={globalLoading}
                    query={search.query}
                    onPick={onPickGlobalResult}
                />
            ) : reminders.length === 0 && completedReminders.length === 0 ? (
                <EmptyState message="No reminders here yet — press n to add one." />
            ) : (
                <>
                    {hasQuery && filteredOpen.length === 0 && (
                        <div className="px-4 py-8 text-center text-sm text-muted-foreground">
                            No open reminders match “{search.query}”.
                        </div>
                    )}
                    <SortableContext
                        items={filteredOpen.map((r) => reminderItemId(r.id))}
                        strategy={verticalListSortingStrategy}
                    >
                        <div>
                            {filteredOpen.map((r) => (
                                <SortableReminder
                                    key={r.id}
                                    reminder={r}
                                    expanded={expandedId === r.id}
                                    highlighted={highlightedId === r.id}
                                    onToggleExpand={() =>
                                        onExpandChange(
                                            expandedId === r.id ? null : r.id,
                                        )
                                    }
                                    onCloseExpand={() => onExpandChange(null)}
                                />
                            ))}
                        </div>
                    </SortableContext>
                    <CompletedAccordion
                        reminders={filteredCompleted}
                        forceOpen={
                            hasQuery &&
                            search.includeCompleted &&
                            filteredCompleted.length > 0
                        }
                    />
                </>
            )}
        </main>
    );
}

function GlobalResults({
    results,
    loading,
    query,
    onPick,
}: {
    results: GlobalReminder[];
    loading: boolean;
    query: string;
    onPick: (listId: number, reminderId: number) => void;
}) {
    if (loading) {
        return (
            <div className="space-y-2 px-4 py-6">
                {Array.from({ length: 4 }).map((_, i) => (
                    <div
                        key={i}
                        className="h-10 animate-pulse rounded bg-amber-100/60 dark:bg-amber-900/30"
                    />
                ))}
            </div>
        );
    }

    if (results.length === 0) {
        return (
            <div className="px-4 py-8 text-center text-sm text-muted-foreground">
                {query.trim().length === 0
                    ? 'Type to search across all lists.'
                    : `No reminders match “${query}”.`}
            </div>
        );
    }

    return (
        <div>
            {results.map((r) => (
                <GlobalResultRow key={r.id} reminder={r} onPick={onPick} />
            ))}
        </div>
    );
}

function GlobalResultRow({
    reminder,
    onPick,
}: {
    reminder: GlobalReminder;
    onPick: (listId: number, reminderId: number) => void;
}) {
    const done = reminder.status === 'done';

    return (
        <button
            type="button"
            onClick={() => onPick(reminder.list.id, reminder.id)}
            className="flex w-full cursor-pointer items-center gap-3 border-b border-amber-100/60 px-4 py-2.5 text-left hover:bg-amber-50/50 dark:border-amber-900/30 dark:hover:bg-amber-950/10"
        >
            <span className="flex items-center gap-1.5 text-xs text-muted-foreground">
                {reminder.list.is_inbox ? (
                    <span>📥</span>
                ) : (
                    <span
                        className="inline-block h-2 w-2 rounded-full"
                        style={{
                            backgroundColor: reminder.list.color ?? '#999',
                        }}
                    />
                )}
                <span className="max-w-[10rem] truncate">
                    {reminder.list.name}
                </span>
            </span>
            <span
                className={`flex-1 truncate text-sm ${
                    done ? 'text-muted-foreground line-through' : ''
                }`}
            >
                {reminder.title}
            </span>
        </button>
    );
}

export type { SearchMode };
