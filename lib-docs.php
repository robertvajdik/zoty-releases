<?php
declare(strict_types=1);
/**
 * Zoty – manuals (help pages) editable in the admin.
 * Table changelog_docs: one Markdown document per (slug, lang). A missing document is created
 * from its seed file the first time it is requested (MANUAL.md, docs/*.md).
 */

// 'portal' => true: customers see it in the portal (admins see every manual)
const DOCS = [
    'admin'  => ['label' => 'Manuál administrace', 'langs' => ['en', 'cs'], 'seed' => ['en' => 'MANUAL.md'], 'portal' => false],
    'portal' => ['label' => 'Manuál zákaznického portálu', 'langs' => ['cs', 'en'], 'seed' => ['cs' => 'docs/portal.cs.md', 'en' => 'docs/portal.en.md'], 'portal' => true],
    'zoty'   => ['label' => 'Manuál Zoty', 'langs' => ['cs'], 'seed' => ['cs' => 'docs/manual/manual.md'], 'portal' => true],
];

const DOC_NOTES = ['note' => 'Poznámka', 'tip' => 'Tip', 'info' => 'Informace', 'caution' => 'Pozor', 'warning' => 'Varování', 'danger' => 'Nebezpečí'];

/** Every picture of the manuals lives here; served to signed-in users by manual-img.php. */
const DOC_IMG_DIR = 'docs/manual/img';
const DOC_IMG_EXT = ['png' => 'image/png', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'gif' => 'image/gif', 'webp' => 'image/webp'];

/** Tab name of a manual: the name of $lang, else a name set for another language, else the default label. */
function doc_tab_name(string $slug, string $lang): string
{
    $default = DOCS[$slug]['label'] ?? $slug;
    foreach (array_unique(array_merge([$lang, 'cs'], LANGS)) as $l) {
        if (($n = doc_name($slug, $l)) !== $default) return $n;
    }
    return $default;
}

/** Language versions of a manual: the configured ones plus any created in the editor, in LANGS order. */
function doc_langs(string $slug): array
{
    static $db = null;
    if ($db === null) {
        $db = [];
        foreach (db()->query('SELECT slug, lang FROM ' . t('changelog_docs')) as $r) $db[$r['slug']][] = $r['lang'];
    }
    $have = array_merge(DOCS[$slug]['langs'] ?? [], $db[$slug] ?? []);
    return array_values(array_filter(LANGS, fn($l) => in_array($l, $have, true)));
}

/** Manuals customers can read in the portal. */
function portal_docs(): array
{
    return array_keys(array_filter(DOCS, fn($d) => !empty($d['portal'])));
}

/**
 * Where an image of a manual comes from. Accepts img/x.png (= docs/manual/img), /docs/img/x.png (Docusaurus),
 * docs/manual/img/x.png, uploads/… (also the older uploads/manual/x.png, now in docs/manual/img) and http(s) URLs.
 * Returns ['src' => url, 'mobile' => url|null] or null when the reference is not allowed / not found.
 */
function doc_img(string $ref): ?array
{
    $ref = trim($ref);
    if (preg_match('#^https?://#i', $ref)) return ['src' => $ref, 'mobile' => null];
    $file = null;
    if (preg_match('#^/?(?:docs/(?:manual/)?)?img/([^/]+)$#', $ref, $m)) {
        $file = $m[1];
    } elseif (str_starts_with($ref, 'uploads/') && !str_contains($ref, '..')) {
        if (is_file(__DIR__ . '/' . $ref)) {
            $mob = preg_replace('/(\.[a-z0-9]+)$/i', '-mobile$1', $ref);
            return ['src' => site_url($ref), 'mobile' => $mob !== $ref && is_file(__DIR__ . '/' . $mob) ? site_url($mob) : null];
        }
        if (preg_match('#^uploads/manual/([^/]+)$#', $ref, $m)) $file = $m[1];
    }
    if ($file === null || !doc_img_file($file)) return null;
    $mob = preg_replace('/(\.[a-z0-9]+)$/i', '-mobile$1', $file);
    return ['src' => doc_img_url($file), 'mobile' => $mob !== $file && doc_img_file($mob) ? doc_img_url($mob) : null];
}

