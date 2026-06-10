import { useEffect, useRef } from 'react';

export type Shortcuts = {
    onNew?: () => void;
    onArrowUp?: () => void;
    onArrowDown?: () => void;
    onEnter?: () => void;
    onSpace?: () => void;
    onRename?: () => void;
    onDelete?: () => void;
    onJumpToListAtIndex?: (idx: number) => void;
    onJumpToInbox?: () => void;
    onEscape?: () => void;
    onCheatsheet?: () => void;
    onSearchCurrent?: () => void;
    onSearchAll?: () => void;
};

function isTextInputFocused(): boolean {
    const el = document.activeElement;

    if (!el) {
        return false;
    }

    const tag = el.tagName;

    return (
        tag === 'INPUT' ||
        tag === 'TEXTAREA' ||
        (el as HTMLElement).isContentEditable
    );
}

export function useKeyboardShortcuts(s: Shortcuts) {
    const gArmedAt = useRef<number | null>(null);
    const G_WINDOW_MS = 1500;

    useEffect(() => {
        function onKey(e: KeyboardEvent) {
            if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'f') {
                e.preventDefault();

                if (e.shiftKey) {
                    s.onSearchAll?.();
                } else {
                    s.onSearchCurrent?.();
                }

                return;
            }

            if (e.key === 'Escape') {
                s.onEscape?.();

                return;
            }

            if (isTextInputFocused()) {
                return;
            }

            const armed =
                gArmedAt.current !== null &&
                Date.now() - gArmedAt.current < G_WINDOW_MS;

            if (armed) {
                gArmedAt.current = null;

                if (e.key === 'i') {
                    s.onJumpToInbox?.();
                    e.preventDefault();

                    return;
                }

                if (/^[1-9]$/.test(e.key)) {
                    s.onJumpToListAtIndex?.(parseInt(e.key, 10) - 1);
                    e.preventDefault();

                    return;
                }

                return;
            }

            switch (e.key) {
                case 'g':
                    gArmedAt.current = Date.now();
                    break;
                case 'n':
                    s.onNew?.();
                    e.preventDefault();
                    break;
                case 'ArrowUp':
                    s.onArrowUp?.();
                    e.preventDefault();
                    break;
                case 'ArrowDown':
                    s.onArrowDown?.();
                    e.preventDefault();
                    break;
                case 'Enter':
                    s.onEnter?.();
                    break;
                case ' ':
                    s.onSpace?.();
                    e.preventDefault();
                    break;
                case 'e':
                    s.onRename?.();
                    e.preventDefault();
                    break;
                case 'Delete':
                case 'Backspace':
                    s.onDelete?.();
                    break;
                case '?':
                    s.onCheatsheet?.();
                    e.preventDefault();
                    break;
            }
        }
        document.addEventListener('keydown', onKey);

        return () => document.removeEventListener('keydown', onKey);
    }, [s]);
}
