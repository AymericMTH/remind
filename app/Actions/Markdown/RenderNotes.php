<?php

namespace App\Actions\Markdown;

use League\CommonMark\Environment\Environment;
use League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension;
use League\CommonMark\MarkdownConverter;

class RenderNotes
{
    public function run(?string $markdown): string
    {
        if ($markdown === null || trim($markdown) === '') {
            return '';
        }

        $config = [
            'html_input' => 'strip',
            'allow_unsafe_links' => false,
        ];

        $env = new Environment($config);
        $env->addExtension(new CommonMarkCoreExtension());

        return (new MarkdownConverter($env))->convert($markdown)->getContent();
    }
}
