<?php

namespace App\Services;

use DOMDocument;
use DOMElement;
use DOMNode;
use DOMXPath;

/**
 * Reduces a docs page's HTML to the markdown of its article: headings,
 * paragraphs, lists, tables and code blocks, without the site's navigation,
 * scripts or styling. Uses PHP's DOM only, so no dependency is needed.
 *
 * The article is found from the page's `<h1>`: the largest element around it
 * that holds no `<nav>` or `<aside>`, so the menus beside it are left out.
 */
class HtmlToMarkdown
{
    private const array SKIPPED = ['script', 'style', 'noscript', 'template', 'svg', 'nav', 'aside', 'header', 'footer', 'form', 'button', 'iframe', 'img', 'picture', 'video', 'select', 'input'];

    private const array BLOCKS = ['p', 'div', 'section', 'article', 'main', 'blockquote', 'figure', 'figcaption', 'dl', 'dt', 'dd', 'details', 'summary'];

    // The scheme and host of the page being converted, for its relative links.
    private ?string $origin = null;

    /**
     * Links starting with `/` are made absolute against `$pageUrl`, so they
     * still lead somewhere when read away from the site.
     */
    public function convert(string $html, ?string $pageUrl = null): string
    {
        $this->origin = $pageUrl !== null && preg_match('#^(https?://[^/]+)#i', $pageUrl, $match) ? $match[1] : null;

        $document = new DOMDocument;

        $previous = libxml_use_internal_errors(true);
        $document->loadHTML('<?xml encoding="UTF-8">'.$html, LIBXML_NOERROR | LIBXML_NOWARNING | LIBXML_NONET);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $article = $this->article($document);

        if ($article === null) {
            return '';
        }

        $markdown = $this->children($article);

        // At most one blank line between blocks, and none at the ends of lines.
        $markdown = preg_replace("/[ \t]+\n/", "\n", $markdown) ?? $markdown;
        $markdown = preg_replace("/\n{3,}/", "\n\n", $markdown) ?? $markdown;

        return trim($markdown)."\n";
    }

    private function article(DOMDocument $document): ?DOMNode
    {
        $xpath = new DOMXPath($document);
        $best = null;

        foreach ($xpath->query('//h1') ?: [] as $heading) {
            $candidate = $heading;

            while ($candidate->parentNode instanceof DOMElement
                && ! in_array($candidate->parentNode->nodeName, ['body', 'html'], true)
                && $xpath->query('.//nav | .//aside', $candidate->parentNode)->length === 0) {
                $candidate = $candidate->parentNode;
            }

            if ($best === null || strlen($candidate->textContent) > strlen($best->textContent)) {
                $best = $candidate;
            }
        }

        return $best
            ?? $xpath->query('//main | //article | //*[@role="main"]')->item(0)
            ?? $xpath->query('//body')->item(0);
    }

    private function children(DOMNode $node): string
    {
        $markdown = '';

        foreach ($node->childNodes as $child) {
            $markdown .= $this->node($child);
        }

        return $markdown;
    }

    private function node(DOMNode $node): string
    {
        if ($node->nodeType === XML_TEXT_NODE) {
            return preg_replace('/\s+/', ' ', $node->textContent) ?? '';
        }

        if (! $node instanceof DOMElement) {
            return '';
        }

        $name = strtolower($node->nodeName);

        if (in_array($name, self::SKIPPED, true) || $node->getAttribute('aria-hidden') === 'true' || $node->hasAttribute('hidden')) {
            return '';
        }

        return match (true) {
            (bool) preg_match('/^h([1-6])$/', $name, $level) => "\n\n".str_repeat('#', (int) $level[1]).' '.$this->inline($node)."\n\n",
            $name === 'pre' => $this->codeBlock($node),
            $name === 'code' => $this->inlineCode($node),
            $name === 'br' => "\n",
            $name === 'hr' => "\n\n---\n\n",
            in_array($name, ['strong', 'b'], true) => $this->wrap('**', $this->children($node)),
            in_array($name, ['em', 'i'], true) => $this->wrap('_', $this->children($node)),
            $name === 'a' => $this->link($node),
            in_array($name, ['ul', 'ol'], true) => $this->listBlock($node, $name === 'ol'),
            $name === 'table' => $this->table($node),
            $name === 'blockquote' => "\n\n".preg_replace('/^/m', '> ', trim($this->children($node)))."\n\n",
            in_array($name, self::BLOCKS, true) => "\n\n".$this->children($node)."\n\n",
            default => $this->children($node),
        };
    }

