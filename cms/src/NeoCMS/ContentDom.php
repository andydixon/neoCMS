<?php

namespace NeoCMS;

/** Pure HTML transformations for shared regions, generated menus, and editable-region detection. */
final class ContentDom
{
    /** Return each data-neo-shared region's inner HTML, keyed by its shared name. */
    public static function sharedBlocks(string $html): array
    {
        $found = [];
        foreach ((new \DOMXPath(self::load($html)))->query('//*[@data-neo-shared]') as $node) {
            $key = $node->getAttribute('data-neo-shared');
            if ($key !== '') {
                $found[$key] = self::innerHtml($node);
            }
        }
        return $found;
    }

    /** Replace every region marked data-neo-shared="$key"; null when the page has none. */
    public static function withShared(string $html, string $key, string $content): ?string
    {
        if (!str_contains($html, 'data-neo-shared')) {
            return null;
        }
        $dom = self::load($html);
        // $key is restricted to [a-zA-Z0-9_-] by the caller, so it cannot break out of the XPath string.
        $nodes = (new \DOMXPath($dom))->query('//*[@data-neo-shared="' . $key . '"]');
        if ($nodes->length === 0) {
            return null;
        }
        foreach ($nodes as $node) {
            self::replaceInnerHtml($dom, $node, $content);
        }
        return $dom->saveHTML();
    }

    /** Replace every element marked data-neo-menu="$name" with the rendered <nav>; null when nothing to change. */
    public static function withMenu(string $html, string $name, string $menuHtml): ?string
    {
        if (!str_contains($html, 'data-neo-menu')) {
            return null;
        }
        $dom = self::load($html);
        $nodes = (new \DOMXPath($dom))->query('//*[@data-neo-menu="' . $name . '"]');
        $sourceNav = self::load($menuHtml)->getElementsByTagName('nav')->item(0);
        if ($nodes->length === 0 || !$sourceNav) {
            return null;
        }
        foreach (iterator_to_array($nodes) as $node) {
            $node->parentNode->replaceChild($dom->importNode($sourceNav, true), $node);
        }
        return $dom->saveHTML();
    }

    /**
     * Render a nested, escaped navigation list from flat parent-labelled items.
     * Circular parent references are stopped by the ancestor list rather than pursued forever.
     */
    public static function renderMenu(string $name, array $items): string
    {
        $children = [];
        foreach ($items as $item) {
            $children[$item['parent'] ?? ''][] = $item;
        }
        $render = function (string $parent, array $ancestors = []) use (&$render, $children): string {
            if (empty($children[$parent])) {
                return '';
            }
            $html = '<ul>';
            foreach ($children[$parent] as $item) {
                $label = htmlspecialchars($item['label'], ENT_QUOTES);
                $url = htmlspecialchars($item['url'], ENT_QUOTES);
                $nested = in_array($item['label'], $ancestors, true) ? '' : $render($item['label'], array_merge($ancestors, [$item['label']]));
                $html .= '<li><a href="' . $url . '">' . $label . '</a>' . $nested . '</li>';
            }
            return $html . '</ul>';
        };
        return '<nav data-neo-menu="' . htmlspecialchars($name, ENT_QUOTES) . '">' . $render('') . '</nav>';
    }

    /** Determine whether a document contains at least one element carrying exactly this CSS class token. */
    public static function hasClass(string $html, string $class): bool
    {
        $dom = new \DOMDocument();
        @$dom->loadHTML($html);
        // Class-token matching avoids treating "editable-extra" as though it were "editable".
        $query = '//*[contains(concat(" ", normalize-space(@class), " "), " ' . $class . ' ")]';
        return (new \DOMXPath($dom))->query($query)->length > 0;
    }

    /** Parse HTML as UTF-8 (libxml assumes Latin-1 otherwise) without keeping the encoding hint in the output. */
    private static function load(string $html): \DOMDocument
    {
        $dom = new \DOMDocument();
        @$dom->loadHTML('<?xml encoding="UTF-8">' . $html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        foreach (iterator_to_array($dom->childNodes) as $node) {
            if ($node->nodeType === XML_PI_NODE) {
                $dom->removeChild($node);
            }
        }
        return $dom;
    }

    /**
     * Replace a DOM node's children with an HTML fragment.
     *
     * DOMDocument parses forgiving HTML here rather than strict XML, allowing ordinary authoring
     * fragments such as <br> without demanding that editors suddenly become XML librarians.
     */
    private static function replaceInnerHtml(\DOMDocument $dom, \DOMNode $node, string $html): void
    {
        while ($node->firstChild) {
            $node->removeChild($node->firstChild);
        }
        $wrapper = self::load('<div id="neo-fragment">' . $html . '</div>')->getElementById('neo-fragment');
        if (!$wrapper) {
            $node->appendChild($dom->createTextNode($html));
            return;
        }
        foreach (iterator_to_array($wrapper->childNodes) as $child) {
            $node->appendChild($dom->importNode($child, true));
        }
    }

    /** Serialise only the children of a DOM node, excluding the node's own wrapper tag. */
    private static function innerHtml(\DOMNode $node): string
    {
        $html = '';
        foreach ($node->childNodes as $child) {
            $html .= $node->ownerDocument->saveHTML($child);
        }
        return $html;
    }
}
