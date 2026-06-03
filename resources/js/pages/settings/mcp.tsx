import { Head } from '@inertiajs/react';
import { Check, Copy } from 'lucide-react';
import { toast } from 'sonner';
import { Button } from '@/components/ui/button';
import { useClipboard } from '@/hooks/use-clipboard';
import { mcp } from '@/routes/settings';
import type { McpPageProps } from '@/types/remind';

export default function McpSettings({ mcpUrl, tools }: McpPageProps) {
    const [copiedText, copy] = useClipboard();
    const copied = copiedText !== null;

    const snippet = JSON.stringify(
        { mcpServers: { remind: { type: 'http', url: mcpUrl } } },
        null,
        2,
    );

    async function copySnippet() {
        await copy(snippet);
        toast.success('Copied to clipboard');
    }

    return (
        <>
            <Head title="MCP" />

            <h1 className="sr-only">MCP settings</h1>

            <div className="space-y-6">
                <header>
                    <h2 className="text-lg font-semibold tracking-tight">Claude Code MCP setup</h2>
                    <p className="text-sm text-muted-foreground mt-1">
                        Add this to <code className="font-mono text-xs">~/.claude.json</code> or a project{' '}
                        <code className="font-mono text-xs">.mcp.json</code> to make Claude Code talk to Re:Mind.
                    </p>
                </header>

                <div className="rounded border border-input bg-muted/30">
                    <div className="flex justify-between items-center px-3 py-2 border-b border-input">
                        <span className="text-xs uppercase tracking-wider text-muted-foreground">.mcp.json</span>
                        <Button type="button" size="sm" variant="ghost" onClick={copySnippet} className="gap-1">
                            {copied ? <Check className="h-3.5 w-3.5" /> : <Copy className="h-3.5 w-3.5" />}
                            {copied ? 'Copied' : 'Copy'}
                        </Button>
                    </div>
                    <pre className="px-3 py-3 text-xs font-mono overflow-x-auto">{snippet}</pre>
                </div>

                <section>
                    <h3 className="text-sm font-medium mb-2">What Claude can do</h3>
                    <ul className="text-sm text-muted-foreground space-y-1">
                        {tools.map((t) => (
                            <li key={t} className="font-mono text-xs">&middot; {t}</li>
                        ))}
                    </ul>
                </section>

                <p className="text-xs text-muted-foreground border-t border-input pt-3">
                    Re:Mind&rsquo;s MCP endpoint is unauthenticated by design. Only expose this app on loopback or a private network.
                </p>
            </div>
        </>
    );
}

McpSettings.layout = {
    breadcrumbs: [
        {
            title: 'MCP settings',
            href: mcp.url(),
        },
    ],
};
