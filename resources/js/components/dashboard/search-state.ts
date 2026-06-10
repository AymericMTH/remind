export type SearchMode = 'current' | 'global';

export type SearchState = {
    mode: SearchMode | null;
    query: string;
    includeCompleted: boolean;
};

export type GlobalReminder = {
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
