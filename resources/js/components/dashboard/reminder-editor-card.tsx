import { Button } from '@/components/ui/button';
import { router, useForm } from '@inertiajs/react';
import { useState } from 'react';
import type { Reminder } from '@/types/remind';

type Mode = 'edit' | 'preview';

type Props = {
    reminder: Reminder;
    onSaved: () => void;
    onCancel: () => void;
};

export function ReminderEditorCard({ reminder, onSaved, onCancel }: Props) {
    const [mode, setMode] = useState<Mode>('edit');
    const { data, setData, put, processing, errors } = useForm({
        title: reminder.title,
        notes: reminder.notes ?? '',
        soft_due_date: reminder.soft_due_date ?? '',
        context_branch: reminder.context?.branch ?? '',
        context_file: reminder.context?.file ?? '',
        context_line_start: reminder.context?.line_start?.toString() ?? '',
        context_line_end: reminder.context?.line_end?.toString() ?? '',
    });

    function save() {
        const ctx: Record<string, string | number> = {};
        if (reminder.context?.repo) ctx.repo = reminder.context.repo;
        if (reminder.context?.repo_label) ctx.repo_label = reminder.context.repo_label;
        if (reminder.context?.cwd) ctx.cwd = reminder.context.cwd;
        if (data.context_branch) ctx.branch = data.context_branch;
        if (data.context_file) ctx.file = data.context_file;
        if (data.context_line_start) ctx.line_start = Number(data.context_line_start);
        if (data.context_line_end) ctx.line_end = Number(data.context_line_end);

        const payload = {
            title: data.title,
            notes: data.notes === '' ? null : data.notes,
            soft_due_date: data.soft_due_date === '' ? null : data.soft_due_date,
            context: Object.keys(ctx).length ? ctx : null,
        };

        router.put(`/reminders/${reminder.id}`, payload, {
            preserveScroll: true,
            onSuccess: () => onSaved(),
        });
    }

    function destroy() {
        if (!confirm('Delete this reminder?')) return;
        router.delete(`/reminders/${reminder.id}`, {
            preserveScroll: true,
            onSuccess: () => onCancel(),
        });
    }

    return (
        <div className="mx-4 my-2 p-4 rounded border border-amber-200 dark:border-amber-900/40 bg-white dark:bg-amber-950/10 shadow-sm space-y-3">
            <input
                value={data.title}
                onChange={(e) => setData('title', e.target.value)}
                className="w-full text-base font-medium bg-transparent outline-none"
                placeholder="Title"
            />
            {errors.title && <p className="text-xs text-red-600">{errors.title}</p>}

            <div>
                <div className="flex items-center gap-2 mb-1">
                    <button
                        type="button"
                        onClick={() => setMode('edit')}
                        className={`text-xs px-2 py-0.5 rounded ${mode === 'edit' ? 'bg-amber-100 dark:bg-amber-900/40' : 'text-muted-foreground'}`}
                    >
                        Edit
                    </button>
                    <button
                        type="button"
                        onClick={() => setMode('preview')}
                        className={`text-xs px-2 py-0.5 rounded ${mode === 'preview' ? 'bg-amber-100 dark:bg-amber-900/40' : 'text-muted-foreground'}`}
                    >
                        Preview
                    </button>
                </div>
                {mode === 'edit' ? (
                    <textarea
                        value={data.notes}
                        onChange={(e) => setData('notes', e.target.value)}
                        rows={4}
                        className="w-full text-sm border border-input rounded px-2 py-1.5 bg-background"
                        placeholder="Notes (markdown)…"
                    />
                ) : (
                    <div
                        className="text-sm prose prose-sm dark:prose-invert max-w-none px-2 py-1.5 border border-input rounded bg-amber-50/40 dark:bg-amber-950/10 min-h-[80px]"
                        dangerouslySetInnerHTML={{ __html: reminder.notes_html || '<em class=\'text-muted-foreground\'>No notes yet</em>' }}
                    />
                )}
            </div>

            <div className="flex items-center gap-3 text-sm">
                <label className="text-muted-foreground">Due</label>
                <input
                    type="date"
                    value={data.soft_due_date}
                    onChange={(e) => setData('soft_due_date', e.target.value)}
                    className="text-sm border border-input rounded px-2 py-1 bg-background"
                />
                {data.soft_due_date && (
                    <button type="button" className="text-xs text-muted-foreground" onClick={() => setData('soft_due_date', '')}>
                        Clear
                    </button>
                )}
            </div>

            <div className="grid grid-cols-2 gap-2 text-sm">
                <input
                    value={data.context_branch}
                    onChange={(e) => setData('context_branch', e.target.value)}
                    placeholder="branch"
                    className="border border-input rounded px-2 py-1 bg-background font-mono text-xs"
                />
                <input
                    value={data.context_file}
                    onChange={(e) => setData('context_file', e.target.value)}
                    placeholder="file"
                    className="border border-input rounded px-2 py-1 bg-background font-mono text-xs"
                />
                <input
                    value={data.context_line_start}
                    onChange={(e) => setData('context_line_start', e.target.value)}
                    placeholder="line start"
                    className="border border-input rounded px-2 py-1 bg-background font-mono text-xs"
                />
                <input
                    value={data.context_line_end}
                    onChange={(e) => setData('context_line_end', e.target.value)}
                    placeholder="line end"
                    className="border border-input rounded px-2 py-1 bg-background font-mono text-xs"
                />
            </div>

            <div className="flex items-center justify-between pt-1">
                <Button type="button" variant="ghost" size="sm" onClick={destroy} className="text-red-600 hover:text-red-700">
                    Delete
                </Button>
                <div className="flex gap-2">
                    <Button type="button" variant="outline" size="sm" onClick={onCancel} disabled={processing}>
                        Cancel
                    </Button>
                    <Button type="button" size="sm" onClick={save} disabled={processing}>
                        Save
                    </Button>
                </div>
            </div>
        </div>
    );
}