    private function inline(DOMNode $node): string
    {
        return trim(preg_replace('/\s+/', ' ', $this->children($node)) ?? '');
    }

    private function wrap(string $marker, string $text): string
    {
        $trimmed = trim($text);

        return $trimmed === '' ? $text : $marker.$trimmed.$marker;
    }

    private function inlineCode(DOMElement $node): string
    {
        $code = $node->textContent;

        return str_contains($code, '`') ? '``'.$code.'``' : '`'.$code.'`';
    }

    private function codeBlock(DOMElement $node): string
    {
        $code = rtrim($this->codeText($node));
        $class = $node->getAttribute('class').' '.(($node->getElementsByTagName('code')->item(0)?->getAttribute('class')) ?? '');
        $language = preg_match('/(?:language|lang)-([\w+-]+)/', $class, $match) ? $match[1] : '';

        return "\n\n```{$language}\n{$code}\n```\n\n";
    }

    /**
     * A code block's text, keeping its line breaks (highlighters often put each
     * line in its own element) and leaving out copy buttons and the like.
     */
    private function codeText(DOMNode $node): string
    {
        $text = '';

        foreach ($node->childNodes as $child) {
            if ($child->nodeType === XML_TEXT_NODE) {
                $text .= $child->textContent;
            } elseif ($child instanceof DOMElement && ! in_array(strtolower($child->nodeName), ['button', 'svg', 'style', 'script'], true)) {
                $isLine = str_contains(' '.$child->getAttribute('class').' ', ' line ');
                $text .= $this->codeText($child).($isLine && ! str_ends_with($child->textContent, "\n") ? "\n" : '');
            }
        }

        return $text;
    }

    private function link(DOMElement $node): string
    {
        $text = $this->inline($node);
        $href = $node->getAttribute('href');

        if ($text === '' || $href === '' || str_starts_with($href, '#') || str_starts_with($href, 'javascript:')) {
            return $text;
        }

        if ($this->origin !== null && str_starts_with($href, '/') && ! str_starts_with($href, '//')) {
            $href = $this->origin.$href;
        }

        return '['.$text.']('.$href.')';
    }

    private function listBlock(DOMElement $node, bool $ordered): string
    {
        $items = [];
        $number = 1;

        foreach ($node->childNodes as $child) {
            if (! $child instanceof DOMElement || strtolower($child->nodeName) !== 'li') {
                continue;
            }

            $text = trim(preg_replace("/\n{2,}/", "\n", $this->children($child)) ?? '');
            $marker = $ordered ? ($number++).'. ' : '- ';
            $items[] = $marker.str_replace("\n", "\n   ", $text);
        }

        return "\n\n".implode("\n", $items)."\n\n";
    }

    private function table(DOMElement $node): string
    {
        $rows = [];

        foreach ($node->getElementsByTagName('tr') as $row) {
            $cells = [];

            foreach ($row->childNodes as $cell) {
                if ($cell instanceof DOMElement && in_array(strtolower($cell->nodeName), ['td', 'th'], true)) {
                    $cells[] = str_replace('|', '\|', $this->inline($cell));
                }
            }

            if ($cells !== []) {
                $rows[] = '| '.implode(' | ', $cells).' |';
            }
        }

        if ($rows === []) {
            return '';
        }

        $columns = substr_count($rows[0], ' | ') + 1;
        array_splice($rows, 1, 0, ['|'.str_repeat(' --- |', $columns)]);

        return "\n\n".implode("\n", $rows)."\n\n";
    }
}
