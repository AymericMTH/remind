import { useEffect, useRef } from 'react';

export function useClickOutside<T extends HTMLElement>(active: boolean, onOutside: () => void) {
    const ref = useRef<T>(null);

    useEffect(() => {
        if (!active) return;
        function onDocClick(e: MouseEvent) {
            if (!ref.current) return;
            if (ref.current.contains(e.target as Node)) return;
            onOutside();
        }
        document.addEventListener('mousedown', onDocClick);
        return () => document.removeEventListener('mousedown', onDocClick);
    }, [active, onOutside]);

    return ref;
}
