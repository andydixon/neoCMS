<?php

namespace NeoCMS;

/**
 * Finds what NeoCMS could edit in a page (content containers, images, SEO tags) and adds the markers.
 *
 * Pages are never re-serialised through a DOM parser: a tokenizer locates tag offsets and apply() splices
 * text in, so every byte outside the inserted markers is preserved exactly.
 */
final class SiteAnalyser
{
    private const VOID = ['area', 'base', 'br', 'col', 'embed', 'hr', 'img', 'input', 'link', 'meta', 'param', 'source', 'track', 'wbr'];
    private const SKIPPED = ['header', 'nav', 'footer', 'aside', 'form'];
    private const SKIPPED_ROLES = ['navigation', 'banner', 'contentinfo'];
    private const MIN_SECTION_TEXT = 40;

    /** Describe a page: existing and proposed editable regions, images, local references, and SEO tags present. */
    public static function analyse(string $html, string $editableClass): array
    {
        $c = self::scan($html, $editableClass, true);
        return [
            'title' => $c['title'],
            'existingRegions' => $c['existing'],
            'proposed' => array_map(fn(int $i) => $c['els'][$i]['name'], $c['regions']),
            'images' => array_map(fn(array $i) => ['src' => $i['src'], 'hasAlt' => $i['hasAlt'], 'inRegion' => $i['inRegion'], 'tagged' => $i['tagged']], $c['images']),
            'refs' => $c['refs'],
            'seo' => $c['seo'],
        ];
    }

    /**
     * Insert the requested markers into a page.
     *
     * @param array $options Booleans: content, images, seo.
     * @return array|null ['html' => string, 'changes' => string[]], or null when the page needs no change.
     */
    public static function apply(string $html, string $editableClass, array $options): ?array
    {
        $c = self::scan($html, $editableClass, !empty($options['content']));
        $edits = [];
        $changes = [];

        if (!empty($options['content'])) {
            foreach ($c['regions'] as $i) {
                $edit = self::classEdit($html, $c['els'][$i], $editableClass);
                if ($edit) {
                    $edits[] = $edit;
                    $changes[] = "Made <{$c['els'][$i]['name']}> editable";
                }
            }
        }
        if (!empty($options['images'])) {
            foreach ($c['images'] as $img) {
                if (!$img['inRegion'] && !$img['tagged'] && $img['taggable']) {
                    $edits[] = [$img['insertAt'], ' data-neo-image'];
                    $changes[] = !empty($img['placeholder'])
                        ? 'Made image placeholder editable' . ($img['label'] !== '' ? ': ' . $img['label'] : '')
                        : 'Made image editable: ' . $img['src'];
                }
            }
        }
        if (!empty($options['seo']) && ($seo = self::seoEdit($html, $c))) {
            $edits[] = $seo['edit'];
            array_push($changes, ...$seo['changes']);
        }
        if (!$edits) {
            return null;
        }

        usort($edits, fn(array $a, array $b) => $b[0] <=> $a[0]);
        foreach ($edits as [$offset, $text]) {
            $html = substr($html, 0, $offset) . $text . substr($html, $offset);
        }
        return ['html' => $html, 'changes' => $changes];
    }

