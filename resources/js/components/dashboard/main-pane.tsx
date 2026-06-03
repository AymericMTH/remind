import {
    closestCenter,
    DndContext,
    PointerSensor,
    useSensor,
    useSensors,
} from '@dnd-kit/core';
import type { DragEndEvent } from '@dnd-kit/core';
import {
    SortableContext,
    useSortable,
    verticalListSortingStrategy,
} from '@dnd-kit/sortable';
import { CSS } from '@dnd-kit/utilities';
import { router } from '@inertiajs/react';
import { useState } from 'react';
import type { Reminder, ReminderList } from '@/types/remind';
import { AddReminderRow } from './add-reminder-row';
import type { AddReminderRowHandle } from './add-reminder-row';
import { CompletedAccordion } from './completed-accordion';
import { EmptyState } from './empty-state';
import { MainPaneHeader } from './main-pane-header';
import { ReminderRow } from './reminder-row';

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
    const sortable = useSortable({ id: reminder.id });
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
};

export function MainPane({
    list,
    reminders,
    completedReminders,
    addRef,
}: Props) {
    const [expandedId, setExpandedId] = useState<number | null>(null);
    const highlightedId: number | null = null;
    const sensors = useSensors(
        useSensor(PointerSensor, { activationConstraint: { distance: 5 } }),
    );

    function onDragEnd(e: DragEndEvent) {
        if (!e.over || e.active.id === e.over.id) {
            return;
        }

        const oldIdx = reminders.findIndex((r) => r.id === e.active.id);
        const newIdx = reminders.findIndex((r) => r.id === e.over!.id);

        if (oldIdx === -1 || newIdx === -1) {
            return;
        }

        const reordered = reminders.slice();
        reordered.splice(newIdx, 0, reordered.splice(oldIdx, 1)[0]);
        router.put(
            '/reminders/reorder',
            {
                list_id: list.id,
                order: reordered.map((r) => r.id),
            },
            { preserveScroll: true },
        );
    }

    return (
        <main className="flex min-w-0 flex-1 flex-col">
            <MainPaneHeader list={list} openCount={reminders.length} />
            <AddReminderRow ref={addRef} listId={list.id} />
            {reminders.length === 0 && completedReminders.length === 0 ? (
                <EmptyState message="No reminders here yet — press n to add one." />
            ) : (
                <>
                    <DndContext
                        sensors={sensors}
                        collisionDetection={closestCenter}
                        onDragEnd={onDragEnd}
                    >
                        <SortableContext
                            items={reminders.map((r) => r.id)}
                            strategy={verticalListSortingStrategy}
                        >
                            <div>
                                {reminders.map((r) => (
                                    <SortableReminder
                                        key={r.id}
                                        reminder={r}
                                        expanded={expandedId === r.id}
                                        highlighted={highlightedId === r.id}
                                        onToggleExpand={() =>
                                            setExpandedId((cur) =>
                                                cur === r.id ? null : r.id,
                                            )
                                        }
                                        onCloseExpand={() =>
                                            setExpandedId(null)
                                        }
                                    />
                                ))}
                            </div>
                        </SortableContext>
                    </DndContext>
                    <CompletedAccordion reminders={completedReminders} />
                </>
            )}
        </main>
    );
}
