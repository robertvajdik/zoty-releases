<?php
declare(strict_types=1);
/**
 * Zoty Release Notes
 * @author Robert Vajdik
 */

date_default_timezone_set('Europe/Prague');

/**
 * Zoty Release Notes – shared helpers (storage, auth, rendering, Markdown export).
 * Storage: database via PDO (MySQL/MariaDB in production, SQLite for local dev).
 * Connection settings live in config.php (or the file given by env ZOTY_CONFIG).
 */

const APP_NAME = 'Zoty – přehled změn';

const TYPES = [
    'new'      => ['label' => 'Vylepšení'],
    'improved' => ['label' => 'Úprava'],
    'fixed'    => ['label' => 'Oprava'],
    'security' => ['label' => 'Zranitelnosti'],
];

if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params(['httponly' => true, 'samesite' => 'Lax']);
    session_start();
}

function h(?string $s): string
{
    return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/* ---------------------------------------------------------------- i18n (lang/*.properties) */

const LANGS = ['cs', 'en'];

/** Current UI language: ?lang=xx (remembered in a cookie) > cookie > 'cs'. Admin is always Czech. */
function lang(): string
{
    static $lang = null;
    if ($lang !== null) return $lang;
    if (strpos(str_replace(chr(92), '/', (string)($_SERVER['SCRIPT_NAME'] ?? '')), '/admin/') !== false) return $lang = 'cs';
    $q = strtolower((string)($_GET['lang'] ?? ''));
    if (in_array($q, LANGS, true)) {
        if (!headers_sent() && ($_COOKIE['zoty_lang'] ?? '') !== $q) {
            setcookie('zoty_lang', $q, ['expires' => time() + 365 * 86400, 'path' => '/', 'samesite' => 'Lax']);
        }
        return $lang = $q;
    }
    $c = (string)($_COOKIE['zoty_lang'] ?? '');
    return $lang = in_array($c, LANGS, true) ? $c : 'cs';
}

/** Parses a Java-style .properties file (UTF-8): key=value / key: value, # or ! comments, \ line continuation, \n \t \uXXXX escapes. */
function load_properties(string $file): array
{
    $out = [];
    if (!is_file($file)) return $out;
    $lines = preg_split('/\R/u', (string)file_get_contents($file));
    $buf = '';
    foreach ($lines as $line) {
        $line = $buf === '' ? ltrim($line) : $buf . ltrim($line);
        $buf = '';
        if ($line === '' || $line[0] === '#' || $line[0] === '!') continue;
        if (preg_match('/(\\\\+)$/', $line, $m) && strlen($m[1]) % 2 === 1) { $buf = substr($line, 0, -1); continue; }
        if (!preg_match('/^((?:\\\\.|[^=:\s\\\\])+)\s*[=:\s]\s*(.*)$/u', $line, $m)) { $out[$line] = ''; continue; }
        $unescape = fn(string $s): string => preg_replace_callback('/\\\\(u[0-9a-fA-F]{4}|.)/u', function ($e) {
            $c = $e[1];
            if ($c[0] === 'u' && strlen($c) === 5) return mb_chr(hexdec(substr($c, 1)), 'UTF-8');
            return ['n' => "\n", 't' => "\t", 'r' => "\r", 'f' => "\f"][$c] ?? $c;
        }, $s);
        $out[$unescape($m[1])] = $unescape($m[2]);
    }
    return $out;
}

/** Translated text for $key in the current language, with {placeholders} replaced. Falls back to Czech, then to the key. */
function tr(string $key, array $params = [], ?string $lang = null): string
{
    static $cache = [];
    $lang = $lang ?? lang();
    foreach ([$lang, 'cs'] as $l) {
        $cache[$l] ??= load_properties(__DIR__ . '/lang/' . $l . '.properties');
        if (isset($cache[$l][$key])) { $s = $cache[$l][$key]; break; }
    }
    $s ??= $key;
    foreach ($params as $k => $v) $s = str_replace('{' . $k . '}', (string)$v, $s);
    return $s;
}

/** Like tr(), HTML-escaped; $html params are inserted as ready-made HTML. */
function trh(string $key, array $params = [], array $html = []): string
{
    $s = h(tr($key, $params));
    foreach ($html as $k => $v) $s = str_replace(h('{' . $k . '}'), $v, $s);
    return $s;
}

function type_label(string $type): string
{
    return lang() === 'cs' ? (TYPES[$type]['label'] ?? $type) : tr('type.' . $type);
}

/** Current URL with ?lang=$code (other query parameters kept). */
function lang_url(string $code): string
{
    $q = $_GET;
    $q['lang'] = $code;
    $path = strtok((string)($_SERVER['REQUEST_URI'] ?? './'), '?') ?: './';
    return $path . '?' . http_build_query($q);
}

/** Adds lang=en to an absolute URL when English is active (Czech is the default without parameter). */
function with_lang(string $url, ?string $lang = null): string
{
    $lang = $lang ?? lang();
    if ($lang === 'cs') return $url;
    return $url . (strpos($url, '?') === false ? '?' : '&') . 'lang=' . $lang;
}

/* ---------------------------------------------------------------- database */

function config(): array
{
    static $cfg = null;
    if ($cfg === null) {
        $file = getenv('ZOTY_CONFIG') ?: __DIR__ . '/config.php';
        if (!is_file($file)) {
            http_response_code(500);
            exit('Chybí konfigurace databáze: zkopírujte config.sample.php jako config.php a vyplňte přístupy.');
        }
        $cfg = require $file;
    }
    return $cfg;
}

function db(): PDO
{
    static $pdo = null;
    if ($pdo) return $pdo;

    $c = config()['db'];
    $opts = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ];
    try {
        if ($c['driver'] === 'sqlite') {
            $pdo = new PDO('sqlite:' . $c['path'], null, null, $opts);
        } else {
            $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $c['host'], $c['port'] ?? 3306, $c['name']);
            $pdo = new PDO($dsn, $c['user'], $c['pass'], $opts);
        }
    } catch (PDOException $ex) {
        error_log('Zoty changelog DB: ' . $ex->getMessage());
        http_response_code(500);
        exit('Nepodařilo se připojit k databázi.');
    }
    if (!empty(config()['auto_migrate'])) {
        migrate($pdo, $c['driver']);
    }
    return $pdo;
}

function t(string $name): string
{
    return (config()['db']['prefix'] ?? '') . $name;
}

