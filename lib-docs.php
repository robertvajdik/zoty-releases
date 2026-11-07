<?php
declare(strict_types=1);
/**
 * Zoty – manuals (help pages) editable in the admin.
 * Table changelog_docs: one Markdown document per (slug, lang). A missing document is created
 * from its seed file the first time it is requested (MANUAL.md, docs/*.md).
 */

const DOCS = [
    'admin'  => ['label' => 'Příručka administrace', 'langs' => ['en', 'cs'], 'seed' => ['en' => 'MANUAL.md']],
    'portal' => ['label' => 'Nápověda zákaznického portálu', 'langs' => ['cs', 'en'], 'seed' => ['cs' => 'docs/portal.cs.md', 'en' => 'docs/portal.en.md']],
];

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
        return;
    }
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

function doc_save(string $slug, string $lang, string $body, string $by): void
{
    $tbl = t('changelog_docs');
    $st = db()->prepare("UPDATE $tbl SET body = ?, updated_at = ?, updated_by = ? WHERE slug = ? AND lang = ?");
    $st->execute([$body, now(), $by, $slug, $lang]);
    if ($st->rowCount() === 0) {
        try {
            db()->prepare("INSERT INTO $tbl (slug, lang, body, updated_at, updated_by) VALUES (?, ?, ?, ?, ?)")
                ->execute([$slug, $lang, $body, now(), $by]);
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
    $s = preg_replace_callback('/!\[([^\]]*)\]\(([^)\s]+)\)/u', function ($m) use ($hold) {
        $src = $m[2];
        if (!preg_match('#^(https?://|uploads/)#', $src)) return $hold(h($m[0]));
        $src = str_starts_with($src, 'uploads/') ? site_url($src) : $src;
        return $hold('<img src="' . h($src) . '" alt="' . h($m[1]) . '" loading="lazy">');
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
    $lines = preg_split('/\R/u', str_replace("\t", '    ', $md));
    $n = count($lines);
    $html = '';
    $para = [];
    $flush = function () use (&$para, &$html) {
        if ($para) { $html .= '<p>' . implode("<br>\n", array_map('doc_inline', $para)) . '</p>'; $para = []; }
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
        if (preg_match('/^(#{1,4})\s+(.+?)\s*#*$/u', $t, $m)) {
            $flush();
            $lvl = strlen($m[1]);
            $id = doc_slug($m[2]);
            $base = $id;
            for ($k = 1; isset($ids[$id]); $k++) $id = $base . '-' . $k;
            $ids[$id] = true;
            if ($lvl === 2) $toc[] = ['id' => $id, 'text' => trim(strip_tags(doc_inline($m[2])))];
            $html .= "<h$lvl id=\"" . h($id) . "\">" . doc_inline($m[2]) . ($lvl > 1 ? ' <a class="doc-anchor" href="#' . h($id) . '" aria-hidden="true" tabindex="-1">#</a>' : '') . "</h$lvl>";
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
