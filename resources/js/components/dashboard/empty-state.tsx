export function EmptyState({ message }: { message: string }) {
    return (
        <div className="flex items-center justify-center py-16 px-6 mx-2 my-4 rounded-lg bg-amber-50/60 dark:bg-amber-950/20 border border-amber-100 dark:border-amber-900/30">
            <p className="text-sm text-muted-foreground">{message}</p>
        </div>
    );
}
