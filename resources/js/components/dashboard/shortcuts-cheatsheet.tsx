import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';

const ROWS: [string, string][] = [
    ['n', 'New reminder'],
    ['↑ / ↓', 'Move between rows'],
    ['Enter', 'Open editor'],
    ['Space', 'Toggle done'],
    ['e', 'Rename in place'],
    ['Delete / Backspace', 'Delete reminder'],
    ['g then i', 'Jump to Inbox'],
    ['g then 1–9', 'Jump to list 1–9'],
    ['Esc', 'Close editor / cancel'],
    ['?', 'Show this'],
];

type Props = { open: boolean; onClose: () => void };

export function ShortcutsCheatsheet({ open, onClose }: Props) {
    return (
        <Dialog open={open} onOpenChange={(o) => !o && onClose()}>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Keyboard shortcuts</DialogTitle>
                </DialogHeader>
                <table className="w-full text-sm">
                    <tbody>
                        {ROWS.map(([k, label]) => (
                            <tr
                                key={k}
                                className="border-b border-amber-100/60 last:border-0 dark:border-amber-900/30"
                            >
                                <td className="py-1.5 pr-4 font-mono text-xs whitespace-nowrap text-amber-700 dark:text-amber-300">
                                    {k}
                                </td>
                                <td className="py-1.5">{label}</td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </DialogContent>
        </Dialog>
    );
}
