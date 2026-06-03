import { useState } from 'react';
import type { ChangeEvent } from 'react';

const HEX_RE =
    /^#?(?:[0-9a-fA-F]{8}|[0-9a-fA-F]{6}|[0-9a-fA-F]{4}|[0-9a-fA-F]{3})$/;

function normalize(value: string): string | null {
    if (!HEX_RE.test(value)) {
        return null;
    }

    return value.startsWith('#') ? value : `#${value}`;
}

type Props = {
    value: string | null;
    onChange: (value: string | null) => void;
    swatches: string[];
};

export function ColorPicker({ value, onChange, swatches }: Props) {
    const [draft, setDraft] = useState(value ?? '');
    const [invalid, setInvalid] = useState(false);

    function commit(e: ChangeEvent<HTMLInputElement>) {
        const v = e.target.value.trim();
        setDraft(v);

        if (v === '') {
            onChange(null);
            setInvalid(false);

            return;
        }

        const n = normalize(v);
        setInvalid(n === null);

        if (n !== null) {
            onChange(n);
        }
    }

    return (
        <div className="space-y-2">
            <div className="flex gap-1.5">
                {swatches.map((c) => (
                    <button
                        type="button"
                        key={c}
                        onClick={() => {
                            onChange(c);
                            setDraft(c);
                            setInvalid(false);
                        }}
                        className={`h-5 w-5 rounded-full border ${value === c ? 'ring-2 ring-amber-500 ring-offset-1' : 'border-black/10'}`}
                        style={{ backgroundColor: c }}
                        aria-label={`Select color ${c}`}
                    />
                ))}
            </div>
            <input
                type="text"
                value={draft}
                onChange={commit}
                placeholder="#7aa2f7"
                aria-invalid={invalid}
                className={`w-full rounded border px-2 py-1 font-mono text-sm ${invalid ? 'border-red-400' : 'border-input'} bg-background`}
            />
        </div>
    );
}
