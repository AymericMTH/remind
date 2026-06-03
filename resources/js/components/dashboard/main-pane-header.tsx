import type { ReminderList } from '@/types/remind';

type Props = {
    list: Pick<ReminderList, 'id' | 'name' | 'color' | 'is_inbox'>;
    openCount: number;
};

export function MainPaneHeader({ list, openCount }: Props) {
    return (
        <div className="flex items-center gap-3 px-6 pt-6 pb-3">
            {list.is_inbox ? (
                <span className="text-base">📥</span>
            ) : (
                <span
                    className="inline-block w-2.5 h-2.5 rounded-full"
                    style={{ backgroundColor: list.color ?? '#999' }}
                />
            )}
            <h1 className="text-lg font-semibold tracking-tight">{list.name}</h1>
            <span className="ml-auto text-xs bg-amber-100 dark:bg-amber-900/30 text-amber-900 dark:text-amber-200 rounded-full px-2 py-0.5">
                {openCount} open
            </span>
        </div>
    );
}