/** Absolute path of a picture in DOC_IMG_DIR, or null (bad name, other type, missing). */
function doc_img_file(string $name): ?string
{
    if (!preg_match('/^[\w\-. ]+\.([a-z0-9]+)$/i', $name, $m) || str_starts_with($name, '.') || !isset(DOC_IMG_EXT[strtolower($m[1])])) return null;
    $path = __DIR__ . '/' . DOC_IMG_DIR . '/' . $name;
    return is_file($path) ? $path : null;
}

function doc_img_url(string $name): string
{
    return site_url('manual-img?f=' . rawurlencode($name));
}

/**
 * Docusaurus / HTML bits of imported manuals turned into the Markdown doc_render() understands:
 * comments removed, <img src title width> -> ![title](src =width), &nbsp; lines -> blank,
 * tabs in headings kept apart (Docusaurus ids drop them: "3.6<TAB>Filtry" -> #36filtry).
 */
function doc_normalize(string $md): string
{
    $md = preg_replace('/<!--.*?-->/s', '', $md);
    $md = preg_replace_callback('/<img\b([^>]*?)\/?>/i', function ($m) {
        $a = [];
        preg_match_all('/(\w+)\s*=\s*"([^"]*)"/', $m[1], $mm, PREG_SET_ORDER);
        foreach ($mm as $x) $a[strtolower($x[1])] = html_entity_decode($x[2], ENT_QUOTES, 'UTF-8');
        if (empty($a['src'])) return '';
        $alt = str_replace(['[', ']'], '', $a['alt'] ?? $a['title'] ?? '');
        $w = isset($a['width']) && ctype_digit(trim($a['width'])) ? ' =' . trim($a['width']) : '';
        return '![' . $alt . '](' . str_replace(' ', '%20', trim($a['src'])) . $w . ')';
    }, $md);
    $md = preg_replace('/^[ \t]*(&nbsp;[ \t]*)+$/m', '', $md);
    $md = str_replace('&nbsp;', "\u{00A0}", $md);
    return preg_replace_callback('/^(#{1,6})[ \t]+(.*)$/m', fn($m) => $m[1] . ' ' . str_replace("\t", "\u{E000}", $m[2]), $md);
}

