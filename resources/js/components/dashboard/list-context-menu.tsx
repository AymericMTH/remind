import type { ReactNode } from 'react';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';

type Props = {
    children: ReactNode;
    disabled?: boolean;
    onRename: () => void;
    onChangeColor: () => void;
    onDelete: () => void;
};

export function ListContextMenu({
    children,
    disabled,
    onRename,
    onChangeColor,
    onDelete,
}: Props) {
    return (
        <DropdownMenu>
            <DropdownMenuTrigger asChild disabled={disabled}>
                {children}
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end" className="w-44">
                <DropdownMenuItem onSelect={onRename}>Rename</DropdownMenuItem>
                <DropdownMenuItem onSelect={onChangeColor}>
                    Change color
                </DropdownMenuItem>
                <DropdownMenuSeparator />
                <DropdownMenuItem
                    onSelect={onDelete}
                    className="text-red-600 focus:bg-red-50 focus:text-red-700 dark:focus:bg-red-950/30"
                >
                    Delete
                </DropdownMenuItem>
            </DropdownMenuContent>
        </DropdownMenu>
    );
}
