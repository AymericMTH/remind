import { router } from '@inertiajs/react';

export function useSelectedList() {
    function select(id: number) {
        router.get(
            '/dashboard',
            { list: id },
            {
                preserveScroll: true,
                preserveState: true,
                only: ['selectedList', 'reminders', 'completedReminders'],
                replace: true,
            },
        );
    }

    return { select };
}
