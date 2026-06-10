import { useDndContext } from '@dnd-kit/core';
import {
    SortableContext,
    useSortable,
    verticalListSortingStrategy,
} from '@dnd-kit/sortable';
import { CSS } from '@dnd-kit/utilities';
import type { ReminderList } from '@/types/remind';
import { BrandWordmark } from './brand-wordmark';
import { sidebarListId } from './dnd-ids';
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
    const sortable = useSortable({
        id: sidebarListId(list.id),
        disabled: list.is_inbox ? { draggable: true, droppable: false } : false,
    });
    const { active } = useDndContext();
    const isReminderDrag =
        active !== null && String(active.id).startsWith('reminder-');
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
                isDropTarget={isReminderDrag && sortable.isOver}
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
    return (
        <aside className="flex w-60 shrink-0 flex-col border-r border-amber-100 bg-amber-50/80 px-4 py-4 dark:border-amber-900/30 dark:bg-amber-950/20">
            <BrandWordmark className="mb-5 text-base" />
            <div className="mb-2 text-xs tracking-widest text-amber-900/50 uppercase dark:text-amber-200/50">
                Projects
            </div>
            <SortableContext
                items={lists.map((l) => sidebarListId(l.id))}
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
            <SidebarNewListInput curatedColors={curatedColors} />
        </aside>
    );
}