function migrate_docs(PDO $pdo, string $driver): void
{
    $d = t('changelog_docs');
    if ($driver === 'sqlite') {
        $pdo->exec("CREATE TABLE IF NOT EXISTS $d (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            slug TEXT NOT NULL,
            lang TEXT NOT NULL,
            body TEXT NOT NULL DEFAULT '',
            updated_at TEXT NOT NULL,
            updated_by TEXT NOT NULL DEFAULT '',
            UNIQUE (slug, lang))");
    } else {
    $pdo->exec("CREATE TABLE IF NOT EXISTS `$d` (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
        slug VARCHAR(40) NOT NULL,
        lang VARCHAR(5) NOT NULL,
        body MEDIUMTEXT NOT NULL,
        updated_at DATETIME NOT NULL,
        updated_by VARCHAR(160) NOT NULL DEFAULT '',
        UNIQUE KEY uq_slug_lang (slug, lang)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_czech_ci");
    }
    // v2: editable manual name (empty = default label from DOCS)
    try {
        $pdo->query("SELECT title FROM $d WHERE 1 = 0");
    } catch (PDOException $ex) {
        $pdo->exec($driver === 'sqlite'
            ? "ALTER TABLE $d ADD COLUMN title TEXT NOT NULL DEFAULT ''"
            : "ALTER TABLE `$d` ADD COLUMN title VARCHAR(120) NOT NULL DEFAULT '' AFTER lang");
    }
}

const DOC_NAME_MAX = 120;

/** Name of a manual in a language: the edited name, else the default label. */
function doc_name(string $slug, string $lang): string
{
    static $names = null;
    if ($names === null) {
        $names = [];
        foreach (db()->query('SELECT slug, lang, title FROM ' . t('changelog_docs')) as $r) {
            $names[$r['slug']][$r['lang']] = (string)$r['title'];
        }
    }
    $n = trim($names[$slug][$lang] ?? '');
    return $n !== '' ? $n : (DOCS[$slug]['label'] ?? $slug);
}

function doc_seed(string $slug, string $lang): ?string
{
    $file = DOCS[$slug]['seed'][$lang] ?? null;
    if (!$file || !is_file(__DIR__ . '/' . $file)) return null;
    return str_replace("\r\n", "\n", (string)file_get_contents(__DIR__ . '/' . $file));
}

/** The document for slug + lang; created from the seed file when missing. Null when there is neither. */
function doc_get(string $slug, string $lang): ?array
{
    if (!isset(DOCS[$slug])) return null;
    $st = db()->prepare('SELECT * FROM ' . t('changelog_docs') . ' WHERE slug = ? AND lang = ?');
    $st->execute([$slug, $lang]);
    if ($row = $st->fetch()) return $row;
    $seed = doc_seed($slug, $lang);
    if ($seed === null) return null;
    doc_save($slug, $lang, $seed, '');
    $st->execute([$slug, $lang]);
    return $st->fetch() ?: null;
}

/** Document in the preferred language, else in any other language of the slug. */
function doc_for(string $slug, string $lang): ?array
{
    foreach (array_unique(array_merge([$lang], DOCS[$slug]['langs'] ?? [])) as $l) {
        if ($d = doc_get($slug, $l)) return $d;
    }
    return null;
}

/** Renames one language version (empty = default name). */
function doc_rename(string $slug, string $lang, string $title): void
{
    db()->prepare('UPDATE ' . t('changelog_docs') . ' SET title = ? WHERE slug = ? AND lang = ?')->execute([$title, $slug, $lang]);
}

/** Saves the text (and the name, unless $title is null). */
function doc_save(string $slug, string $lang, string $body, string $by, ?string $title = null): void
{
    $tbl = t('changelog_docs');
    if ($title !== null) {
        $st = db()->prepare("UPDATE $tbl SET body = ?, title = ?, updated_at = ?, updated_by = ? WHERE slug = ? AND lang = ?");
        $st->execute([$body, $title, now(), $by, $slug, $lang]);
    } else {
        $st = db()->prepare("UPDATE $tbl SET body = ?, updated_at = ?, updated_by = ? WHERE slug = ? AND lang = ?");
        $st->execute([$body, now(), $by, $slug, $lang]);
    }
    if ($st->rowCount() === 0) {
        try {
            db()->prepare("INSERT INTO $tbl (slug, lang, title, body, updated_at, updated_by) VALUES (?, ?, ?, ?, ?, ?)")
                ->execute([$slug, $lang, (string)$title, $body, now(), $by]);
        } catch (PDOException $ex) {
            // row exists with identical content (rowCount 0 on MySQL) – nothing to do
        }
    }
}

/** First "# " heading of a document. */
function doc_title(string $md, string $fallback = ''): string
{
    return preg_match('/^#\s+(.+)$/m', $md, $m) ? trim(html_entity_decode(strip_tags(doc_inline($m[1])), ENT_QUOTES, 'UTF-8')) : $fallback;
}

/* ---------------------------------------------------------------- Markdown → HTML for manuals */

/** GitHub-style heading id: "2.3 Raising a new request" → "23-raising-a-new-request". */
function doc_slug(string $text): string
{
    $t = mb_strtolower(preg_replace('/[`*_~\[\]]|\]\([^)]*\)/u', '', $text));
    $t = preg_replace('/[^\p{L}\p{N}\s\-_]/u', '', $t);
    return preg_replace('/\s/u', '-', trim($t));
}

/** <img> or <figure> for a manual picture; null when the source is not allowed. */
function doc_img_html(string $alt, string $ref, string $width = '', bool $figure = false): ?string
{
    $img = doc_img(str_replace('%20', ' ', $ref));
    if (!$img) return null;
    $style = $width !== '' ? ' style="width:' . (int)$width . 'px"' : '';
    $tag = '<img src="' . h($img['src']) . '" alt="' . h($alt) . '" loading="lazy"' . ($figure ? '' : $style) . '>';
    if (!$figure) return $tag;
    $mobile = $img['mobile'] ? '<source media="(max-width: 600px)" srcset="' . h($img['mobile']) . '">' : '';
    return '<figure class="doc-fig' . ($mobile ? ' doc-fig--mobile' : '') . ($width !== '' ? ' doc-fig--sized' : '') . '"' . $style . '>'
         . '<a href="' . h($img['src']) . '" target="_blank" rel="noopener"><picture>' . $mobile . $tag . '</picture></a>'
         . ($alt !== '' ? '<figcaption>' . doc_inline($alt) . '</figcaption>' : '') . '</figure>';
}

/** Inline Markdown: code spans, escapes, images, links, bold, italic, strike, bare URLs. Output is safe HTML. */
function doc_inline(string $s): string
{
    $keep = [];
    $hold = function (string $html) use (&$keep): string { $keep[] = $html; return "\x01" . (count($keep) - 1) . "\x02"; };
    // code spans with any number of backticks: `` `code` ``
    $s = preg_replace_callback('/(`+)(.+?)(?<!`)\1(?!`)/u', function ($m) use ($hold) {
        $c = $m[2];
        if (strlen($c) > 1 && $c[0] === ' ' && substr($c, -1) === ' ') $c = substr($c, 1, -1);
        return $hold('<code>' . h($c) . '</code>');
    }, $s);
    $s = preg_replace_callback('/\\\\([\\\\`*_{}\[\]()#+\-.!|>~])/u', fn($m) => $hold(h($m[1])), $s);
    $url = fn(string $u): ?string => preg_match('#^(https?://|mailto:|\#|/|\.{0,2}/?[\w\-./?=&%]+$)#i', $u) && !preg_match('/^\s*(javascript|data|vbscript):/i', $u) ? $u : null;
    $s = preg_replace_callback('/!\[([^\]]*)\]\(([^)\s]+)(?:\s+=(\d{1,4}))?\)/u', function ($m) use ($hold) {
        return $hold(doc_img_html($m[1], $m[2], $m[3] ?? '') ?? h($m[0]));
    }, $s);
    $s = preg_replace_callback('/\[([^\]]+)\]\(([^)\s]+)\)/u', function ($m) use ($hold, $url) {
        $u = $url($m[2]);
        if ($u === null) return $hold(h($m[1]));
        $ext = preg_match('#^https?://#i', $u) ? ' target="_blank" rel="noopener"' : '';
        return $hold('<a href="' . h($u) . '"' . $ext . '>' . doc_inline($m[1]) . '</a>');
    }, $s);
    $s = preg_replace_callback('/\bhttps?:\/\/[^\s<>"\x01]+[^\s<>"\x01.,;:!?)\]]/u',
        fn($m) => $hold('<a href="' . h($m[0]) . '" target="_blank" rel="noopener">' . h($m[0]) . '</a>'), $s);
    $s = h($s);
    $s = preg_replace('/\*\*(?=\S)(.+?)(?<=\S)\*\*/u', '<strong>$1</strong>', $s);
    $s = preg_replace('/(?<![\w*])\*(?=\S)(.+?)(?<=\S)\*(?![\w*])/u', '<em>$1</em>', $s);
    $s = preg_replace('/(?<![\w_])_(?=\S)(.+?)(?<=\S)_(?![\w_])/u', '<em>$1</em>', $s);
    $s = preg_replace('/~~(?=\S)(.+?)(?<=\S)~~/u', '<del>$1</del>', $s);
    for ($i = 0; $i < 3 && strpos($s, "\x01") !== false; $i++) {   // held parts may contain held parts
        $s = preg_replace_callback('/\x01(\d+)\x02/', fn($m) => $keep[(int)$m[1]], $s);
    }
    return $s;
}

/** Splits a table row on "|" outside code spans and escapes. */
function doc_table_cells(string $line): array
{
    $line = trim($line);
    if (str_starts_with($line, '|')) $line = substr($line, 1);
    if (str_ends_with($line, '|') && !str_ends_with($line, '\\|')) $line = substr($line, 0, -1);
    $cells = [];
    $cur = '';
    $tick = 0;
    $len = strlen($line);
    for ($i = 0; $i < $len; $i++) {
        $ch = $line[$i];
        if ($ch === '\\' && $i + 1 < $len) { $cur .= $ch . $line[++$i]; continue; }
        if ($ch === '`') {
            $run = strspn($line, '`', $i);
            $tick = $tick === 0 ? $run : ($tick === $run ? 0 : $tick);
            $cur .= str_repeat('`', $run);
            $i += $run - 1;
            continue;
        }
        if ($ch === '|' && $tick === 0) { $cells[] = trim($cur); $cur = ''; continue; }
        $cur .= $ch;
    }
    $cells[] = trim($cur);
    return $cells;
}

/**
 * Renders a manual. Supports headings (with ids), paragraphs, nested lists, tables, fenced code,
 * quotes, horizontal rules and the inline syntax of doc_inline(). $toc receives the "## " headings.
 */
function doc_render(string $md, ?array &$toc = null): string
{
    $toc = [];
    $ids = [];
    $lines = preg_split('/\R/u', str_replace("\t", '    ', doc_normalize($md)));
    $n = count($lines);
    $html = '';
    $para = [];
    $flush = function () use (&$para, &$html) {
        if (!$para) return;
        // a photo on its own line becomes a figure: the alt text is its caption, a click opens the full size
        // (phones get "name-mobile.png" when it exists next to "name.png")
        if (count($para) === 1 && preg_match('/^!\[([^\]]*)\]\(([^)\s]+)(?:\s+=(\d{1,4}))?\)$/u', $para[0], $m)
            && ($fig = doc_img_html($m[1], $m[2], $m[3] ?? '', true)) !== null) {
            $html .= $fig;
        } else {
            $html .= '<p>' . implode("<br>\n", array_map('doc_inline', $para)) . '</p>';
        }
        $para = [];
    };
    $isList = fn(string $l) => (bool)preg_match('/^(\s*)([-*+]|\d{1,3}[.)])\s+/', $l);

    for ($i = 0; $i < $n; $i++) {
        $line = $lines[$i];
        $t = trim($line);

        if (preg_match('/^\s*(```+|~~~+)/', $line, $fm)) {   // fenced code
            $flush();
            $code = [];
            for ($i++; $i < $n && !preg_match('/^\s*' . preg_quote($fm[1], '/') . '\s*$/', $lines[$i]); $i++) $code[] = $lines[$i];
            $html .= '<pre><code>' . h(implode("\n", $code)) . '</code></pre>';
            continue;
        }
        if ($t === '') { $flush(); continue; }
        // admonition (Docusaurus): :::note | tip | info | caution | warning | danger [Title] ... :::
        if (preg_match('/^:::\s*(note|tip|info|caution|warning|danger)\b\s*(.*)$/i', $t, $am)) {
            $flush();
            $inner = [];
            for ($i++; $i < $n && !preg_match('/^\s*:::\s*$/', $lines[$i]); $i++) $inner[] = $lines[$i];
            $kind = strtolower($am[1]);
            $title = trim($am[2]) !== '' ? doc_inline(trim($am[2])) : h(DOC_NOTES[$kind] ?? ucfirst($kind));
            $html .= '<aside class="doc-note doc-note--' . $kind . '"><p class="doc-note__h">' . $title . '</p>' . doc_render(implode("\n", $inner)) . '</aside>';
            continue;
        }
        if (preg_match('/^(#{1,6})\s+(.+?)\s*#*$/u', $t, $m)) {
            $flush();
            $lvl = strlen($m[1]);
            $raw = $m[2];
            $custom = null;
            if (preg_match('/^(.*?)\s*\{#([\w\-]+)\}$/u', $raw, $cm)) { $raw = $cm[1]; $custom = $cm[2]; }
            $text = trim(str_replace("\u{E000}", ' ', $raw));
            $id = $custom ?? doc_slug(str_replace("\u{E000}", '', trim($raw, " \u{E000}")));
            $base = $id;
            for ($k = 1; isset($ids[$id]); $k++) $id = $base . '-' . $k;
            $ids[$id] = true;
            if ($lvl === 2) $toc[] = ['id' => $id, 'text' => trim(strip_tags(doc_inline($text)))];
            $html .= "<h$lvl id=\"" . h($id) . "\">" . doc_inline($text) . ($lvl > 1 ? ' <a class="doc-anchor" href="#' . h($id) . '" aria-hidden="true" tabindex="-1">#</a>' : '') . "</h$lvl>";
            continue;
        }
        if (preg_match('/^(-{3,}|\*{3,}|_{3,})$/', $t)) { $flush(); $html .= '<hr>'; continue; }
        if (str_starts_with($t, '|') && $i + 1 < $n && preg_match('/^\|?\s*:?-{2,}:?\s*(\|\s*:?-{2,}:?\s*)*\|?$/', trim($lines[$i + 1]))) {
            $flush();
            $align = array_map(fn($c) => str_ends_with($c, ':') ? (str_starts_with($c, ':') ? 'center' : 'right') : '', doc_table_cells($lines[$i + 1]));
            $head = doc_table_cells($line);
            $html .= '<div class="doc-table"><table><thead><tr>';
            foreach ($head as $k => $c) $html .= '<th' . (!empty($align[$k]) ? ' style="text-align:' . $align[$k] . '"' : '') . '>' . doc_inline($c) . '</th>';
            $html .= '</tr></thead><tbody>';
            for ($i += 2; $i < $n && str_starts_with(trim($lines[$i]), '|'); $i++) {
                $html .= '<tr>';
                foreach (doc_table_cells($lines[$i]) as $k => $c) $html .= '<td' . (!empty($align[$k]) ? ' style="text-align:' . $align[$k] . '"' : '') . '>' . doc_inline($c) . '</td>';
                $html .= '</tr>';
            }
            $i--;
            $html .= '</tbody></table></div>';
            continue;
        }
        if (str_starts_with($t, '>')) {
            $flush();
            $q = [];
            for (; $i < $n && str_starts_with(trim($lines[$i]), '>'); $i++) $q[] = preg_replace('/^\s*>\s?/', '', $lines[$i]);
            $i--;
            $html .= '<blockquote>' . doc_render(implode("\n", $q)) . '</blockquote>';
            continue;
        }
        if ($isList($line)) {
            $flush();
            // collect the list block: items, indented continuations, blank lines followed by more of the list
            $block = [];
            for (; $i < $n; $i++) {
                $l = $lines[$i];
                if (trim($l) === '') {
                    $next = $i + 1 < $n ? $lines[$i + 1] : '';
                    if ($next !== '' && ($isList($next) || preg_match('/^\s{2,}\S/', $next))) { $block[] = ''; continue; }
                    break;
                }
                if (!$isList($l) && !preg_match('/^\s{2,}/', $l) && $block && trim(end($block)) === '') break;
                if (!$isList($l) && !preg_match('/^\s/', $l) && preg_match('/^(#|\||>|```)/', trim($l))) break;
                $block[] = $l;
            }
            $i--;
            $html .= doc_list($block);
            continue;
        }
        $para[] = $t;
    }
    $flush();
    return $html;
}

/** Renders a list block (lines of one list incl. nested lists and continuation lines). */
function doc_list(array $lines): string
{
    // items: [indent, ordered, number, text lines[], children lines[]]
    preg_match('/^(\s*)/', $lines[0], $m0);
    $base = strlen($m0[1]);
    $items = [];
    foreach ($lines as $l) {
        if (preg_match('/^(\s*)([-*+]|(\d{1,3})[.)])\s+(.*)$/', $l, $m) && strlen($m[1]) <= $base + 1) {
            $items[] = ['ordered' => $m[3] !== '', 'num' => (int)$m[3], 'text' => [$m[4]], 'sub' => []];
        } elseif ($items) {
            $last = &$items[count($items) - 1];
            if (trim($l) === '') { $last['sub'][] = ''; }
            elseif (preg_match('/^\s*([-*+]|\d{1,3}[.)])\s+/', $l) || $last['sub']) { $last['sub'][] = $l; }
            else { $last['text'][] = trim($l); }
            unset($last);
        }
    }
    if (!$items) return '';
    $ordered = $items[0]['ordered'];
    $html = $ordered ? '<ol' . ($items[0]['num'] > 1 ? ' start="' . $items[0]['num'] . '"' : '') . '>' : '<ul>';
    foreach ($items as $it) {
        $html .= '<li>' . implode("<br>\n", array_map('doc_inline', $it['text']));
        $sub = $it['sub'];
        while ($sub && trim(end($sub)) === '') array_pop($sub);
        if ($sub) {
            // de-indent and render the nested content (lists, paragraphs, code) as a mini document
            $min = PHP_INT_MAX;
            foreach ($sub as $s) if (trim($s) !== '') { preg_match('/^(\s*)/', $s, $mm); $min = min($min, strlen($mm[1])); }
            $html .= doc_render(implode("\n", array_map(fn($s) => substr($s, min($min, strlen($s) - strlen(ltrim($s)))), $sub)));
        }
        $html .= '</li>';
    }
    return $html . ($ordered ? '</ol>' : '</ul>');
}
