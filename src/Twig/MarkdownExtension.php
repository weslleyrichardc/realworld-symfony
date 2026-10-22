<?php

declare(strict_types=1);

namespace App\Twig;

use League\CommonMark\GithubFlavoredMarkdownConverter;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

/**
 * Renders the article bodies, described by the specification as markdown.
 */
final class MarkdownExtension extends AbstractExtension
{
    private readonly GithubFlavoredMarkdownConverter $converter;

    public function __construct()
    {
        $this->converter = new GithubFlavoredMarkdownConverter([
            'html_input' => 'strip',
            'allow_unsafe_links' => false,
            'max_nesting_level' => 20,
        ]);
    }

    public function getFilters(): array
    {
        return [
            new TwigFilter('markdown', $this->convert(...), ['is_safe' => ['html']]),
        ];
    }

    public function convert(string $source): string
    {
        return (string) $this->converter->convert($source);
    }
}
