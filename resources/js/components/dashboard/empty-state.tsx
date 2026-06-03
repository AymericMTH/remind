export function EmptyState({ message }: { message: string }) {
    return (
        <div className="mx-2 my-4 flex items-center justify-center rounded-lg border border-amber-100 bg-amber-50/60 px-6 py-16 dark:border-amber-900/30 dark:bg-amber-950/20">
            <p className="text-sm text-muted-foreground">{message}</p>
        </div>
    );
}
