import { Head } from '@inertiajs/react';
import { Check, Copy } from 'lucide-react';
import { toast } from 'sonner';
import { Button } from '@/components/ui/button';
import { useClipboard } from '@/hooks/use-clipboard';
import { mcp } from '@/routes/settings';
import type { McpPageProps } from '@/types/remind';

export default function McpSettings({ mcpUrl, tools }: McpPageProps) {
    const [copiedText, copy] = useClipboard();

    const cliCommand = `claude mcp add --scope user --transport http remind ${mcpUrl}`;
    const snippet = JSON.stringify(
        { mcpServers: { remind: { type: 'http', url: mcpUrl } } },
        null,
        2,
    );

    async function copyValue(value: string) {
        await copy(value);
        toast.success('Copied to clipboard');
    }

    return (
        <>
            <Head title="MCP" />

            <h1 className="sr-only">MCP settings</h1>

            <div className="space-y-6">
                <header>
                    <h2 className="text-lg font-semibold tracking-tight">
                        Claude Code MCP setup
                    </h2>
                    <p className="mt-1 text-sm text-muted-foreground">
                        Run the CLI one-liner, or add the JSON snippet to{' '}
                        <code className="font-mono text-xs">
                            ~/.claude.json
                        </code>{' '}
                        or a project{' '}
                        <code className="font-mono text-xs">.mcp.json</code>.
                    </p>
                </header>

                <div className="rounded border border-input bg-muted/30">
                    <div className="flex items-center justify-between border-b border-input px-3 py-2">
                        <span className="text-xs tracking-wider text-muted-foreground uppercase">
                            Claude Code CLI
                        </span>
                        <Button
                            type="button"
                            size="sm"
                            variant="ghost"
                            onClick={() => copyValue(cliCommand)}
                            className="gap-1"
                        >
                            {copiedText === cliCommand ? (
                                <Check className="h-3.5 w-3.5" />
                            ) : (
                                <Copy className="h-3.5 w-3.5" />
                            )}
                            {copiedText === cliCommand ? 'Copied' : 'Copy'}
                        </Button>
                    </div>
                    <pre className="overflow-x-auto px-3 py-3 font-mono text-xs">
                        {cliCommand}
                    </pre>
                </div>

                <div className="rounded border border-input bg-muted/30">
                    <div className="flex items-center justify-between border-b border-input px-3 py-2">
                        <span className="text-xs tracking-wider text-muted-foreground uppercase">
                            .mcp.json
                        </span>
                        <Button
                            type="button"
                            size="sm"
                            variant="ghost"
                            onClick={() => copyValue(snippet)}
                            className="gap-1"
                        >
                            {copiedText === snippet ? (
                                <Check className="h-3.5 w-3.5" />
                            ) : (
                                <Copy className="h-3.5 w-3.5" />
                            )}
                            {copiedText === snippet ? 'Copied' : 'Copy'}
                        </Button>
                    </div>
                    <pre className="overflow-x-auto px-3 py-3 font-mono text-xs">
                        {snippet}
                    </pre>
                </div>

                <section>
                    <h3 className="mb-2 text-sm font-medium">
                        What Claude can do
                    </h3>
                    <ul className="space-y-1 text-sm text-muted-foreground">
                        {tools.map((t) => (
                            <li key={t} className="font-mono text-xs">
                                &middot; {t}
                            </li>
                        ))}
                    </ul>
                </section>

                <p className="border-t border-input pt-3 text-xs text-muted-foreground">
                    Re:Mind&rsquo;s MCP endpoint is unauthenticated by design.
                    Only expose this app on loopback or a private network.
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
