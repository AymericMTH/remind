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
import type { ReminderList } from '@/types/remind';
import { BrandWordmark } from './brand-wordmark';
import { SidebarListItem } from './sidebar-list-item';
import { SidebarNewListInput } from './sidebar-new-list-input';

function SortableItem({
    list,
    selected,
    reminderCount,
    curatedColors,
    onSelect,
}: {
    list: ReminderList;
    selected: boolean;
    reminderCount: number;
    curatedColors: string[];
    onSelect: () => void;
}) {
    const sortable = useSortable({ id: list.id, disabled: list.is_inbox });
    const style = {
        transform: CSS.Transform.toString(sortable.transform),
        transition: sortable.transition,
        opacity: sortable.isDragging ? 0.4 : 1,
    };

    return (
        <div
            ref={sortable.setNodeRef}
            style={style}
            {...sortable.attributes}
            {...sortable.listeners}
        >
            <SidebarListItem
                list={list}
                selected={selected}
                reminderCount={reminderCount}
                curatedColors={curatedColors}
                onSelect={onSelect}
            />
        </div>
    );
}

type Props = {
    lists: ReminderList[];
    selectedListId: number;
    curatedColors: string[];
    onSelect: (id: number) => void;
};

export function Sidebar({
    lists,
    selectedListId,
    curatedColors,
    onSelect,
}: Props) {
    const sensors = useSensors(
        useSensor(PointerSensor, { activationConstraint: { distance: 5 } }),
    );

    function onDragEnd(e: DragEndEvent) {
        if (!e.over || e.active.id === e.over.id) {
            return;
        }

        const oldIdx = lists.findIndex((l) => l.id === e.active.id);
        const newIdx = lists.findIndex((l) => l.id === e.over!.id);

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
    }

    return (
        <aside className="flex w-60 shrink-0 flex-col border-r border-amber-100 bg-amber-50/80 px-4 py-4 dark:border-amber-900/30 dark:bg-amber-950/20">
            <BrandWordmark className="mb-5 text-base" />
            <div className="mb-2 text-xs tracking-widest text-amber-900/50 uppercase dark:text-amber-200/50">
                Projects
            </div>
            <DndContext
                sensors={sensors}
                collisionDetection={closestCenter}
                onDragEnd={onDragEnd}
            >
                <SortableContext
                    items={lists.map((l) => l.id)}
                    strategy={verticalListSortingStrategy}
                >
                    <div className="space-y-0.5">
                        {lists.map((l) => (
                            <SortableItem
                                key={l.id}
                                list={l}
                                selected={l.id === selectedListId}
                                reminderCount={l.open_count ?? 0}
                                curatedColors={curatedColors}
                                onSelect={() => onSelect(l.id)}
                            />
                        ))}
                    </div>
                </SortableContext>
            </DndContext>
            <SidebarNewListInput curatedColors={curatedColors} />
        </aside>
    );
}