    /** Tokenise a page and gather everything analyse() and apply() need. */
    private static function scan(string $html, string $class, bool $withRegions): array
    {
        $els = self::elements($html);
        $existing = 0;
        foreach ($els as $k => $e) {
            $els[$k]['editable'] = self::hasClassToken($e['attrs'], $class) || ($e['parent'] >= 0 && $els[$e['parent']]['editable']);
            $existing += self::hasClassToken($e['attrs'], $class) ? 1 : 0;
        }

        $regions = ($withRegions && $existing === 0) ? self::chooseRegions($html, $els) : [];
        $inChosen = static function (int $k) use ($els, $regions): bool {
            for ($i = $k; $i >= 0; $i = $els[$i]['parent']) {
                if (in_array($i, $regions, true)) {
                    return true;
                }
            }
            return false;
        };

        // Containers that already hold a real <img> are not placeholders.
        $hasImg = [];
        foreach ($els as $e) {
            for ($p = $e['name'] === 'img' ? $e['parent'] : -1; $p >= 0; $p = $els[$p]['parent']) {
                $hasImg[$p] = true;
            }
        }

        $images = [];
        $refs = [];
        $meta = [];
        $title = null;
        $h1 = '';
        $firstParagraph = '';
        foreach ($els as $k => $e) {
            $name = $e['name'];
            if ($name === 'img') {
                $src = self::attr($e['attrs'], 'src');
                $images[] = [
                    'src' => (string) $src,
                    'hasAlt' => self::attr($e['attrs'], 'alt') !== null,
                    'tagged' => self::attr($e['attrs'], 'data-neo-image') !== null,
                    'inRegion' => $e['editable'] || $inChosen($k),
                    'taggable' => $src !== null && $src !== '' && !str_starts_with(strtolower(trim($src)), 'data:')
                        && !(self::attr($e['attrs'], 'width') === '1' && self::attr($e['attrs'], 'height') === '1'),
                    'insertAt' => $e['start'] + 1 + strlen($name),
                    'skip' => $e['skip'],
                ];
                if ($src) {
                    $refs[] = ['type' => 'img', 'url' => $src];
                }
            } elseif (strtolower((string) self::attr($e['attrs'], 'role')) === 'img' && !in_array($name, ['svg'], true)
                && self::attr($e['attrs'], 'aria-hidden') !== 'true' && empty($hasImg[$k])) {
                // A styled box standing in for a photo (for example a "Your photo here" block).
                $images[] = [
                    'src' => '', 'hasAlt' => true, 'placeholder' => true,
                    'label' => (string) self::attr($e['attrs'], 'aria-label'),
                    'tagged' => self::attr($e['attrs'], 'data-neo-image') !== null,
                    'inRegion' => $e['editable'] || $inChosen($k),
                    'taggable' => true,
                    'insertAt' => $e['start'] + 1 + strlen($name),
                    'skip' => $e['skip'],
                ];
            } elseif ($name === 'link' && preg_match('/\bstylesheet\b/i', (string) self::attr($e['attrs'], 'rel')) && ($h = self::attr($e['attrs'], 'href'))) {
                $refs[] = ['type' => 'link', 'url' => $h];
            } elseif ($name === 'script' && ($s = self::attr($e['attrs'], 'src'))) {
                $refs[] = ['type' => 'script', 'url' => $s];
            } elseif ($name === 'meta') {
                $key = self::attr($e['attrs'], 'name') ?? self::attr($e['attrs'], 'property');
                if ($key !== null && trim((string) self::attr($e['attrs'], 'content')) !== '') {
                    $meta[strtolower($key)] = trim(html_entity_decode((string) self::attr($e['attrs'], 'content'), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
                }
            } elseif ($name === 'title' && $title === null) {
                $title = self::text($html, $e);
            } elseif ($name === 'h1' && $h1 === '') {
                $h1 = self::text($html, $e);
            } elseif ($name === 'p' && $firstParagraph === '' && !$e['skip'] && strlen($t = self::text($html, $e)) >= 20) {
                $firstParagraph = $t;
            }
        }
        $canonical = false;
        foreach ($els as $e) {
            if ($e['name'] === 'link' && preg_match('/\bcanonical\b/i', (string) self::attr($e['attrs'], 'rel'))) {
                $canonical = true;
            }
        }

        return [
            'els' => $els, 'existing' => $existing, 'regions' => $regions, 'images' => $images, 'refs' => $refs,
            'title' => (string) $title, 'h1' => $h1, 'paragraph' => $firstParagraph, 'metaText' => $meta,
            'seo' => [
                'title' => trim((string) $title) !== '', 'description' => isset($meta['description']), 'canonical' => $canonical,
                'ogTitle' => isset($meta['og:title']), 'ogDescription' => isset($meta['og:description']),
                'ogImage' => isset($meta['og:image']), 'robots' => isset($meta['robots']),
            ],
        ];
    }

    /** Flat list of elements with source offsets, parent index, skipped-subtree and close-position data. */
    private static function elements(string $html): array
    {
        $pattern = '#<!--.*?-->|<(script|style|textarea|template)\b[^>]*>.*?</\1\s*>|<(/?)([a-zA-Z][a-zA-Z0-9:-]*)((?:"[^"]*"|\'[^\']*\'|[^>"\'])*)>#is';
        preg_match_all($pattern, $html, $tokens, PREG_SET_ORDER | PREG_OFFSET_CAPTURE);

        $els = [];
        $stack = [];
        foreach ($tokens as $t) {
            // Group 3 is the tag name; comments and raw script/style blocks never set it, but script src still matters.
            if (!isset($t[3]) || $t[3][1] < 0) {
                if (!empty($t[1][0]) && preg_match('#^<script\b([^>]*)>#i', $t[0][0], $m)) {
                    $els[] = ['name' => 'script', 'attrs' => $m[1], 'start' => $t[0][1], 'end' => $t[0][1] + strlen($m[0]), 'parent' => $stack ? $stack[count($stack) - 1] : -1, 'close' => $t[0][1] + strlen($t[0][0]), 'skip' => true, 'editable' => false];
                }
                continue;
            }
            $name = strtolower($t[3][0]);
            if ($t[2][0] === '/') {
                // Close the nearest open element of this name and anything implicitly left open inside it.
                for ($j = count($stack) - 1; $j >= 0 && $els[$stack[$j]]['name'] !== $name; $j--);
                if ($j >= 0) {
                    for ($x = count($stack) - 1; $x >= $j; $x--) {
                        $els[$stack[$x]]['close'] = $t[0][1];
                    }
                    $stack = array_slice($stack, 0, $j);
                }
                continue;
            }
            $parent = $stack ? $stack[count($stack) - 1] : -1;
            $attrs = $t[4][0];
            $skip = in_array($name, self::SKIPPED, true)
                || in_array(strtolower((string) self::attr($attrs, 'role')), self::SKIPPED_ROLES, true)
                || ($parent >= 0 && $els[$parent]['skip']);
            $els[] = ['name' => $name, 'attrs' => $attrs, 'start' => $t[0][1], 'end' => $t[0][1] + strlen($t[0][0]), 'parent' => $parent, 'close' => null, 'skip' => $skip, 'editable' => false];
            if (!in_array($name, self::VOID, true) && !str_ends_with(rtrim($attrs), '/')) {
                $stack[] = count($els) - 1;
            }
        }
        return $els;
    }

    /** Pick the containers to make editable: <main>, else top-level articles, else text-bearing sections. */
    private static function chooseRegions(string $html, array $els): array
    {
        $isMain = fn(array $e) => $e['name'] === 'main' || strtolower((string) self::attr($e['attrs'], 'role')) === 'main';
        foreach ([$isMain, fn(array $e) => $e['name'] === 'article', fn(array $e) => $e['name'] === 'section' && strlen(self::text($html, $e)) >= self::MIN_SECTION_TEXT] as $match) {
            $chosen = [];
            foreach ($els as $k => $e) {
                if ($e['skip'] || !$match($e)) {
                    continue;
                }
                for ($p = $e['parent']; $p >= 0; $p = $els[$p]['parent']) {
                    if (in_array($p, $chosen, true)) {
                        continue 2;
                    }
                }
                $chosen[] = $k;
            }
            if ($chosen) {
                return $chosen;
            }
        }
        return [];
    }

    /** Edit that adds the editable class to an element's opening tag, or null when it cannot be done safely. */
    private static function classEdit(string $html, array $el, string $class): ?array
    {
        $tag = substr($html, $el['start'], $el['end'] - $el['start']);
        if (preg_match('/\sclass\s*=\s*(["\'])(.*?)\1/is', $tag, $m, PREG_OFFSET_CAPTURE)) {
            $closeQuote = $m[0][1] + strlen($m[0][0]) - 1;
            return [$el['start'] + $closeQuote, trim($m[2][0]) === '' ? $class : ' ' . $class];
        }
        if (preg_match('/\sclass\s*=/i', $tag)) {
            return null; // Unquoted class value: leave for manual tagging.
        }
        return [$el['start'] + 1 + strlen($el['name']), ' class="' . $class . '"'];
    }

    /** One combined insertion of every missing SEO tag just before </head>, with values derived from the page. */
    private static function seoEdit(string $html, array $c): ?array
    {
        if (!preg_match('#^([ \t]*)</head\s*>#im', $html, $m, PREG_OFFSET_CAPTURE)
            && !preg_match('#</head\s*>#i', $html, $m, PREG_OFFSET_CAPTURE)) {
            return null;
        }
        $seo = $c['seo'];
        $esc = static fn(string $s) => htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $trim = static fn(string $s, int $n) => mb_strlen($s) > $n ? rtrim(mb_substr($s, 0, $n - 3)) . '...' : $s;
        $valid = static fn(string $s) => $s !== '' && mb_check_encoding($s, 'UTF-8');

        $title = trim($c['title']) !== '' ? trim($c['title']) : $c['h1'];
        // Prefer the page's own description over one derived from its first paragraph.
        $description = (string) ($c['metaText']['description'] ?? '') !== '' ? $c['metaText']['description'] : ($valid($c['paragraph']) ? $trim($c['paragraph'], 155) : '');
        $image = '';
        // A content image makes a better social preview than a header logo.
        foreach ([false, true] as $allowSkipped) {
            foreach ($c['images'] as $img) {
                // Open Graph needs an absolute or root-relative address; a page-relative one would be wrong.
                if ($img['taggable'] && preg_match('#^(/|https?://)#i', $img['src']) && ($allowSkipped || !$img['skip'])) {
                    $image = $img['src'];
                    break 2;
                }
            }
        }

        $tags = [];
        $changes = [];
        if (!$seo['title'] && $valid($c['h1'])) {
            $tags[] = '<title>' . $esc($c['h1']) . '</title>';
            $changes[] = 'Added page title';
        }
        if (!$seo['description'] && $description !== '') {
            $tags[] = '<meta name="description" content="' . $esc($description) . '">';
            $changes[] = 'Added meta description';
        }
        if (!$seo['ogTitle'] && $valid($title)) {
            $tags[] = '<meta property="og:title" content="' . $esc($title) . '">';
            $changes[] = 'Added og:title';
        }
        if (!$seo['ogDescription'] && $description !== '') {
            $tags[] = '<meta property="og:description" content="' . $esc($description) . '">';
            $changes[] = 'Added og:description';
        }
        if (!$seo['ogImage'] && $image !== '') {
            $tags[] = '<meta property="og:image" content="' . $esc($image) . '">';
            $changes[] = 'Added og:image';
        }
        if (!$tags) {
            return null;
        }

        $atLineStart = isset($m[1]) && $m[1][0] !== null && $m[0][1] === $m[1][1];
        $offset = $m[0][1];
        // Match the page's own line endings so CRLF files do not end up with mixed endings.
        $nl = str_contains($html, "\r\n") ? "\r\n" : "\n";
        $body = '    ' . implode($nl . '    ', $tags) . $nl;
        $text = $atLineStart ? $body : $nl . $body;

        return ['edit' => [$offset, $text], 'changes' => $changes];
    }

    /** Visible text of an element (tags stripped, entities decoded, whitespace collapsed). */
    private static function text(string $html, array $el): string
    {
        $end = $el['close'] ?? $el['end'];
        $inner = substr($html, $el['end'], max(0, $end - $el['end']));
        return trim(preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags($inner), ENT_QUOTES | ENT_HTML5, 'UTF-8')) ?? '');
    }

    private static function hasClassToken(string $attrs, string $class): bool
    {
        return in_array($class, preg_split('/\s+/', (string) self::attr($attrs, 'class')) ?: [], true);
    }

    /** Return an attribute's value from a tag's attribute string, '' for a bare attribute, null when absent. */
    private static function attr(string $attrs, string $name): ?string
    {
        if (!preg_match('/(?:^|\s)' . preg_quote($name, '/') . '(?:\s*=\s*(?:"([^"]*)"|\'([^\']*)\'|([^\s>]+)))?(?=\s|\/|$)/i', $attrs, $m)) {
            return null;
        }
        return (string) ($m[1] ?? '') . (string) ($m[2] ?? '') . (string) ($m[3] ?? '');
    }
}
