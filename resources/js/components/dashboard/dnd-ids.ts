export const SIDEBAR_LIST_PREFIX = 'list-';
export const REMINDER_PREFIX = 'reminder-';

export function sidebarListId(id: number): string {
    return `${SIDEBAR_LIST_PREFIX}${id}`;
}

export function reminderItemId(id: number): string {
    return `${REMINDER_PREFIX}${id}`;
}

export function parseSidebarListId(raw: string | number): number | null {
    const s = String(raw);

    return s.startsWith(SIDEBAR_LIST_PREFIX)
        ? Number(s.slice(SIDEBAR_LIST_PREFIX.length))
        : null;
}

export function parseReminderId(raw: string | number): number | null {
    const s = String(raw);

    return s.startsWith(REMINDER_PREFIX)
        ? Number(s.slice(REMINDER_PREFIX.length))
        : null;
}
