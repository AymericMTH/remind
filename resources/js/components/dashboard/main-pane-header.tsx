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
                    className="inline-block h-2.5 w-2.5 rounded-full"
                    style={{ backgroundColor: list.color ?? '#999' }}
                />
            )}
            <h1 className="text-lg font-semibold tracking-tight">
                {list.name}
            </h1>
            <span className="ml-auto rounded-full bg-amber-100 px-2 py-0.5 text-xs text-amber-900 dark:bg-amber-900/30 dark:text-amber-200">
                {openCount} open
            </span>
        </div>
    );
}
