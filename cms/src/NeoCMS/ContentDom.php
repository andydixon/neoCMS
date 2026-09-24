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

    /**
     * Replace the list inside every element marked data-neo-menu="$name", keeping the element's own attributes
     * and every other byte of the page. Null when nothing needs to change.
     */
    public static function withMenu(string $html, string $name, array $items, string $uri = '', string $basePath = ''): ?string
    {
        if (!str_contains($html, 'data-neo-menu')) {
            return null;
        }
        $list = self::menuList($items, $uri, $basePath);
        $flat = fn(string $s) => preg_replace('/>\s+</', '><', trim($s));
        $edits = [];
        foreach (SiteAnalyser::navs($html) as $nav) {
            if ($nav['menu'] !== $name || $nav['close'] === null) {
                continue;
            }
            if ($flat(substr($html, $nav['end'], $nav['close'] - $nav['end'])) !== $flat($list)) {
                $edits[] = [$nav['end'], $nav['close'] - $nav['end']];
            }
        }
        rsort($edits);
        foreach ($edits as [$offset, $length]) {
            $html = substr($html, 0, $offset) . $list . substr($html, $offset + $length);
        }
        return $edits ? $html : null;
    }

    /** A menu as a standalone <nav> (the list itself comes from menuList). */
    public static function renderMenu(string $name, array $items): string
    {
        return '<nav data-neo-menu="' . htmlspecialchars($name, ENT_QUOTES) . '">' . self::menuList($items) . '</nav>';
    }

    /**
     * Render a nested, escaped list from flat parent-labelled items, marking the link to the page being written.
     * Circular parent references are stopped by the ancestor list rather than pursued forever.
     */
    public static function menuList(array $items, string $uri = '', string $basePath = ''): string
    {
        $children = [];
        foreach ($items as $item) {
            $children[$item['parent'] ?? ''][] = $item;
        }
        $render = function (string $parent, array $ancestors = []) use (&$render, $children, $uri, $basePath): string {
            if (empty($children[$parent])) {
                return '';
            }
            $html = '<ul>';
            foreach ($children[$parent] as $item) {
                $label = htmlspecialchars($item['label'], ENT_QUOTES);
                $url = htmlspecialchars($item['url'], ENT_QUOTES);
                $current = $uri !== '' && self::isPage($item['url'], $uri, $basePath) ? ' aria-current="page"' : '';
                $nested = in_array($item['label'], $ancestors, true) ? '' : $render($item['label'], array_merge($ancestors, [$item['label']]));
                $html .= '<li><a href="' . $url . '"' . $current . '>' . $label . '</a>' . $nested . '</li>';
            }
            return $html . '</ul>';
        };
        return $render('');
    }

    /** Turn a page link into a site path (relative links resolved against the page, query/fragment kept); null for external or fragment-only links. */
    public static function resolveLink(string $url, string $pageUri): ?string
    {
        $url = trim($url);
        $path = parse_url($url, PHP_URL_PATH);
        if ($url === '' || $url[0] === '#' || preg_match('#^([a-z][a-z0-9+.-]*:|//)#i', $url) || !is_string($path) || $path === '') {
            return null;
        }
        if ($path[0] !== '/') {
            $path = rtrim(str_replace('\\', '/', dirname($pageUri)), '/') . '/' . $path;
        }
        $out = [];
        foreach (explode('/', $path) as $segment) {
            if ($segment === '' || $segment === '.') {
                continue;
            }
            $segment === '..' ? array_pop($out) : $out[] = $segment;
        }
        return '/' . implode('/', $out) . (str_ends_with($path, '/') && $out ? '/' : '') . substr($url, strcspn($url, '?#'));
    }

    /** Whether a menu link points at the page with this URI (a folder link means its index.html; a subfolder install's prefix is ignored). */
    private static function isPage(string $url, string $uri, string $basePath): bool
    {
        $resolved = self::resolveLink($url, $uri);
        if ($resolved === null) {
            return false;
        }
        $index = fn(string $p) => str_ends_with($p, '/') ? $p . 'index.html' : $p;
        $path = $index((string) parse_url($resolved, PHP_URL_PATH));
        $uri = $index($uri);
        return $path === $uri || ($basePath !== '' && $path === $basePath . $uri);
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
