<?php

declare(strict_types=1);

namespace App\Twig;

use League\CommonMark\Environment\Environment;
use League\CommonMark\Extension\Autolink\AutolinkExtension;
use League\CommonMark\Extension\Strikethrough\StrikethroughExtension;
use League\CommonMark\Extension\Table\TableExtension;
use League\CommonMark\Extension\TaskList\TaskListExtension;
use League\CommonMark\MarkdownConverter;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

/**
 * Renders the article bodies, described by the specification as markdown.
 */
final class MarkdownExtension extends AbstractExtension
{
    private readonly MarkdownConverter $converter;

    public function __construct()
    {
        $environment = new Environment([], ['html_input' => 'strip', 'max_nesting_level' => 20]);
        $environment->addExtension(new AutolinkExtension());
        $environment->addExtension(new StrikethroughExtension());
        $environment->addExtension(new TableExtension());
        $environment->addExtension(new TaskListExtension());

        $this->converter = new MarkdownConverter($environment);
    }

    public function getFilters(): array
    {
        return [
            new TwigFilter('markdown', $this->convert(...), ['is_safe' => ['html']]),
        ];
    }

    public function convert(string $source): string
    {
        return $this->converter->convert($source);
    }
}