/** Creates tables if they do not exist (same structure as schema.sql). */
function migrate(PDO $pdo, string $driver): void
{
    $v = t('changelog_versions');
    $e = t('changelog_entries');
    $u = t('changelog_users');
    $s = t('changelog_subscribers');
    $m = t('changelog_mailings');
    $md = t('changelog_modules');
    try {
        $pdo->query("SELECT 1 FROM $md WHERE 1 = 0");
        $modulesExisted = true;
    } catch (PDOException $ex) {
        $modulesExisted = false;
    }
    if ($driver === 'sqlite') {
        $pdo->exec("CREATE TABLE IF NOT EXISTS $s (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            email TEXT NOT NULL UNIQUE,
            token TEXT NOT NULL UNIQUE,
            confirmed INTEGER NOT NULL DEFAULT 0,
            created_at TEXT NOT NULL,
            confirmed_at TEXT NULL,
            ip TEXT NOT NULL DEFAULT '')");
        $pdo->exec("CREATE TABLE IF NOT EXISTS $m (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            version_id INTEGER NOT NULL,
            recipients INTEGER NOT NULL DEFAULT 0,
            created_at TEXT NOT NULL)");
        $pdo->exec('PRAGMA foreign_keys = ON');
        $pdo->exec("CREATE TABLE IF NOT EXISTS $v (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            version TEXT NOT NULL UNIQUE,
            release_date TEXT NOT NULL,
            title TEXT NOT NULL DEFAULT '',
            summary TEXT NOT NULL DEFAULT '',
            published INTEGER NOT NULL DEFAULT 1,
            created_at TEXT NOT NULL,
            updated_at TEXT NOT NULL)");
        $pdo->exec("CREATE TABLE IF NOT EXISTS $e (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            version_id INTEGER NOT NULL REFERENCES $v(id) ON DELETE RESTRICT,
            type TEXT NOT NULL,
            module TEXT NOT NULL DEFAULT '',
            title TEXT NOT NULL,
            body TEXT NOT NULL DEFAULT '',
            published INTEGER NOT NULL DEFAULT 1,
            created_at TEXT NOT NULL,
            updated_at TEXT NOT NULL)");
        $pdo->exec("CREATE INDEX IF NOT EXISTS {$e}_version ON $e (version_id)");
        $pdo->exec("CREATE TABLE IF NOT EXISTS $u (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            username TEXT NOT NULL UNIQUE,
            password_hash TEXT NOT NULL,
            created_at TEXT NOT NULL)");
        $pdo->exec("CREATE TABLE IF NOT EXISTS $md (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL UNIQUE,
            created_at TEXT NOT NULL)");
    } else {
        $pdo->exec("CREATE TABLE IF NOT EXISTS `$v` (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            version VARCHAR(50) NOT NULL,
            release_date DATE NOT NULL,
            title VARCHAR(255) NOT NULL DEFAULT '',
            summary TEXT NOT NULL,
            published TINYINT(1) NOT NULL DEFAULT 1,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            UNIQUE KEY uq_version (version),
            KEY idx_release_date (release_date)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_czech_ci");
        $pdo->exec("CREATE TABLE IF NOT EXISTS `$e` (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            version_id INT UNSIGNED NOT NULL,
            type VARCHAR(20) NOT NULL,
            module VARCHAR(255) NOT NULL DEFAULT '',
            title VARCHAR(255) NOT NULL,
            body TEXT NOT NULL,
            published TINYINT(1) NOT NULL DEFAULT 1,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            KEY idx_version_id (version_id),
            KEY idx_published (published),
            CONSTRAINT fk_{$e}_version FOREIGN KEY (version_id) REFERENCES `$v` (id) ON DELETE RESTRICT
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_czech_ci");
        $pdo->exec("CREATE TABLE IF NOT EXISTS `$s` (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            email VARCHAR(190) NOT NULL,
            token CHAR(32) NOT NULL,
            confirmed TINYINT(1) NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL,
            confirmed_at DATETIME NULL,
            ip VARCHAR(45) NOT NULL DEFAULT '',
            UNIQUE KEY uq_email (email),
            UNIQUE KEY uq_token (token)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_czech_ci");
        $pdo->exec("CREATE TABLE IF NOT EXISTS `$m` (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            version_id INT UNSIGNED NOT NULL,
            recipients INT UNSIGNED NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL,
            KEY idx_version_id (version_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_czech_ci");
        $pdo->exec("CREATE TABLE IF NOT EXISTS `$u` (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            username VARCHAR(80) NOT NULL UNIQUE,
            password_hash VARCHAR(255) NOT NULL,
            created_at DATETIME NOT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_czech_ci");
        $pdo->exec("CREATE TABLE IF NOT EXISTS `$md` (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(80) NOT NULL,
            created_at DATETIME NOT NULL,
            UNIQUE KEY uq_name (name)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_czech_ci");
    }
    // Several modules per entry are stored as "A, B" – widen the column from the original VARCHAR(80).
    if ($driver !== 'sqlite') {
        $col = $pdo->query("SHOW COLUMNS FROM `$e` LIKE 'module'")->fetch();
        if ($col && stripos((string)$col['Type'], 'varchar(80)') !== false) {
            $pdo->exec("ALTER TABLE `$e` MODIFY module VARCHAR(255) NOT NULL DEFAULT ''");
        }
    }
    // First run: seed the default modules plus any module names already used by entries.
    if (!$modulesExisted) {
        $used = $pdo->query("SELECT DISTINCT module FROM $e WHERE module <> ''")->fetchAll(PDO::FETCH_COLUMN);
        $names = array_merge(DEFAULT_MODULES, ...array_map('split_modules', $used));
        $ins = $pdo->prepare("INSERT INTO $md (name, created_at) VALUES (?, ?)");
        $seen = [];
        foreach ($names as $n) {
            $n = trim((string)$n);
            $key = mb_strtolower($n);
            if ($n === '' || isset($seen[$key])) continue;
            $seen[$key] = true;
            $ins->execute([$n, date('Y-m-d H:i:s')]);
        }
    }
    migrate_tickets($pdo, $driver);
    migrate_docs($pdo, $driver);
    // Guard against a table left over from the earlier schema (version stored as text on entries).
    try {
        $pdo->query("SELECT version_id FROM $e WHERE 1 = 0");
    } catch (PDOException $ex) {
        http_response_code(500);
        exit("Tabulka $e má starou strukturu (bez version_id). Smažte ji (DROP TABLE $e) a obnovte stránku.");
    }
}

/* ---------------------------------------------------------------- versions */

const VERSION_COLS = 'v.id, v.version, v.release_date AS date, v.title, v.summary, v.published';

function normalize_version(array $r): array
{
    $r['id'] = (int)$r['id'];
    $r['date'] = substr((string)$r['date'], 0, 10);
    $r['published'] = (bool)$r['published'];
    $r['title'] = (string)$r['title'];
    $r['summary'] = (string)$r['summary'];
    if (isset($r['entry_count'])) $r['entry_count'] = (int)$r['entry_count'];
    return $r;
}

/** All versions, newest first, with their entry counts. */
function fetch_versions(bool $publishedOnly = false): array
{
    $sql = 'SELECT ' . VERSION_COLS . ', (SELECT COUNT(*) FROM ' . t('changelog_entries') . ' e WHERE e.version_id = v.id) AS entry_count'
         . ' FROM ' . t('changelog_versions') . ' v'
         . ($publishedOnly ? ' WHERE v.published = 1' : '')
         . ' ORDER BY v.release_date DESC, v.id DESC';
    return array_map('normalize_version', db()->query($sql)->fetchAll());
}

function get_version(int $id): ?array
{
    $st = db()->prepare('SELECT ' . VERSION_COLS . ' FROM ' . t('changelog_versions') . ' v WHERE v.id = ?');
    $st->execute([$id]);
    $row = $st->fetch();
    return $row ? normalize_version($row) : null;
}

function find_version_by_number(string $version): ?array
{
    $st = db()->prepare('SELECT ' . VERSION_COLS . ' FROM ' . t('changelog_versions') . ' v WHERE v.version = ?');
    $st->execute([$version]);
    $row = $st->fetch();
    return $row ? normalize_version($row) : null;
}

/** Validates version fields; returns a list of error messages. */
/** "1.42.07" / "V1.42.07" / " v1.42.07 " -> "v1.42.07" (versions are written as v1.4X.XX). */
function normalize_version_number(string $s): string
{
    $s = trim($s);
    if (preg_match('/^[vV]?\s*(\d+(?:\.\d+)*)$/', $s, $m)) return 'v' . $m[1];
    return $s;
}

function validate_version(array $v): array
{
    $errors = [];
    if ($v['version'] === '') $errors[] = 'Vyplňte číslo verze.';
    elseif (mb_strlen($v['version']) > 50) $errors[] = 'Číslo verze je příliš dlouhé (max. 50 znaků).';
    elseif (!preg_match('/^v\d+(\.\d+){1,3}$/', $v['version'])) $errors[] = 'Číslo verze zadejte ve formátu v1.42.07.';
    elseif (($dup = find_version_by_number($v['version'])) && $dup['id'] !== (int)($v['id'] ?? 0)) {
        $errors[] = 'Verze ' . $v['version'] . ' už existuje.';
    }
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $v['date']) || !strtotime($v['date'])) $errors[] = 'Neplatné datum vydání.';
    if (mb_strlen($v['title'] ?? '') > 255) $errors[] = 'Název verze je příliš dlouhý (max. 255 znaků).';
    return $errors;
}

/** Insert (no id) or update (with id). Returns the version id. */
function save_version(array $v): int
{
    $now = date('Y-m-d H:i:s');
    $vals = [$v['version'], $v['date'], $v['title'] ?? '', $v['summary'] ?? '', !empty($v['published']) ? 1 : 0];
    if (!empty($v['id'])) {
        db()->prepare('UPDATE ' . t('changelog_versions')
            . ' SET version = ?, release_date = ?, title = ?, summary = ?, published = ?, updated_at = ? WHERE id = ?')
            ->execute([...$vals, $now, (int)$v['id']]);
        return (int)$v['id'];
    }
    db()->prepare('INSERT INTO ' . t('changelog_versions')
        . ' (version, release_date, title, summary, published, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?)')
        ->execute([...$vals, $now, $now]);
    return (int)db()->lastInsertId();
}

/** Deletes a version only when it has no entries. */
function delete_version(int $id): bool
{
    $st = db()->prepare('SELECT COUNT(*) FROM ' . t('changelog_entries') . ' WHERE version_id = ?');
    $st->execute([$id]);
    if ((int)$st->fetchColumn() > 0) return false;
    db()->prepare('DELETE FROM ' . t('changelog_versions') . ' WHERE id = ?')->execute([$id]);
    return true;
}

function set_version_published(int $id, bool $published): void
{
    db()->prepare('UPDATE ' . t('changelog_versions') . ' SET published = ?, updated_at = ? WHERE id = ?')
        ->execute([$published ? 1 : 0, date('Y-m-d H:i:s'), $id]);
}

/* ---------------------------------------------------------------- entries */

const ENTRY_COLS = 'e.id, e.version_id, v.version, v.release_date AS date, v.title AS version_title, v.summary AS version_summary,'
                 . ' v.published AS version_published, e.type, e.module, e.title, e.body, e.published, e.updated_at';

/**
 * Entries matching filters (q, type, version, module, from, to), newest release first.
 * Public views ($includeDrafts = false) only show published entries of published versions.
 */
function fetch_entries(array $f = [], bool $includeDrafts = false): array
{
    $where = [];
    $args  = [];
    if (!$includeDrafts) $where[] = 'e.published = 1 AND v.published = 1';
    foreach (['type' => 'e.type', 'version' => 'v.version'] as $key => $col) {
        if (($f[$key] ?? '') !== '') { $where[] = "$col = ?"; $args[] = $f[$key]; }
    }
    if (($f['module'] ?? '') !== '') {
        $m = strtr($f['module'], ['!' => '!!', '%' => '!%', '_' => '!_']);
        $where[] = "(e.module = ? OR e.module LIKE ? ESCAPE '!' OR e.module LIKE ? ESCAPE '!' OR e.module LIKE ? ESCAPE '!')";
        array_push($args, $f['module'], $m . MODULE_SEP . '%', '%' . MODULE_SEP . $m, '%' . MODULE_SEP . $m . MODULE_SEP . '%');
    }
    if (($f['from'] ?? '') !== '') { $where[] = 'v.release_date >= ?'; $args[] = $f['from']; }
    if (($f['to'] ?? '') !== '')   { $where[] = 'v.release_date <= ?'; $args[] = $f['to']; }
    if (($f['q'] ?? '') !== '') {
        $like = '%' . strtr($f['q'], ['!' => '!!', '%' => '!%', '_' => '!_']) . '%';
        $where[] = "(e.title LIKE ? ESCAPE '!' OR e.body LIKE ? ESCAPE '!' OR e.module LIKE ? ESCAPE '!' OR v.version LIKE ? ESCAPE '!' OR v.title LIKE ? ESCAPE '!')";
        array_push($args, $like, $like, $like, $like, $like);
    }
    $sql = 'SELECT ' . ENTRY_COLS . ' FROM ' . t('changelog_entries') . ' e'
         . ' JOIN ' . t('changelog_versions') . ' v ON v.id = e.version_id'
         . ($where ? ' WHERE ' . implode(' AND ', $where) : '')
         . " ORDER BY v.release_date DESC, v.id DESC,"
         . " CASE e.type WHEN 'new' THEN 1 WHEN 'improved' THEN 2 WHEN 'fixed' THEN 3 ELSE 4 END, e.id";
    $st = db()->prepare($sql);
    $st->execute($args);
    return array_map('normalize_entry', $st->fetchAll());
}

function get_entry(int $id): ?array
{
    $st = db()->prepare('SELECT ' . ENTRY_COLS . ' FROM ' . t('changelog_entries') . ' e'
        . ' JOIN ' . t('changelog_versions') . ' v ON v.id = e.version_id WHERE e.id = ?');
    $st->execute([$id]);
    $row = $st->fetch();
    return $row ? normalize_entry($row) : null;
}

function normalize_entry(array $r): array
{
    $r['id'] = (int)$r['id'];
    $r['version_id'] = (int)$r['version_id'];
    $r['date'] = substr((string)$r['date'], 0, 10);
    $r['published'] = (bool)$r['published'];
    $r['version_published'] = (bool)$r['version_published'];
    foreach (['module', 'body', 'version_title', 'version_summary'] as $k) $r[$k] = (string)$r[$k];
    return $r;
}

/** Insert (no id) or update (with id). Returns the entry id. */
function save_entry(array $e): int
{
    $now = date('Y-m-d H:i:s');
    $vals = [(int)$e['version_id'], $e['type'], $e['module'], $e['title'], $e['body'], $e['published'] ? 1 : 0];
    if (!empty($e['id'])) {
        db()->prepare('UPDATE ' . t('changelog_entries')
            . ' SET version_id = ?, type = ?, module = ?, title = ?, body = ?, published = ?, updated_at = ? WHERE id = ?')
            ->execute([...$vals, $now, (int)$e['id']]);
        return (int)$e['id'];
    }
    db()->prepare('INSERT INTO ' . t('changelog_entries')
        . ' (version_id, type, module, title, body, published, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?)')
        ->execute([...$vals, $now, $now]);
    return (int)db()->lastInsertId();
}

function delete_entry(int $id): void
{
    db()->prepare('DELETE FROM ' . t('changelog_entries') . ' WHERE id = ?')->execute([$id]);
}

function set_published(int $id, bool $published): void
{
    db()->prepare('UPDATE ' . t('changelog_entries') . ' SET published = ?, updated_at = ? WHERE id = ?')
        ->execute([$published ? 1 : 0, date('Y-m-d H:i:s'), $id]);
}

function entry_stats(): array
{
    $r = db()->query('SELECT COUNT(*) AS total, COALESCE(SUM(published), 0) AS published FROM ' . t('changelog_entries'))->fetch();
    $r['versions'] = db()->query('SELECT COUNT(*) FROM ' . t('changelog_versions'))->fetchColumn();
    return array_map('intval', $r);
}

/** Version numbers (public: only published versions with published entries) or modules, for filter dropdowns. */
function distinct_values(string $field, bool $publishedOnly = true): array
{
    $pub = $publishedOnly ? ' AND e.published = 1 AND v.published = 1' : '';
    if ($field === 'version') {
        $sql = 'SELECT DISTINCT v.version FROM ' . t('changelog_versions') . ' v'
             . ($publishedOnly ? ' JOIN ' . t('changelog_entries') . ' e ON e.version_id = v.id WHERE 1 = 1' . $pub : '')
             . ' ORDER BY v.version';
        $vals = db()->query($sql)->fetchAll(PDO::FETCH_COLUMN);
        usort($vals, fn($a, $b) => version_compare((string)$b, (string)$a));
        return $vals;
    }
    if ($field === 'module') {
        $sql = 'SELECT DISTINCT e.module FROM ' . t('changelog_entries') . ' e JOIN ' . t('changelog_versions') . " v ON v.id = e.version_id WHERE e.module <> ''" . $pub;
        $vals = [];
        foreach (db()->query($sql)->fetchAll(PDO::FETCH_COLUMN) as $v) {
            foreach (split_modules($v) as $name) $vals[$name] = $name;
        }
        $vals = array_values($vals);
        sort($vals, SORT_NATURAL | SORT_FLAG_CASE);
        return $vals;
    }
    return [];
}

/* ----------------------------------------------------------------- modules */

const DEFAULT_MODULES = ['DataLink', 'Audit', 'Reporty'];
/** An entry can belong to several modules; they are stored in entries.module as "Audit, Reporty". */
const MODULE_SEP = ', ';

/** "Audit, Reporty" -> ['Audit', 'Reporty'] */
function split_modules(string $value): array
{
    $out = [];
    foreach (explode(',', $value) as $m) {
        $m = trim($m);
        if ($m !== '' && !in_array($m, $out, true)) $out[] = $m;
    }
    return $out;
}

function join_modules(array $names): string
{
    return implode(MODULE_SEP, split_modules(implode(',', $names)));
}

/** Number of entries per module name. */
function module_usage(): array
{
    $counts = [];
    foreach (db()->query('SELECT module FROM ' . t('changelog_entries') . " WHERE module <> ''")->fetchAll(PDO::FETCH_COLUMN) as $v) {
        foreach (split_modules($v) as $m) $counts[$m] = ($counts[$m] ?? 0) + 1;
    }
    return $counts;
}

/** Modules managed in admin, alphabetically, with the number of entries using each. */
function fetch_modules(): array
{
    try {
        $rows = db()->query('SELECT id, name FROM ' . t('changelog_modules'))->fetchAll();
    } catch (PDOException $ex) {
        return [];   // table missing (auto_migrate off and schema.sql not applied)
    }
    $usage = module_usage();
    foreach ($rows as &$r) {
        $r['id'] = (int)$r['id'];
        $r['entry_count'] = $usage[$r['name']] ?? 0;
    }
    unset($r);
    usort($rows, fn($a, $b) => strnatcasecmp($a['name'], $b['name']));
    return $rows;
}

function module_names(): array
{
    return array_column(fetch_modules(), 'name');
}

function get_module(int $id): ?array
{
    foreach (fetch_modules() as $m) if ($m['id'] === $id) return $m;
    return null;
}

function validate_module(string $name, int $id = 0): array
{
    if ($name === '') return ['Vyplňte název modulu.'];
    if (mb_strlen($name) > 80) return ['Název modulu je příliš dlouhý (max. 80 znaků).'];
    if (str_contains($name, ',')) return ['Název modulu nesmí obsahovat čárku.'];
    foreach (fetch_modules() as $m) {
        if ($m['id'] !== $id && mb_strtolower($m['name']) === mb_strtolower($name)) return ['Modul „' . $m['name'] . '“ už existuje.'];
    }
    return [];
}

/** Insert (no id) or rename (with id – the new name is also applied to its entries). */
function save_module(string $name, int $id = 0): void
{
    if ($id) {
        $old = get_module($id);
        db()->prepare('UPDATE ' . t('changelog_modules') . ' SET name = ? WHERE id = ?')->execute([$name, $id]);
        if ($old && $old['name'] !== $name) {
            $upd = db()->prepare('UPDATE ' . t('changelog_entries') . ' SET module = ? WHERE id = ?');
            foreach (db()->query('SELECT id, module FROM ' . t('changelog_entries') . " WHERE module <> ''")->fetchAll() as $row) {
                $list = split_modules($row['module']);
                if (!in_array($old['name'], $list, true)) continue;
                $list = array_map(fn($m) => $m === $old['name'] ? $name : $m, $list);
                $upd->execute([join_modules($list), (int)$row['id']]);
            }
        }
        return;
    }
    db()->prepare('INSERT INTO ' . t('changelog_modules') . ' (name, created_at) VALUES (?, ?)')->execute([$name, date('Y-m-d H:i:s')]);
}

/** Deletes a module only when no entry uses it. */
function delete_module(int $id): bool
{
    $m = get_module($id);
    if (!$m || $m['entry_count'] > 0) return false;
    db()->prepare('DELETE FROM ' . t('changelog_modules') . ' WHERE id = ?')->execute([$id]);
    return true;
}

/** Group sorted entries into releases (one group per version). */
function group_by_version(array $entries): array
{
    $groups = [];
    foreach ($entries as $e) {
        $k = $e['version_id'];
        if (!isset($groups[$k])) {
            $groups[$k] = [
                'version' => $e['version'], 'date' => $e['date'],
                'title' => $e['version_title'], 'summary' => $e['version_summary'],
                'published' => $e['version_published'], 'entries' => [],
            ];
        }
        $groups[$k]['entries'][] = $e;
    }
    return array_values($groups);
}

/* ---------------------------------------------------------------- formatting */

function fmt_date(string $ymd): string
{
    $t = strtotime($ymd);
    if (!$t) return h($ymd);
    $months = explode(',', tr('date.months'));
    return h(tr('date.format', ['day' => (int)date('j', $t), 'month' => trim($months[(int)date('n', $t) - 1] ?? date('n', $t)), 'year' => date('Y', $t)]));
}

/** Absolute URL of the app root with trailing slash, e.g. https://zoty.cz/release/.
 *  Set 'base_url' in config.php to override the auto-detected value. */
function site_url(string $path = ''): string
{
    $base = (string)(config()['base_url'] ?? '');
    if ($base === '') {
        $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
              || strtolower((string)($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https';
        $host  = preg_replace('/[^A-Za-z0-9.\-:\[\]]/', '', (string)($_SERVER['HTTP_HOST'] ?? 'localhost'));
        $dir   = str_replace(chr(92), '/', dirname((string)($_SERVER['SCRIPT_NAME'] ?? '/')));
        if (preg_match('#/admin$#', $dir)) $dir = substr($dir, 0, -6);   // called from admin/*.php
        $base  = ($https ? 'https' : 'http') . '://' . $host . rtrim($dir, '/') . '/';
    }
    return rtrim($base, '/') . '/' . ltrim($path, '/');
}

/** HTML id / URL fragment for a version, e.g. "v1.42.07". */
function version_anchor(string $version): string
{
    return 'v' . preg_replace('/[^0-9A-Za-z._-]/', '-', ltrim($version, 'vV'));
}

/** Plain text of a description (markdown markers stripped) – for structured data. */
function body_text(string $text): string
{
    $t = preg_replace('/!\[([^\]]*)\]\([^)]*\)/u', '$1', $text);
    $t = preg_replace('/\[([^\]]+)\]\([^)]*\)/u', '$1', $t);
    $t = preg_replace('/\*\*(.+?)\*\*|`(.+?)`/u', '$1$2', $t);
    $t = preg_replace('/^\s*[-*•]\s+/mu', '', $t);
    return trim(preg_replace('/\s+/u', ' ', $t));
}

/** Klíčová slova: 'keywords' from config.php + module names, without duplicates. */
function site_keywords(array $modules = []): array
{
    $cfg = config()['keywords'] ?? [];
    if (is_string($cfg)) $cfg = explode(',', $cfg);
    $out = [];
    foreach (array_merge($cfg, $modules) as $k) {
        $k = trim((string)$k);
        if ($k !== '' && !in_array(mb_strtolower($k), array_map('mb_strtolower', $out), true)) $out[] = $k;
    }
    return $out;
}

/** JSON-LD (schema.org) for the public changelog page. */
function changelog_jsonld(array $groups, string $pageUrl, array $keywords = []): string
{
    $home   = site_url();
    $org    = ['@type' => 'Organization', '@id' => 'https://zoty.cz/#organization', 'name' => 'Zoty',
               'url' => 'https://zoty.cz/', 'logo' => site_url('assets/img/zoty-logo.png')];
    $author = ['@type' => 'Person', '@id' => $home . '#author', 'name' => 'Robert Vajdik'];
    $app    = ['@type' => 'SoftwareApplication', '@id' => $home . '#app', 'name' => 'Zoty',
               'applicationCategory' => 'BusinessApplication', 'operatingSystem' => 'Web',
               'url' => 'https://zoty.cz/', 'releaseNotes' => $home, 'publisher' => ['@id' => $org['@id']]];
    if ($groups) $app['softwareVersion'] = $groups[0]['version'];

    $items = [];
    foreach (array_values($groups) as $i => $g) {
        $parts = [];
        foreach ($g['entries'] as $e) {
            $part = ['@type' => 'CreativeWork', 'name' => $e['title'],
                     'genre' => TYPES[$e['type']]['label'] ?? $e['type']];
            if ($e['module'] !== '') $part['keywords'] = $e['module'];
            if (($txt = body_text($e['body'])) !== '') $part['text'] = $txt;
            $parts[] = $part;
        }
        $release = ['@type' => 'TechArticle', '@id' => $home . '#' . version_anchor($g['version']),
                    'headline' => 'Zoty ' . $g['version'] . ($g['title'] !== '' ? ' – ' . $g['title'] : ''),
                    'url' => site_url('?version=' . rawurlencode($g['version'])),
                    'datePublished' => $g['date'], 'inLanguage' => 'cs',
                    'about' => ['@id' => $app['@id']], 'author' => ['@id' => $author['@id']],
                    'publisher' => ['@id' => $org['@id']], 'hasPart' => $parts];
        if (($sum = body_text($g['summary'])) !== '') $release['description'] = $sum;
        $items[] = ['@type' => 'ListItem', 'position' => $i + 1, 'item' => $release];
    }

    $page = ['@type' => 'CollectionPage', '@id' => $pageUrl . '#webpage', 'url' => $pageUrl,
             'name' => 'Zoty – přehled změn', 'description' => 'Přehled novinek, vylepšení a oprav v aplikaci Zoty.',
             'inLanguage' => 'cs', 'isPartOf' => ['@id' => $home . '#website'], 'about' => ['@id' => $app['@id']],
             'author' => ['@id' => $author['@id']], 'publisher' => ['@id' => $org['@id']],
             'mainEntity' => ['@type' => 'ItemList', 'numberOfItems' => count($items), 'itemListElement' => $items]];
    if ($keywords) $page['keywords'] = implode(', ', $keywords);

    $graph = [
        $org, $author, $app,
        ['@type' => 'WebSite', '@id' => $home . '#website', 'url' => $home, 'name' => 'Zoty – přehled změn',
         'inLanguage' => 'cs', 'publisher' => ['@id' => $org['@id']],
         'potentialAction' => ['@type' => 'SearchAction', 'target' => site_url('?q={search_term_string}'),
                               'query-input' => 'required name=search_term_string']],
        $page,
    ];
    $json = json_encode(['@context' => 'https://schema.org', '@graph' => $graph],
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT | JSON_HEX_TAG | JSON_HEX_AMP);
    return '<script type="application/ld+json">' . "\n" . $json . "\n</script>\n";
}

/** Tiny, safe markdown subset: paragraphs, "- " lists, **bold**, `code`, [text](url), ![alt](image). */
function render_body(string $text): string
{
    $inline = function (string $s): string {
        $s = h($s);
        $s = preg_replace('/\*\*(.+?)\*\*/u', '<strong>$1</strong>', $s);
        $s = preg_replace('/`(.+?)`/u', '<code>$1</code>', $s);
        // Bug numbers such as #20261101 (#YYYYMMNN) – bold; not inside URLs or HTML entities
        $s = preg_replace('/(?<![\w&\/#])#\d{8}(?!\d)/u', '<strong class="bug-ref">$0</strong>', $s);
        // Ticket keys of customers (NBU_ZOTY-12) – link to the request in the customer portal
        $s = link_ticket_refs($s);
        // ![popis](uploads/... nebo https://...) – image; only uploaded files or http(s) URLs
        $s = preg_replace_callback('/!\[([^\]]*)\]\((https?:\/\/[^\s)]+|uploads\/[\w\/.\-]+)\)/u', function ($m) {
            return '<img src="' . $m[2] . '" alt="' . $m[1] . '" loading="lazy" decoding="async">';
        }, $s);
        $s = preg_replace_callback('/\[(.+?)\]\((https?:\/\/[^\s)]+)\)/u', function ($m) {
            return '<a href="' . $m[2] . '" target="_blank" rel="noopener">' . $m[1] . '</a>';
        }, $s);
        return $s;
    };

    $html = '';
    $inList = false;
    $para = [];
    $flush = function () use (&$para, &$html, $inline) {
        if ($para) {
            $html .= '<p>' . $inline(implode(' ', $para)) . '</p>';
            $para = [];
        }
    };
    foreach (preg_split('/\R/u', $text) as $line) {
        $t = trim($line);
        if (preg_match('/^[-*•]\s+(.*)$/u', $t, $m)) {
            $flush();
            if (!$inList) { $html .= '<ul>'; $inList = true; }
            $html .= '<li>' . $inline($m[1]) . '</li>';
            continue;
        }
        if ($inList) { $html .= '</ul>'; $inList = false; }
        if ($t === '') { $flush(); continue; }
        $para[] = $t;
    }
    $flush();
    if ($inList) $html .= '</ul>';
    return $html;
}

/* ---------------------------------------------------------------- Markdown export */

function build_md(array $entries, array $filters = []): string
{
    $nl = "\n";
    $out  = '# ZOTY – Přehled změn' . $nl . $nl;
    $out .= '_Vygenerováno: ' . date('d.m.Y H:i') . '_' . $nl;
    $desc = [];
    if (!empty($filters['version'])) $desc[] = 'verze ' . $filters['version'];
    if (!empty($filters['type']) && isset(TYPES[$filters['type']])) $desc[] = 'typ ' . TYPES[$filters['type']]['label'];
    if (!empty($filters['module'])) $desc[] = 'modul ' . $filters['module'];
    if (!empty($filters['from'])) $desc[] = 'od ' . $filters['from'];
    if (!empty($filters['to'])) $desc[] = 'do ' . $filters['to'];
    if (!empty($filters['q'])) $desc[] = 'hledání „' . $filters['q'] . '“';
    if ($desc) $out .= $nl . '_Filtr: ' . md_escape(implode(', ', $desc)) . '_' . $nl;
    $out .= $nl;

    if (!$entries) {
        return $out . 'Žádné záznamy.' . $nl;
    }

    foreach (group_by_version($entries) as $g) {
        $out .= '## ' . md_escape($g['version']) . ' (' . date('d.m.Y', strtotime($g['date'])) . ')'
              . ($g['title'] !== '' ? ' – ' . md_escape($g['title']) : '')
              . ($g['published'] ? '' : ' _(nepublikováno)_') . $nl . $nl;
        if (trim($g['summary']) !== '') {
            $out .= md_body($g['summary']) . $nl . $nl;
        }
        foreach ($g['entries'] as $e) {
            $out .= '### ' . (TYPES[$e['type']]['label'] ?? $e['type']) . ': ' . md_escape($e['title'])
                  . ($e['module'] !== '' ? ' `' . str_replace('`', "'", $e['module']) . '`' : '')
                  . (empty($e['published']) ? ' _(koncept)_' : '') . $nl . $nl;
            if (trim($e['body']) !== '') $out .= md_body($e['body']) . $nl . $nl;
        }
    }
    return rtrim($out) . $nl;
}

/** Escape characters that would turn plain text (titles, filters) into markdown. */
function md_escape(string $s): string
{
    return preg_replace('/([\\\\`*_\[\]<>#|])/u', '\\\\$1', $s);
}

/** Entry/version description (already simple markdown): normalise bullets, make uploads/ URLs absolute. */
function md_body(string $text): string
{
    $lines = [];
    foreach (preg_split('/\R/u', trim($text)) as $line) {
        $line = rtrim($line);
        $line = preg_replace_callback('/(!?\[[^\]]*\]\()(uploads\/[\w\/.\-]+)\)/u', fn($m) => $m[1] . site_url($m[2]) . ')', $line);
        $lines[] = preg_replace('/^\s*[-*•]\s+/u', '- ', $line);
    }
    return implode("\n", $lines);
}

function send_md(array $entries, array $filters, string $suffix = ''): void
{
    $name = 'zoty-changelog' . (!empty($filters['version']) ? '-' . preg_replace('/[^0-9A-Za-z._-]/', '', $filters['version']) : '') . $suffix . '-' . date('Y-m-d') . '.md';
    header('Content-Type: text/markdown; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $name . '"');
    header('X-Content-Type-Options: nosniff');
    echo build_md($entries, $filters);
    exit;
}

/** Only the filter keys we know about, trimmed. */
function current_filters(array $src): array
{
    $f = [];
    foreach (['q', 'type', 'version', 'module', 'from', 'to'] as $k) {
        $v = trim((string)($src[$k] ?? ''));
        if ($v !== '') $f[$k] = $v;
    }
    return $f;
}

/* ---------------------------------------------------------------- auth (table changelog_users) */

function auth_configured(): bool
{
    return (int)db()->query('SELECT COUNT(*) FROM ' . t('changelog_users'))->fetchColumn() > 0;
}

/** Returns the username on success, null otherwise. */
function auth_verify(string $user, string $password): ?string
{
    $st = db()->prepare('SELECT username, password_hash FROM ' . t('changelog_users') . ' WHERE username = ?');
    $st->execute([$user]);
    $row = $st->fetch();
    // verify against a dummy hash when the user is unknown, so timing does not leak usernames
    $hash = $row['password_hash'] ?? password_hash('dummy-password', PASSWORD_DEFAULT);
    return (password_verify($password, $hash) && $row) ? $row['username'] : null;
}

function auth_create(string $user, string $password): void
{
    db()->prepare('INSERT INTO ' . t('changelog_users') . ' (username, password_hash, created_at) VALUES (?, ?, ?)')
        ->execute([$user, password_hash($password, PASSWORD_DEFAULT), date('Y-m-d H:i:s')]);
}

function is_admin(): bool
{
    return !empty($_SESSION['zoty_admin']);
}

function require_admin(): void
{
    if (!is_admin()) {
        header('Location: login');
        exit;
    }
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(16));
    }
    return $_SESSION['csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf" value="' . h(csrf_token()) . '">';
}

function csrf_check(): void
{
    if (!hash_equals(csrf_token(), (string)($_POST['csrf'] ?? ''))) {
        http_response_code(400);
        exit('Neplatný požadavek (CSRF).');
    }
}

function flash(?string $msg = null): ?string
{
    if ($msg !== null) {
        $_SESSION['flash'] = $msg;
        return null;
    }
    $m = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    return $m;
}

/* ---------------------------------------------------------------- subscribers (table changelog_subscribers) */

/** Adds (or re-sends confirmation to) a subscriber. Returns 'sent', 'exists' or 'error'. */
function subscriber_add(string $email): string
{
    $email = mb_strtolower(trim($email));
    $tbl = t('changelog_subscribers');
    $st = db()->prepare("SELECT * FROM $tbl WHERE email = ?");
    $st->execute([$email]);
    $row = $st->fetch();
    if ($row && $row['confirmed']) return 'exists';

    $now = date('Y-m-d H:i:s');
    if ($row) {
        // do not resend more often than every 10 minutes
        if (strtotime((string)$row['created_at']) > time() - 600) return 'sent';
        $token = (string)$row['token'];
        db()->prepare("UPDATE $tbl SET created_at = ? WHERE id = ?")->execute([$now, $row['id']]);
    } else {
        $token = bin2hex(random_bytes(16));
        db()->prepare("INSERT INTO $tbl (email, token, confirmed, created_at, ip) VALUES (?, ?, 0, ?, ?)")
            ->execute([$email, $token, $now, substr((string)($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45)]);
    }

    $link = with_lang(site_url('subscribe?confirm=' . $token));
    $text = tr('mail.confirm.hello') . "\r\n\r\n" . tr('mail.confirm.intro_text') . "\r\n\r\n"
          . $link . "\r\n\r\n" . tr('mail.confirm.ignore') . "\r\n\r\n" . tr('mail.confirm.signature');
    $html = mail_layout(tr('mail.confirm.title'),
        '<p>' . h(tr('mail.confirm.hello')) . '</p><p>' . h(tr('mail.confirm.intro')) . '</p>'
        . '<p style="margin:28px 0"><a href="' . h($link) . '" style="background:#6144a3;color:#fff;text-decoration:none;padding:12px 22px;border-radius:8px;font-weight:600;display:inline-block">' . h(tr('mail.confirm.button')) . '</a></p>'
        . '<p style="color:#656565;font-size:13px">' . h(tr('mail.confirm.ignore')) . '</p>');
    $res = mail_send([['to' => $email, 'subject' => tr('mail.confirm.subject'), 'text' => $text, 'html' => $html]]);
    if (!$res['sent']) {
        // allow an immediate retry; drop the row if it was just created
        if ($row) db()->prepare("UPDATE $tbl SET created_at = ? WHERE id = ?")->execute(['2000-01-01 00:00:00', $row['id']]);
        else db()->prepare("DELETE FROM $tbl WHERE email = ? AND confirmed = 0")->execute([$email]);
        return 'error';
    }
    return 'sent';
}

function subscriber_confirm(string $token): ?string
{
    if (!preg_match('/^[0-9a-f]{32}$/', $token)) return null;
    $tbl = t('changelog_subscribers');
    $st = db()->prepare("SELECT id, email FROM $tbl WHERE token = ?");
    $st->execute([$token]);
    if (!$row = $st->fetch()) return null;
    db()->prepare("UPDATE $tbl SET confirmed = 1, confirmed_at = COALESCE(confirmed_at, ?) WHERE id = ?")
        ->execute([date('Y-m-d H:i:s'), $row['id']]);
    return (string)$row['email'];
}

function subscriber_by_token(string $token): ?array
{
    if (!preg_match('/^[0-9a-f]{32}$/', $token)) return null;
    $st = db()->prepare('SELECT * FROM ' . t('changelog_subscribers') . ' WHERE token = ?');
    $st->execute([$token]);
    return $st->fetch() ?: null;
}

function subscriber_delete(int $id): void
{
    db()->prepare('DELETE FROM ' . t('changelog_subscribers') . ' WHERE id = ?')->execute([$id]);
}

function fetch_subscribers(bool $confirmedOnly = false): array
{
    return db()->query('SELECT * FROM ' . t('changelog_subscribers')
        . ($confirmedOnly ? ' WHERE confirmed = 1' : '') . ' ORDER BY created_at DESC, id DESC')->fetchAll();
}

/** Last mailing per version id: [version_id => ['created_at' => ..., 'recipients' => n]] */
function fetch_mailings(): array
{
    $out = [];
    foreach (db()->query('SELECT version_id, recipients, created_at FROM ' . t('changelog_mailings') . ' ORDER BY id') as $r) {
        $out[(int)$r['version_id']] = $r;
    }
    return $out;
}

/** E-mails one published version to all confirmed subscribers. Returns ['sent' => n, 'failed' => n, 'error' => ?string]. */
function notify_subscribers(array $version): array
{
    $entries = fetch_entries(['version' => $version['version']]);
    $subs = fetch_subscribers(true);
    if (!$subs) return ['sent' => 0, 'failed' => 0, 'error' => 'Zatím nemáte žádné potvrzené odběratele.'];
    if (!$entries) return ['sent' => 0, 'failed' => 0, 'error' => 'Verze nemá žádné publikované záznamy.'];

    @set_time_limit(0);
    $messages = [];
    foreach ($subs as $s) {
        $unsub = site_url('unsubscribe?t=' . $s['token']);
        [$subject, $text, $html] = version_email($version, $entries, $unsub);
        $messages[] = ['to' => $s['email'], 'subject' => $subject, 'text' => $text, 'html' => $html, 'unsubscribe' => $unsub];
    }
    $res = mail_send($messages);
    if ($res['sent']) {
        db()->prepare('INSERT INTO ' . t('changelog_mailings') . ' (version_id, recipients, created_at) VALUES (?, ?, ?)')
            ->execute([(int)$version['id'], $res['sent'], date('Y-m-d H:i:s')]);
    }
    return $res;
}

/** [subject, text, html] for a version announcement. */
function version_email(array $v, array $entries, string $unsubUrl): array
{
    $url = site_url('?version=' . rawurlencode($v['version']));
    $subject = 'Zoty ' . $v['version'] . ($v['title'] !== '' ? ' – ' . $v['title'] : '') . ': co je nového';

    $text = 'Zoty ' . $v['version'] . ' (' . date('d.m.Y', strtotime($v['date'])) . ')'
          . ($v['title'] !== '' ? "\r\n" . $v['title'] : '') . "\r\n\r\n";
    if (($sum = body_text($v['summary'])) !== '') $text .= $sum . "\r\n\r\n";

    $html = '<p style="margin:0 0 4px;color:#656565;font-size:13px">' . h(fmt_date($v['date'])) . ' · Verze ' . h($v['version']) . '</p>';
    if ($v['title'] !== '') $html .= '<h2 style="margin:0 0 12px;font-size:20px">' . h($v['title']) . '</h2>';
    if (trim($v['summary']) !== '') $html .= '<div style="color:#444">' . absolute_urls(render_body($v['summary'])) . '</div>';

    $colors = ['new' => '#2f7a27', 'improved' => '#6144a3', 'fixed' => '#8a5d05', 'security' => '#a3263a'];
    foreach ($entries as $e) {
        $label = TYPES[$e['type']]['label'] ?? $e['type'];
        $text .= '[' . $label . '] ' . $e['title'] . ($e['module'] !== '' ? ' (' . $e['module'] . ')' : '') . "\r\n";
        if (($b = body_text($e['body'])) !== '') $text .= '    ' . $b . "\r\n";
        $html .= '<div style="border:1px solid #ecebec;border-radius:10px;padding:14px 16px;margin:12px 0">'
               . '<span style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:' . ($colors[$e['type']] ?? '#333') . '">' . h($label) . '</span>'
               . ($e['module'] !== '' ? '<span style="font-size:11px;color:#656565"> · ' . h($e['module']) . '</span>' : '')
               . '<div style="font-weight:600;font-size:16px;margin-top:4px">' . h($e['title']) . '</div>'
               . (trim($e['body']) !== '' ? '<div style="color:#555;font-size:14px">' . absolute_urls(render_body($e['body'])) . '</div>' : '')
               . '</div>';
    }
    $text .= "\r\nCelý přehled: " . $url . "\r\n\r\n--\r\nOdhlášení z odběru: " . $unsubUrl . "\r\n";
    $html .= '<p style="margin:24px 0 0"><a href="' . h($url) . '" style="background:#6144a3;color:#fff;text-decoration:none;padding:12px 22px;border-radius:8px;font-weight:600;display:inline-block">Zobrazit na webu</a></p>';
    return [$subject, $text, mail_layout('Zoty ' . $v['version'], $html, $unsubUrl)];
}

/** Turns src="uploads/…" / href="uploads/…" into absolute URLs (for e-mail and feeds). */
function absolute_urls(string $html): string
{
    $html = preg_replace('/(src|href)="(uploads\/[^"]*)"/', '$1="' . site_url('') . '$2"', $html);
    return str_replace('<img ', '<img style="max-width:100%;height:auto;border-radius:8px" ', $html);
}

function mail_layout(string $title, string $content, string $unsubUrl = ''): string
{
    return '<!doctype html><html lang="' . lang() . '"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>' . h($title) . '</title></head>'
        . '<body style="margin:0;background:#f9f9fc;font-family:Roboto,Arial,Helvetica,sans-serif;color:#333;line-height:1.55">'
        . '<div style="max-width:600px;margin:0 auto;padding:24px 16px">'
        . '<div style="padding:4px 0 16px"><img src="' . h(site_url('assets/img/zoty-logo.png')) . '" width="110" alt="Zoty" style="display:block;border:0"></div>'
        . '<div style="background:#fff;border-radius:12px;padding:24px;border:1px solid #ecebec">' . $content . '</div>'
        . '<p style="color:#888;font-size:12px;text-align:center;margin:18px 0 0">' . h(tr('site.name')) . ' · <a href="' . h(site_url()) . '" style="color:#888">' . h(parse_url(site_url(), PHP_URL_HOST) ?: '') . '</a>'
        . ($unsubUrl !== '' ? '<br>' . h(tr('mail.footer.reason')) . ' <a href="' . h($unsubUrl) . '" style="color:#888">' . h(tr('mail.footer.unsubscribe')) . '</a>' : '')
        . '</p></div></body></html>';
}

/* ---------------------------------------------------------------- mail (SMTP or PHP mail()) */

/**
 * Sends messages [['to','subject','text','html','unsubscribe'?], ...] over one SMTP connection.
 * Returns ['sent' => n, 'failed' => n, 'error' => ?string].
 */
function mail_send(array $messages): array
{
    $c = (config()['mail'] ?? []) + ['transport' => 'mail', 'from' => '', 'from_name' => 'Zoty'];
    $from = (string)$c['from'];
    if (!filter_var($from, FILTER_VALIDATE_EMAIL)) {
        return ['sent' => 0, 'failed' => count($messages), 'error' => 'V config.php chybí platná adresa mail.from.'];
    }
    $sent = 0; $failed = 0; $error = null;

    if ($c['transport'] !== 'smtp') {
        return mail_send_php($messages, $c);
    }

    try {
        $smtp = smtp_open($c);
        foreach ($messages as $m) {
            try {
                [$headers, $body] = mail_build($m, $c);
                smtp_cmd($smtp, 'MAIL FROM:<' . $from . '>', [250]);
                smtp_cmd($smtp, 'RCPT TO:<' . $m['to'] . '>', [250, 251]);
                smtp_cmd($smtp, 'DATA', [354]);
                $data = preg_replace('/^\./m', '..', $headers . "\r\n" . $body);
                smtp_cmd($smtp, $data . "\r\n.", [250]);
                $sent++;
            } catch (RuntimeException $ex) {
                $failed++;
                $error = $ex->getMessage();
                error_log('Zoty mail to ' . $m['to'] . ': ' . $error);
                try { smtp_cmd($smtp, 'RSET', [250]); } catch (RuntimeException $ignored) {}
            }
        }
        try { smtp_cmd($smtp, 'QUIT', [221]); } catch (RuntimeException $ignored) {}
        fclose($smtp);
    } catch (RuntimeException $ex) {
        mail_log('SMTP ' . $c['host'] . ':' . ($c['port'] ?? '') . ' – ' . $ex->getMessage());
        if ($sent === 0 && !empty($c['fallback_mail'])) {
            // connection / login failed before anything was sent: try the hosting's mail()
            $res = mail_send_php($messages, $c);
            if ($res['sent']) return $res;
            $res['error'] = 'SMTP: ' . $ex->getMessage() . ' | mail(): ' . $res['error'];
            mail_log($res['error']);
            return $res;
        }
        return ['sent' => $sent, 'failed' => count($messages) - $sent, 'error' => $ex->getMessage()];
    }
    if ($error) mail_log('SMTP: ' . $error);
    return ['sent' => $sent, 'failed' => $failed, 'error' => $error];
}

function mail_send_php(array $messages, array $c): array
{
    $sent = 0; $failed = 0;
    foreach ($messages as $m) {
        [$headers, $body] = mail_build($m, $c);
        $hdr = preg_replace('/^(To|Subject):.*\r\n/mi', '', $headers);
        $ok = @mail($m['to'], mail_header_encode($m['subject']), $body, rtrim($hdr), '-f' . $c['from']);
        $ok ? $sent++ : $failed++;
    }
    $err = $failed ? 'funkce mail() zprávu neodeslala' : null;
    if ($err) mail_log('mail(): ' . $err);
    return ['sent' => $sent, 'failed' => $failed, 'error' => $err];
}

/** Last mail errors go to data/mail.log (web access to data/ is denied) and the PHP error log. */
function mail_log(string $msg): void
{
    error_log('Zoty mail: ' . $msg);
    $file = __DIR__ . '/data/mail.log';
    $lines = is_file($file) ? array_slice(file($file, FILE_IGNORE_NEW_LINES) ?: [], -49) : [];
    $lines[] = date('Y-m-d H:i:s') . '  ' . preg_replace('/\s+/', ' ', $msg);
    @file_put_contents($file, implode("\n", $lines) . "\n", LOCK_EX);
}

function mail_log_tail(int $n = 10): array
{
    $file = __DIR__ . '/data/mail.log';
    return is_file($file) ? array_reverse(array_slice(file($file, FILE_IGNORE_NEW_LINES) ?: [], -$n)) : [];
}

/** @return resource */
function smtp_open(array $c)
{
    $secure = strtolower((string)($c['secure'] ?? 'ssl'));       // ssl (465) | tls (587, STARTTLS) | none
    $port = (int)($c['port'] ?? ($secure === 'ssl' ? 465 : 587));
    $host = (string)$c['host'];
    // force IPv4: the host also has an AAAA record and the server may lack an IPv6 route ("No route to host").
    // gethostbyname() returns only an A record (or the input unchanged when it can't resolve).
    $ip = filter_var($host, FILTER_VALIDATE_IP) ? $host : gethostbyname($host);
    $scheme = $secure === 'ssl' ? 'ssl://' : 'tcp://';
    $remote = $scheme . $ip . ':' . $port;
    $smtp = @stream_socket_client($remote, $errno, $errstr, 15, STREAM_CLIENT_CONNECT,
        stream_context_create(['ssl' => [
            'verify_peer' => true, 'verify_peer_name' => true,
            'peer_name' => $host, 'SNI_enabled' => true,          // certificate is still checked against the hostname
        ]]));
    if (!$smtp) {
        $via = $ip !== $host ? " ($ip)" : '';
        throw new RuntimeException("Nelze se připojit k SMTP $scheme$host:$port$via: $errstr");
    }
    stream_set_timeout($smtp, 30);
    smtp_read($smtp, [220]);
    $helo = preg_replace('/[^A-Za-z0-9.\-]/', '', (string)($_SERVER['SERVER_NAME'] ?? 'localhost')) ?: 'localhost';
    smtp_cmd($smtp, 'EHLO ' . $helo, [250]);
    if ($secure === 'tls') {
        smtp_cmd($smtp, 'STARTTLS', [220]);
        if (!stream_socket_enable_crypto($smtp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
            throw new RuntimeException('STARTTLS se nezdařil.');
        }
        smtp_cmd($smtp, 'EHLO ' . $helo, [250]);
    }
    if (($c['user'] ?? '') !== '') {
        smtp_cmd($smtp, 'AUTH LOGIN', [334]);
        smtp_cmd($smtp, base64_encode((string)$c['user']), [334]);
        smtp_cmd($smtp, base64_encode((string)$c['pass']), [235], 'AUTH (heslo skryto)');
    }
    return $smtp;
}

function smtp_cmd($smtp, string $cmd, array $expect, ?string $label = null): string
{
    fwrite($smtp, $cmd . "\r\n");
    return smtp_read($smtp, $expect, $label ?? (strlen($cmd) > 60 ? substr($cmd, 0, 20) . '…' : $cmd));
}

function smtp_read($smtp, array $expect, string $label = 'connect'): string
{
    $resp = '';
    while (($line = fgets($smtp, 1024)) !== false) {
        $resp .= $line;
        if (strlen($line) < 4 || $line[3] === ' ') break;
    }
    $code = (int)substr($resp, 0, 3);
    if (!in_array($code, $expect, true)) {
        throw new RuntimeException('SMTP ' . $label . ': ' . trim($resp ?: 'bez odpovědi'));
    }
    return $resp;
}

function mail_header_encode(string $s): string
{
    $s = trim(preg_replace('/[\r\n]+/', ' ', $s));
    return preg_match('/[^\x20-\x7E]/', $s) ? '=?UTF-8?B?' . base64_encode($s) . '?=' : $s;
}

/** @return array{0:string,1:string} [headers ending in CRLF, body] */
function mail_build(array $m, array $c): array
{
    $from = (string)$c['from'];
    $domain = substr(strrchr($from, '@'), 1) ?: 'localhost';
    $b = 'zoty-' . bin2hex(random_bytes(8));
    $h = [
        'Date: ' . date('r'),
        'From: ' . mail_header_encode((string)$c['from_name']) . ' <' . $from . '>',
        'To: <' . $m['to'] . '>',
        'Subject: ' . mail_header_encode((string)$m['subject']),
        'Message-ID: <' . bin2hex(random_bytes(12)) . '@' . $domain . '>',
        'MIME-Version: 1.0',
        'Content-Type: multipart/alternative; boundary="' . $b . '"',
    ];
    if (!empty($c['reply_to']) && filter_var($c['reply_to'], FILTER_VALIDATE_EMAIL)) $h[] = 'Reply-To: <' . $c['reply_to'] . '>';
    if (!empty($m['unsubscribe'])) {
        $h[] = 'List-Unsubscribe: <' . $m['unsubscribe'] . '>';
        $h[] = 'List-Unsubscribe-Post: List-Unsubscribe=One-Click';
    }
    $body = '--' . $b . "\r\nContent-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n"
          . chunk_split(base64_encode((string)$m['text']))
          . '--' . $b . "\r\nContent-Type: text/html; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n"
          . chunk_split(base64_encode((string)$m['html']))
          . '--' . $b . "--\r\n";
    return [implode("\r\n", $h) . "\r\n", $body];
}

/* ---------------------------------------------------------------- Atom feed */

function atom_feed(int $limit = 30): string
{
    $groups = array_slice(group_by_version(fetch_entries()), 0, $limit);
    $x = fn(string $s): string => htmlspecialchars($s, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    $home = site_url();
    $updated = null;
    foreach ($groups as $g) foreach ($g['entries'] as $e) {
        $u = $e['updated_at'] ?? $g['date'];
        if ($updated === null || $u > $updated) $updated = $u;
    }
    $out = '<?xml version="1.0" encoding="utf-8"?>' . "\n"
         . '<feed xmlns="http://www.w3.org/2005/Atom" xml:lang="cs">' . "\n"
         . '  <title>Zoty – přehled změn</title>' . "\n"
         . '  <subtitle>Přehled novinek, vylepšení a oprav v aplikaci Zoty.</subtitle>' . "\n"
         . '  <link rel="alternate" type="text/html" href="' . $x($home) . '"/>' . "\n"
         . '  <link rel="self" type="application/atom+xml" href="' . $x(site_url('feed.xml')) . '"/>' . "\n"
         . '  <id>' . $x($home) . '</id>' . "\n"
         . '  <updated>' . date(DATE_ATOM, $updated ? strtotime($updated) : time()) . '</updated>' . "\n"
         . '  <author><name>Robert Vajdik</name></author>' . "\n"
         . '  <icon>' . $x(site_url('assets/img/favicon-32.png')) . '</icon>' . "\n";
    foreach ($groups as $g) {
        $html = trim($g['summary']) !== '' ? render_body($g['summary']) : '';
        $u = $g['date'];
        foreach ($g['entries'] as $e) {
            if (($e['updated_at'] ?? '') > $u) $u = $e['updated_at'];
            $html .= '<h3>[' . h(TYPES[$e['type']]['label'] ?? $e['type']) . '] ' . h($e['title'])
                   . ($e['module'] !== '' ? ' <small>(' . h($e['module']) . ')</small>' : '') . '</h3>' . render_body($e['body']);
        }
        $url = site_url('?version=' . rawurlencode($g['version']));
        $out .= "  <entry>\n"
              . '    <title>' . $x('Zoty ' . $g['version'] . ($g['title'] !== '' ? ' – ' . $g['title'] : '')) . "</title>\n"
              . '    <link rel="alternate" type="text/html" href="' . $x($url) . "\"/>\n"
              . '    <id>' . $x($home . '#' . version_anchor($g['version'])) . "</id>\n"
              . '    <published>' . date(DATE_ATOM, strtotime($g['date'])) . "</published>\n"
              . '    <updated>' . date(DATE_ATOM, strtotime($u)) . "</updated>\n"
              . '    <content type="html">' . $x(absolute_urls($html)) . "</content>\n"
              . "  </entry>\n";
    }
    return $out . "</feed>\n";
}

require __DIR__ . '/lib-tickets.php';
require __DIR__ . '/lib-docs.php';
