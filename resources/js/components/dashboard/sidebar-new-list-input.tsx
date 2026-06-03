import { router } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import { useRef, useState } from 'react';
import { ColorPicker } from './color-picker';

type Props = {
    curatedColors: string[];
};

export function SidebarNewListInput({ curatedColors }: Props) {
    const [open, setOpen] = useState(false);
    const [name, setName] = useState('');
    const [color, setColor] = useState<string | null>(curatedColors[0] ?? null);
    const inputRef = useRef<HTMLInputElement>(null);

    function start() {
        setOpen(true);
        queueMicrotask(() => inputRef.current?.focus());
    }

    function cancel() {
        setOpen(false);
        setName('');
        setColor(curatedColors[0] ?? null);
    }

    function submit() {
        const trimmed = name.trim();

        if (!trimmed) {
            cancel();

            return;
        }

        router.post(
            '/lists',
            { name: trimmed, color },
            {
                preserveScroll: true,
                onSuccess: () => cancel(),
            },
        );
    }

    if (!open) {
        return (
            <button
                type="button"
                onClick={start}
                className="-mx-2 mt-1 flex w-[calc(100%+1rem)] items-center gap-2 rounded px-2 py-1.5 text-sm text-muted-foreground hover:bg-amber-50 dark:hover:bg-amber-950/20"
            >
                <Plus className="h-3.5 w-3.5" />
                <span>New list</span>
            </button>
        );
    }

    return (
        <div className="-mx-2 mt-1 space-y-2 rounded bg-amber-50 px-2 py-2 dark:bg-amber-950/20">
            <input
                ref={inputRef}
                value={name}
                onChange={(e) => setName(e.target.value)}
                placeholder="List name"
                onKeyDown={(e) => {
                    if (e.key === 'Enter') {
                        submit();
                    }

                    if (e.key === 'Escape') {
                        cancel();
                    }
                }}
                className="w-full rounded border border-input bg-background px-2 py-1 text-sm"
            />
            <ColorPicker
                value={color}
                onChange={setColor}
                swatches={curatedColors}
            />
        </div>
    );
}
