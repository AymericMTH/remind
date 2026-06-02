import { Head } from '@inertiajs/react';

export default function Dashboard({ lists }: { lists: Array<{ id: number; name: string }> }) {
    return (
        <>
            <Head title="Re:Mind" />
            <div className="p-6">
                <h1 className="text-xl font-semibold">Re:Mind</h1>
                <ul className="mt-4 space-y-1">
                    {lists.map((l) => (
                        <li key={l.id}>📋 {l.name}</li>
                    ))}
                </ul>
                <p className="mt-6 text-sm text-gray-500">Full dashboard ships in Plan 2.</p>
            </div>
        </>
    );
}
