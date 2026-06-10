export type ReminderList = {
    id: number;
    name: string;
    color: string | null;
    position: number;
    is_inbox: boolean;
    open_count?: number;
};

export type ReminderContext = {
    repo?: string;
    repo_label?: string;
    branch?: string;
    file?: string;
    line_start?: number;
    line_end?: number;
    cwd?: string;
};

export type Reminder = {
    id: number;
    list_id: number;
    title: string;
    notes: string | null;
    notes_html: string;
    soft_due_date: string | null;
    context: ReminderContext | null;
    status: 'open' | 'done';
    completed_at: string | null;
    position: number;
};

export type GlobalReminderLite = {
    id: number;
    title: string;
    status: 'open' | 'done';
    list: {
        id: number;
        name: string;
        color: string | null;
        is_inbox: boolean;
    };
};

export type DashboardPageProps = {
    lists: ReminderList[];
    selectedList: Pick<ReminderList, 'id' | 'name' | 'color' | 'is_inbox'>;
    reminders: Reminder[];
    completedReminders: Reminder[];
    curatedColors: string[];
    globalReminders?: GlobalReminderLite[];
};

export type McpPageProps = {
    mcpUrl: string;
    tools: string[];
};
