<?php
declare(strict_types=1);
/**
 * Zoty Release Notes – support tickets (customer portal + admin queue).
 * Loaded from lib.php. Tables: changelog_customers, changelog_customer_users,
 * changelog_tickets, changelog_ticket_comments, changelog_ticket_files.
 * Attachments live in data/tickets/<ticket id>/ (not web-accessible) and are served by portal/file.php.
 */

const TICKET_TYPES      = ['incident', 'question', 'feature'];
const TICKET_SEVERITIES = ['crisis', 'high', 'medium', 'low'];
const TICKET_STATUSES   = ['open', 'in_progress', 'waiting', 'resolved', 'closed'];
const TICKET_DONE       = ['resolved', 'closed'];
const TICKET_DEFAULT_OS = ['Windows 11', 'Windows 10', 'Windows Server 2022', 'Windows Server 2019', 'Linux', 'macOS'];
const TICKET_FILE_MAX   = 10 * 1024 * 1024;   // bytes per file
const TICKET_FILE_COUNT = 10;                  // files per upload
const TICKET_FILE_EXT   = ['png', 'jpg', 'jpeg', 'gif', 'webp', 'pdf', 'txt', 'log', 'csv', 'json', 'xml',
                           'zip', '7z', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'odt', 'ods', 'eml', 'msg'];

function now(): string
{
    return date('Y-m-d H:i:s');
}

function support_config(): array
{
    return (config()['support'] ?? []) + ['notify' => [], 'os' => TICKET_DEFAULT_OS];
}

/* ---------------------------------------------------------------- schema */

function migrate_tickets(PDO $pdo, string $driver): void
{
    $c  = t('changelog_customers');
    $cu = t('changelog_customer_users');
    $tk = t('changelog_tickets');
    $cm = t('changelog_ticket_comments');
    $fl = t('changelog_ticket_files');
    if ($driver === 'sqlite') {
        $pdo->exec("CREATE TABLE IF NOT EXISTS $c (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL,
            code TEXT NOT NULL UNIQUE,
            created_at TEXT NOT NULL)");
        $pdo->exec("CREATE TABLE IF NOT EXISTS $cu (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            customer_id INTEGER NOT NULL REFERENCES $c(id) ON DELETE CASCADE,
            email TEXT NOT NULL UNIQUE,
            name TEXT NOT NULL DEFAULT '',
            password_hash TEXT NOT NULL,
            active INTEGER NOT NULL DEFAULT 1,
            lang TEXT NOT NULL DEFAULT 'cs',
            created_at TEXT NOT NULL,
            last_login TEXT NULL)");
        $pdo->exec("CREATE TABLE IF NOT EXISTS $tk (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            customer_id INTEGER NOT NULL REFERENCES $c(id) ON DELETE RESTRICT,
            number INTEGER NOT NULL,
            user_id INTEGER NULL,
            type TEXT NOT NULL,
            product TEXT NOT NULL DEFAULT '',
            product_version TEXT NOT NULL DEFAULT '',
            os TEXT NOT NULL DEFAULT '',
            summary TEXT NOT NULL,
            description TEXT NOT NULL DEFAULT '',
            severity TEXT NOT NULL DEFAULT 'medium',
            status TEXT NOT NULL DEFAULT 'open',
            created_at TEXT NOT NULL,
            updated_at TEXT NOT NULL,
            UNIQUE (customer_id, number))");
        $pdo->exec("CREATE TABLE IF NOT EXISTS $cm (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            ticket_id INTEGER NOT NULL REFERENCES $tk(id) ON DELETE CASCADE,
            user_id INTEGER NULL,
            staff_name TEXT NOT NULL DEFAULT '',
            body TEXT NOT NULL DEFAULT '',
            event TEXT NOT NULL DEFAULT '',
            created_at TEXT NOT NULL)");
        $pdo->exec("CREATE INDEX IF NOT EXISTS {$cm}_ticket ON $cm (ticket_id)");
        $pdo->exec("CREATE TABLE IF NOT EXISTS $fl (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            ticket_id INTEGER NOT NULL REFERENCES $tk(id) ON DELETE CASCADE,
            comment_id INTEGER NULL,
            name TEXT NOT NULL,
            path TEXT NOT NULL,
            mime TEXT NOT NULL DEFAULT '',
            size INTEGER NOT NULL DEFAULT 0,
            created_at TEXT NOT NULL)");
        $pdo->exec("CREATE INDEX IF NOT EXISTS {$fl}_ticket ON $fl (ticket_id)");
        return;
    }
    $opts = 'ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_czech_ci';
    $pdo->exec("CREATE TABLE IF NOT EXISTS `$c` (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(160) NOT NULL,
        code VARCHAR(10) NOT NULL,
        created_at DATETIME NOT NULL,
        UNIQUE KEY uq_code (code)
    ) $opts");
    $pdo->exec("CREATE TABLE IF NOT EXISTS `$cu` (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
        customer_id INT UNSIGNED NOT NULL,
        email VARCHAR(190) NOT NULL,
        name VARCHAR(160) NOT NULL DEFAULT '',
        password_hash VARCHAR(255) NOT NULL,
        active TINYINT(1) NOT NULL DEFAULT 1,
        lang VARCHAR(5) NOT NULL DEFAULT 'cs',
        created_at DATETIME NOT NULL,
        last_login DATETIME NULL,
        UNIQUE KEY uq_email (email),
        KEY idx_customer (customer_id),
        CONSTRAINT fk_{$cu}_customer FOREIGN KEY (customer_id) REFERENCES `$c` (id) ON DELETE CASCADE
    ) $opts");
    $pdo->exec("CREATE TABLE IF NOT EXISTS `$tk` (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
        customer_id INT UNSIGNED NOT NULL,
        number INT UNSIGNED NOT NULL,
        user_id INT UNSIGNED NULL,
        type VARCHAR(20) NOT NULL,
        product VARCHAR(80) NOT NULL DEFAULT '',
        product_version VARCHAR(50) NOT NULL DEFAULT '',
        os VARCHAR(80) NOT NULL DEFAULT '',
        summary VARCHAR(255) NOT NULL,
        description MEDIUMTEXT NOT NULL,
        severity VARCHAR(10) NOT NULL DEFAULT 'medium',
        status VARCHAR(20) NOT NULL DEFAULT 'open',
        created_at DATETIME NOT NULL,
        updated_at DATETIME NOT NULL,
        UNIQUE KEY uq_customer_number (customer_id, number),
        KEY idx_status (status),
        KEY idx_updated (updated_at),
        CONSTRAINT fk_{$tk}_customer FOREIGN KEY (customer_id) REFERENCES `$c` (id) ON DELETE RESTRICT
    ) $opts");
    $pdo->exec("CREATE TABLE IF NOT EXISTS `$cm` (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
        ticket_id INT UNSIGNED NOT NULL,
        user_id INT UNSIGNED NULL,
        staff_name VARCHAR(160) NOT NULL DEFAULT '',
        body MEDIUMTEXT NOT NULL,
        event VARCHAR(60) NOT NULL DEFAULT '',
        created_at DATETIME NOT NULL,
        KEY idx_ticket (ticket_id),
        CONSTRAINT fk_{$cm}_ticket FOREIGN KEY (ticket_id) REFERENCES `$tk` (id) ON DELETE CASCADE
    ) $opts");
    $pdo->exec("CREATE TABLE IF NOT EXISTS `$fl` (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
        ticket_id INT UNSIGNED NOT NULL,
        comment_id INT UNSIGNED NULL,
        name VARCHAR(255) NOT NULL,
        path VARCHAR(255) NOT NULL,
        mime VARCHAR(100) NOT NULL DEFAULT '',
        size INT UNSIGNED NOT NULL DEFAULT 0,
        created_at DATETIME NOT NULL,
        KEY idx_ticket (ticket_id),
        CONSTRAINT fk_{$fl}_ticket FOREIGN KEY (ticket_id) REFERENCES `$tk` (id) ON DELETE CASCADE
    ) $opts");
}

/* ---------------------------------------------------------------- customers & their users */

function fetch_customers(): array
{
    $c = t('changelog_customers');
    return db()->query("SELECT c.*,
            (SELECT COUNT(*) FROM " . t('changelog_customer_users') . " u WHERE u.customer_id = c.id) AS user_count,
            (SELECT COUNT(*) FROM " . t('changelog_tickets') . " k WHERE k.customer_id = c.id AND k.status NOT IN ('resolved','closed')) AS open_count,
            (SELECT COUNT(*) FROM " . t('changelog_tickets') . " k WHERE k.customer_id = c.id) AS ticket_count
        FROM $c c ORDER BY c.name")->fetchAll();
}

function get_customer(int $id): ?array
{
    $st = db()->prepare('SELECT * FROM ' . t('changelog_customers') . ' WHERE id = ?');
    $st->execute([$id]);
    return $st->fetch() ?: null;
}

function validate_customer(string $name, string $code, int $id = 0): array
{
    $err = [];
    if ($name === '' || mb_strlen($name) > 160) $err[] = 'Vyplňte název zákazníka (max. 160 znaků).';
    if (!preg_match('/^[A-Z][A-Z0-9_]{0,8}[A-Z0-9]$/', $code)) $err[] = 'Klíč musí mít 2–10 znaků A–Z, 0–9 nebo _, začínat písmenem a nekončit _ (např. ACME nebo NBU_ZOTY).';
    $st = db()->prepare('SELECT id FROM ' . t('changelog_customers') . ' WHERE code = ? AND id <> ?');
    $st->execute([$code, $id]);
    if ($st->fetch()) $err[] = 'Klíč ' . $code . ' už používá jiný zákazník.';
    return $err;
}

function save_customer(string $name, string $code, int $id = 0): int
{
    if ($id) {
        db()->prepare('UPDATE ' . t('changelog_customers') . ' SET name = ?, code = ? WHERE id = ?')->execute([$name, $code, $id]);
        return $id;
    }
    db()->prepare('INSERT INTO ' . t('changelog_customers') . ' (name, code, created_at) VALUES (?, ?, ?)')->execute([$name, $code, now()]);
    return (int)db()->lastInsertId();
}

/** A customer can be deleted only while it has no tickets (its users go with it). */
function delete_customer(int $id): bool
{
    $st = db()->prepare('SELECT COUNT(*) FROM ' . t('changelog_tickets') . ' WHERE customer_id = ?');
    $st->execute([$id]);
    if ((int)$st->fetchColumn() > 0) return false;
    db()->prepare('DELETE FROM ' . t('changelog_customer_users') . ' WHERE customer_id = ?')->execute([$id]);
    db()->prepare('DELETE FROM ' . t('changelog_customers') . ' WHERE id = ?')->execute([$id]);
    return true;
}

function fetch_customer_users(int $customerId): array
{
    $st = db()->prepare('SELECT * FROM ' . t('changelog_customer_users') . ' WHERE customer_id = ? ORDER BY name, email');
    $st->execute([$customerId]);
    return $st->fetchAll();
}

function get_customer_user(int $id): ?array
{
    $st = db()->prepare('SELECT u.*, c.name AS customer_name, c.code AS customer_code FROM ' . t('changelog_customer_users') . ' u'
        . ' JOIN ' . t('changelog_customers') . ' c ON c.id = u.customer_id WHERE u.id = ?');
    $st->execute([$id]);
    return $st->fetch() ?: null;
}

function validate_customer_user(string $email, string $name, ?string $password, int $id = 0): array
{
    $err = [];
    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 190) $err[] = 'Zadejte platný e-mail.';
    if (mb_strlen($name) > 160) $err[] = 'Jméno je příliš dlouhé.';
    if ($password !== null && mb_strlen($password) < 8) $err[] = 'Heslo musí mít alespoň 8 znaků.';
    $st = db()->prepare('SELECT id FROM ' . t('changelog_customer_users') . ' WHERE email = ? AND id <> ?');
    $st->execute([$email, $id]);
    if ($st->fetch()) $err[] = 'E-mail ' . $email . ' už má jiný uživatel.';
    return $err;
}

function save_customer_user(int $customerId, string $email, string $name, ?string $password, int $id = 0): int
{
    $tbl = t('changelog_customer_users');
    if ($id) {
        db()->prepare("UPDATE $tbl SET email = ?, name = ? WHERE id = ? AND customer_id = ?")->execute([$email, $name, $id, $customerId]);
        if ($password !== null) customer_user_set_password($id, $password);
        return $id;
    }
    db()->prepare("INSERT INTO $tbl (customer_id, email, name, password_hash, active, created_at) VALUES (?, ?, ?, ?, 1, ?)")
        ->execute([$customerId, $email, $name, password_hash((string)$password, PASSWORD_DEFAULT), now()]);
    return (int)db()->lastInsertId();
}

function customer_user_set_password(int $id, string $password): void
{
    db()->prepare('UPDATE ' . t('changelog_customer_users') . ' SET password_hash = ? WHERE id = ?')
        ->execute([password_hash($password, PASSWORD_DEFAULT), $id]);
}

function customer_user_set_active(int $id, bool $active): void
{
    db()->prepare('UPDATE ' . t('changelog_customer_users') . ' SET active = ? WHERE id = ?')->execute([$active ? 1 : 0, $id]);
}

function delete_customer_user(int $id): void
{
    db()->prepare('DELETE FROM ' . t('changelog_customer_users') . ' WHERE id = ?')->execute([$id]);
}

/* ---------------------------------------------------------------- customer login (portal) */

function customer_verify(string $email, string $password): ?array
{
    $st = db()->prepare('SELECT id, password_hash, active FROM ' . t('changelog_customer_users') . ' WHERE email = ?');
    $st->execute([mb_strtolower(trim($email))]);
    $row = $st->fetch();
    $hash = $row['password_hash'] ?? password_hash('dummy-password', PASSWORD_DEFAULT);
    if (!password_verify($password, $hash) || !$row || !(int)$row['active']) return null;
    db()->prepare('UPDATE ' . t('changelog_customer_users') . ' SET last_login = ?, lang = ? WHERE id = ?')->execute([now(), lang(), $row['id']]);
    return get_customer_user((int)$row['id']);
}

/** The signed-in portal user (with customer_name / customer_code), or null. */
function portal_user(): ?array
{
    static $u = false;
    if ($u !== false) return $u;
    $id = (int)($_SESSION['zoty_customer'] ?? 0);
    $u = $id ? get_customer_user($id) : null;
    if ($u && !(int)$u['active']) {
        unset($_SESSION['zoty_customer']);
        $u = null;
    }
    return $u;
}

function require_portal_user(): array
{
    $u = portal_user();
    if (!$u) {
        $back = (string)($_SERVER['REQUEST_URI'] ?? '');
        header('Location: login' . ($back !== '' ? '?next=' . rawurlencode($back) : ''));
        exit;
    }
    return $u;
}

/* ---------------------------------------------------------------- tickets */

const TICKET_COLS = 'k.*, c.code AS customer_code, c.name AS customer_name, u.name AS reporter_name, u.email AS reporter_email, u.lang AS reporter_lang';

function ticket_from(): string
{
    return t('changelog_tickets') . ' k JOIN ' . t('changelog_customers') . ' c ON c.id = k.customer_id'
         . ' LEFT JOIN ' . t('changelog_customer_users') . ' u ON u.id = k.user_id';
}

function ticket_key(array $t): string
{
    return $t['customer_code'] . '-' . $t['number'];
}

function normalize_ticket(array $r): array
{
    foreach (['id', 'customer_id', 'number'] as $k) $r[$k] = (int)$r[$k];
    $r['user_id'] = $r['user_id'] === null ? null : (int)$r['user_id'];
    $r['key'] = ticket_key($r);
    $r['reporter_name'] = (string)($r['reporter_name'] ?? '');
    $r['reporter_email'] = (string)($r['reporter_email'] ?? '');
    return $r;
}

/** Filters: customer_id, status ('' | 'unresolved' | 'done' | one status), type, severity, q, user_id. */
function fetch_tickets(array $f = []): array
{
    $where = [];
    $args = [];
    if (!empty($f['customer_id'])) { $where[] = 'k.customer_id = ?'; $args[] = (int)$f['customer_id']; }
    if (!empty($f['user_id']))     { $where[] = 'k.user_id = ?';     $args[] = (int)$f['user_id']; }
    $s = (string)($f['status'] ?? '');
    if ($s === 'unresolved') $where[] = "k.status NOT IN ('resolved','closed')";
    elseif ($s === 'done') $where[] = "k.status IN ('resolved','closed')";
    elseif (in_array($s, TICKET_STATUSES, true)) { $where[] = 'k.status = ?'; $args[] = $s; }
    if (in_array($f['type'] ?? '', TICKET_TYPES, true))          { $where[] = 'k.type = ?';     $args[] = $f['type']; }
    if (in_array($f['severity'] ?? '', TICKET_SEVERITIES, true)) { $where[] = 'k.severity = ?'; $args[] = $f['severity']; }
    $q = trim((string)($f['q'] ?? ''));
    if ($q !== '') {
        if (preg_match('/^([A-Za-z][A-Za-z0-9_]{0,8}[A-Za-z0-9])-(\d+)$/', $q, $m)) {
            $where[] = '(c.code = ? AND k.number = ?)';
            array_push($args, strtoupper($m[1]), (int)$m[2]);
        } else {
            $where[] = '(k.summary LIKE ? OR k.description LIKE ?)';
            $like = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $q) . '%';
            array_push($args, $like, $like);
        }
    }
    $sql = 'SELECT ' . TICKET_COLS . ', (SELECT COUNT(*) FROM ' . t('changelog_ticket_comments') . " m WHERE m.ticket_id = k.id AND m.body <> '') AS comment_count"
         . ' FROM ' . ticket_from() . ($where ? ' WHERE ' . implode(' AND ', $where) : '')
         . " ORDER BY CASE WHEN k.status IN ('resolved','closed') THEN 1 ELSE 0 END, k.updated_at DESC";
    $st = db()->prepare($sql);
    $st->execute($args);
    return array_map('normalize_ticket', $st->fetchAll());
}

function get_ticket(int $id): ?array
{
    $st = db()->prepare('SELECT ' . TICKET_COLS . ' FROM ' . ticket_from() . ' WHERE k.id = ?');
    $st->execute([$id]);
    $r = $st->fetch();
    return $r ? normalize_ticket($r) : null;
}

/** Ticket by its key ("ACME-12"); with $customerId set, only that customer's ticket. */
function get_ticket_by_key(string $key, int $customerId = 0): ?array
{
    if (!preg_match('/^([A-Za-z][A-Za-z0-9_]{0,8}[A-Za-z0-9])-(\d{1,9})$/', trim($key), $m)) return null;
    $st = db()->prepare('SELECT ' . TICKET_COLS . ' FROM ' . ticket_from() . ' WHERE c.code = ? AND k.number = ?');
    $st->execute([strtoupper($m[1]), (int)$m[2]]);
    $r = $st->fetch();
    if (!$r || ($customerId && (int)$r['customer_id'] !== $customerId)) return null;
    return normalize_ticket($r);
}

function ticket_stats(int $customerId = 0): array
{
    $sql = 'SELECT status, COUNT(*) AS n FROM ' . t('changelog_tickets') . ($customerId ? ' WHERE customer_id = ?' : '') . ' GROUP BY status';
    $st = db()->prepare($sql);
    $st->execute($customerId ? [$customerId] : []);
    $out = array_fill_keys(TICKET_STATUSES, 0);
    foreach ($st->fetchAll() as $r) $out[$r['status']] = (int)$r['n'];
    return $out;
}

/** Numbers for the admin support overview. */
function support_overview(): array
{
    $tk = t('changelog_tickets');
    $one = function (string $sql, array $args = []): int { $st = db()->prepare($sql); $st->execute($args); return (int)$st->fetchColumn(); };
    return [
        'crisis'   => $one("SELECT COUNT(*) FROM $tk WHERE severity = 'crisis' AND status NOT IN ('resolved','closed')"),
        'resolved' => $one("SELECT COUNT(*) FROM $tk WHERE status IN ('resolved','closed') AND updated_at >= ?", [date('Y-m-d H:i:s', time() - 7 * 86400)]),
        'new_week' => $one("SELECT COUNT(*) FROM $tk WHERE created_at >= ?", [date('Y-m-d H:i:s', time() - 7 * 86400)]),
        'customers'=> $one('SELECT COUNT(*) FROM ' . t('changelog_customers')),
    ];
}

/** Form options: products = modules, versions = published versions, os from config. */
function ticket_options(): array
{
    return [
        'product' => module_names(),
        'product_version' => array_map(fn($v) => $v['version'], fetch_versions(true)),
        'os' => array_values(array_filter(array_map('strval', (array)support_config()['os']))),
    ];
}

/** Validates the new-ticket form; returns [clean data, errors keyed by field]. */
function validate_ticket(array $in): array
{
    $opt = ticket_options();
    $other = 'other';
    $d = [
        'type'            => (string)($in['type'] ?? ''),
        'product'         => trim((string)($in['product'] ?? '')),
        'product_version' => trim((string)($in['product_version'] ?? '')),
        'os'              => trim((string)($in['os'] ?? '')),
        'summary'         => trim(preg_replace('/\s+/u', ' ', (string)($in['summary'] ?? ''))),
        'description'     => str_replace("\r\n", "\n", trim((string)($in['description'] ?? ''))),
        'severity'        => (string)($in['severity'] ?? ''),
    ];
    $e = [];
    if (!in_array($d['type'], TICKET_TYPES, true)) $e['type'] = tr('ticket.err.type');
    foreach (['product', 'product_version', 'os'] as $k) {
        if ($d[$k] === '') $e[$k] = tr('ticket.err.required');
        elseif ($d[$k] !== $other && !in_array($d[$k], $opt[$k], true)) $e[$k] = tr('ticket.err.choose');
    }
    if ($d['summary'] === '') $e['summary'] = tr('ticket.err.required');
    elseif (mb_strlen($d['summary']) > 255) $e['summary'] = tr('ticket.err.too_long', ['max' => 255]);
    if ($d['description'] === '') $e['description'] = tr('ticket.err.required');
    elseif (mb_strlen($d['description']) > 60000) $e['description'] = tr('ticket.err.too_long', ['max' => 60000]);
    if (!in_array($d['severity'], TICKET_SEVERITIES, true)) $e['severity'] = tr('ticket.err.required');
    return [$d, $e];
}

/** Creates a ticket with the customer's next number; returns its id. */
function create_ticket(array $user, array $d): int
{
    $tbl = t('changelog_tickets');
    for ($try = 0; ; $try++) {
        $st = db()->prepare("SELECT COALESCE(MAX(number), 0) + 1 FROM $tbl WHERE customer_id = ?");
        $st->execute([$user['customer_id']]);
        $num = (int)$st->fetchColumn();
        try {
            db()->prepare("INSERT INTO $tbl (customer_id, number, user_id, type, product, product_version, os, summary, description, severity, status, created_at, updated_at)
                           VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'open', ?, ?)")
                ->execute([$user['customer_id'], $num, $user['id'], $d['type'], $d['product'], $d['product_version'], $d['os'],
                           $d['summary'], $d['description'], $d['severity'], now(), now()]);
            return (int)db()->lastInsertId();
        } catch (PDOException $ex) {
            if ($try >= 3) throw $ex;   // number taken by a parallel request – try the next one
        }
    }
}

function ticket_touch(int $id): void
{
    db()->prepare('UPDATE ' . t('changelog_tickets') . ' SET updated_at = ? WHERE id = ?')->execute([now(), $id]);
}

/** Changes the status and logs it in the activity; returns false when nothing changed. */
function ticket_set_status(array $ticket, string $status, ?array $user, string $staff = ''): bool
{
    if (!in_array($status, TICKET_STATUSES, true) || $status === $ticket['status']) return false;
    db()->prepare('UPDATE ' . t('changelog_tickets') . ' SET status = ?, updated_at = ? WHERE id = ?')->execute([$status, now(), $ticket['id']]);
    ticket_add_comment($ticket['id'], '', $user, $staff, 'status:' . $ticket['status'] . '>' . $status);
    return true;
}

function ticket_set_severity(array $ticket, string $severity, string $staff): bool
{
    if (!in_array($severity, TICKET_SEVERITIES, true) || $severity === $ticket['severity']) return false;
    db()->prepare('UPDATE ' . t('changelog_tickets') . ' SET severity = ?, updated_at = ? WHERE id = ?')->execute([$severity, now(), $ticket['id']]);
    ticket_add_comment($ticket['id'], '', null, $staff, 'severity:' . $ticket['severity'] . '>' . $severity);
    return true;
}

/** Adds a comment (or, with $event, an activity line). Pass $user for a customer, $staff for support staff. */
function ticket_add_comment(int $ticketId, string $body, ?array $user, string $staff = '', string $event = ''): int
{
    db()->prepare('INSERT INTO ' . t('changelog_ticket_comments') . ' (ticket_id, user_id, staff_name, body, event, created_at) VALUES (?, ?, ?, ?, ?, ?)')
        ->execute([$ticketId, $user['id'] ?? null, $user ? '' : $staff, $body, $event, now()]);
    $id = (int)db()->lastInsertId();
    ticket_touch($ticketId);
    return $id;
}

function ticket_comments(int $ticketId): array
{
    $st = db()->prepare('SELECT m.*, u.name AS user_name, u.email AS user_email FROM ' . t('changelog_ticket_comments') . ' m'
        . ' LEFT JOIN ' . t('changelog_customer_users') . ' u ON u.id = m.user_id WHERE m.ticket_id = ? ORDER BY m.created_at, m.id');
    $st->execute([$ticketId]);
    return $st->fetchAll();
}

/** Deletes a ticket with its comments and attachment files. */
function delete_ticket(int $id): void
{
    foreach (ticket_files($id) as $f) @unlink(ticket_file_path($f));
    @rmdir(__DIR__ . '/data/tickets/' . $id);
    db()->prepare('DELETE FROM ' . t('changelog_ticket_files') . ' WHERE ticket_id = ?')->execute([$id]);
    db()->prepare('DELETE FROM ' . t('changelog_ticket_comments') . ' WHERE ticket_id = ?')->execute([$id]);
    db()->prepare('DELETE FROM ' . t('changelog_tickets') . ' WHERE id = ?')->execute([$id]);
}

/* ---------------------------------------------------------------- attachments */

function ticket_files(int $ticketId): array
{
    $st = db()->prepare('SELECT * FROM ' . t('changelog_ticket_files') . ' WHERE ticket_id = ? ORDER BY id');
    $st->execute([$ticketId]);
    return $st->fetchAll();
}

function get_ticket_file(int $id): ?array
{
    $st = db()->prepare('SELECT * FROM ' . t('changelog_ticket_files') . ' WHERE id = ?');
    $st->execute([$id]);
    return $st->fetch() ?: null;
}

function ticket_file_path(array $f): string
{
    return __DIR__ . '/data/tickets/' . (int)$f['ticket_id'] . '/' . basename((string)$f['path']);
}

function ticket_file_is_image(array $f): bool
{
    return in_array($f['mime'], ['image/png', 'image/jpeg', 'image/gif', 'image/webp'], true);
}

/** Normalises $_FILES['x'] (single or multiple) to a list of files, skipping empty slots. */
function uploaded_list(?array $f): array
{
    if (!$f || !isset($f['name'])) return [];
    if (!is_array($f['name'])) return $f['error'] === UPLOAD_ERR_NO_FILE ? [] : [$f];
    $out = [];
    foreach ($f['name'] as $i => $n) {
        if ($f['error'][$i] === UPLOAD_ERR_NO_FILE) continue;
        $out[] = ['name' => $n, 'tmp_name' => $f['tmp_name'][$i], 'error' => $f['error'][$i], 'size' => $f['size'][$i]];
    }
    return $out;
}

/** Checks uploads before anything is saved; returns error messages. */
function validate_uploads(array $files): array
{
    $err = [];
    if (count($files) > TICKET_FILE_COUNT) $err[] = tr('ticket.err.file_count', ['max' => TICKET_FILE_COUNT]);
    foreach ($files as $f) {
        $name = (string)$f['name'];
        if ($f['error'] !== UPLOAD_ERR_OK) {
            $err[] = tr(in_array($f['error'], [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true) ? 'ticket.err.file_size' : 'ticket.err.file_upload',
                        ['name' => $name, 'max' => TICKET_FILE_MAX / 1048576]);
        } elseif ($f['size'] > TICKET_FILE_MAX) {
            $err[] = tr('ticket.err.file_size', ['name' => $name, 'max' => TICKET_FILE_MAX / 1048576]);
        } elseif (!in_array(strtolower(pathinfo($name, PATHINFO_EXTENSION)), TICKET_FILE_EXT, true)) {
            $err[] = tr('ticket.err.file_type', ['name' => $name]);
        }
    }
    return $err;
}

/** Stores validated uploads for a ticket (optionally tied to a comment). */
function store_ticket_files(int $ticketId, ?int $commentId, array $files): void
{
    $dir = __DIR__ . '/data/tickets/' . $ticketId;
    if ($files && !is_dir($dir) && !mkdir($dir, 0750, true)) throw new RuntimeException('Nelze vytvořit složku pro přílohy.');
    $mimes = ['png' => 'image/png', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'gif' => 'image/gif', 'webp' => 'image/webp',
              'pdf' => 'application/pdf', 'txt' => 'text/plain', 'log' => 'text/plain', 'csv' => 'text/csv', 'json' => 'application/json',
              'xml' => 'application/xml', 'zip' => 'application/zip'];
    foreach ($files as $f) {
        $ext  = strtolower(pathinfo((string)$f['name'], PATHINFO_EXTENSION));
        $mime = $mimes[$ext] ?? 'application/octet-stream';
        // an "image" that is not really one is stored as a plain download
        if (str_starts_with($mime, 'image/')) {
            $info = @getimagesize($f['tmp_name']);
            $real = [IMAGETYPE_PNG => 'image/png', IMAGETYPE_JPEG => 'image/jpeg', IMAGETYPE_GIF => 'image/gif', IMAGETYPE_WEBP => 'image/webp'];
            $mime = $info && isset($real[$info[2]]) ? $real[$info[2]] : 'application/octet-stream';
        }
        $stored = bin2hex(random_bytes(12)) . '.' . $ext;
        if (!move_uploaded_file($f['tmp_name'], $dir . '/' . $stored)) throw new RuntimeException('Přílohu se nepodařilo uložit.');
        @chmod($dir . '/' . $stored, 0640);
        $name = trim(preg_replace('/[\x00-\x1f"\\\\\/]+/u', '_', (string)$f['name'])) ?: 'file.' . $ext;
        db()->prepare('INSERT INTO ' . t('changelog_ticket_files') . ' (ticket_id, comment_id, name, path, mime, size, created_at) VALUES (?, ?, ?, ?, ?, ?, ?)')
            ->execute([$ticketId, $commentId, mb_substr($name, 0, 255), $stored, $mime, (int)$f['size'], now()]);
    }
}

function fmt_bytes(int $b): string
{
    if ($b < 1024) return $b . ' B';
    if ($b < 1048576) return round($b / 1024) . ' kB';
    return number_format($b / 1048576, 1, ',', ' ') . ' MB';
}

/* ---------------------------------------------------------------- rendering */

/** Markdown subset for tickets: headings, **bold**, *italic*, ~~strike~~, `code`, ``` blocks, lists, > quotes, links. */
function ticket_render(string $text): string
{
    $inline = function (string $s): string {
        $keep = [];
        $hold = function (string $html) use (&$keep): string { $keep[] = $html; return "\x01" . (count($keep) - 1) . "\x02"; };
        $s = preg_replace_callback('/`([^`]+)`/u', fn($m) => $hold('<code>' . h($m[1]) . '</code>'), $s);
        $s = preg_replace_callback('/\[([^\]]+)\]\((https?:\/\/[^\s)]+)\)/u',
            fn($m) => $hold('<a href="' . h($m[2]) . '" target="_blank" rel="noopener noreferrer">' . h($m[1]) . '</a>'), $s);
        $s = preg_replace_callback('/\bhttps?:\/\/[^\s<>"\x01]+[^\s<>"\x01.,;:!?)\]]/u',
            fn($m) => $hold('<a href="' . h($m[0]) . '" target="_blank" rel="noopener noreferrer">' . h($m[0]) . '</a>'), $s);
        $s = h($s);
        $s = preg_replace('/\*\*(?=\S)(.+?)(?<=\S)\*\*/u', '<strong>$1</strong>', $s);
        $s = preg_replace('/(?<![\w*])\*(?=\S)(.+?)(?<=\S)\*(?![\w*])/u', '<em>$1</em>', $s);
        $s = preg_replace('/(?<![\w_])_(?=\S)(.+?)(?<=\S)_(?![\w_])/u', '<em>$1</em>', $s);
        $s = preg_replace('/~~(?=\S)(.+?)(?<=\S)~~/u', '<del>$1</del>', $s);
        return preg_replace_callback('/\x01(\d+)\x02/', fn($m) => $keep[(int)$m[1]], $s);
    };

    $html = '';
    $para = [];
    $quote = [];
    $list = null;
    $code = null;
    $flush = function () use (&$para, &$quote, &$list, &$html, $inline) {
        if ($para)  { $html .= '<p>' . implode('<br>', array_map($inline, $para)) . '</p>'; $para = []; }
        if ($quote) { $html .= '<blockquote>' . implode('<br>', array_map($inline, $quote)) . '</blockquote>'; $quote = []; }
        if ($list)  { $html .= '</' . $list . '>'; $list = null; }
    };
    foreach (preg_split('/\R/u', $text) as $line) {
        if ($code !== null) {
            if (preg_match('/^\s*```/', $line)) { $html .= '<pre><code>' . h(implode("\n", $code)) . '</code></pre>'; $code = null; }
            else $code[] = $line;
            continue;
        }
        $t = trim($line);
        if (preg_match('/^```/', $t)) { $flush(); $code = []; continue; }
        if ($t === '') { $flush(); continue; }
        if (preg_match('/^(#{1,3})\s+(.+)$/u', $t, $m)) {
            $flush();
            $n = strlen($m[1]) + 2;
            $html .= "<h$n>" . $inline($m[2]) . "</h$n>";
            continue;
        }
        if (preg_match('/^>\s?(.*)$/u', $t, $m)) {
            if ($para || $list) $flush();
            $quote[] = $m[1];
            continue;
        }
        $kind = preg_match('/^[-*•]\s+(.*)$/u', $t, $m) ? 'ul' : (preg_match('/^\d+[.)]\s+(.*)$/u', $t, $m) ? 'ol' : null);
        if ($kind) {
            if ($para || $quote || ($list && $list !== $kind)) $flush();
            if (!$list) { $html .= '<' . $kind . '>'; $list = $kind; }
            $html .= '<li>' . $inline($m[1]) . '</li>';
            continue;
        }
        if ($list || $quote) $flush();
        $para[] = $t;
    }
    if ($code !== null) $html .= '<pre><code>' . h(implode("\n", $code)) . '</code></pre>';
    $flush();
    return $html;
}

/* ---------------------------------------------------------------- ticket keys in release notes */

/** Keys of all customers (cached per request), e.g. ['ACME', 'NBU_ZOTY']. */
function ticket_codes(): array
{
    static $codes = null;
    if ($codes === null) {
        try {
            $codes = db()->query('SELECT code FROM ' . t('changelog_customers'))->fetchAll(PDO::FETCH_COLUMN);
        } catch (PDOException $ex) {
            $codes = [];
        }
        usort($codes, fn($a, $b) => strlen($b) - strlen($a));   // longest first: NBU_ZOTY before NBU
    }
    return $codes;
}

/** Regex matching a ticket key of an existing customer ("NBU_ZOTY-12"), or null when there are no customers. */
function ticket_ref_regex(): ?string
{
    $codes = ticket_codes();
    if (!$codes) return null;
    $alt = implode('|', array_map(fn($c) => preg_quote($c, '/'), $codes));
    // not part of a word, URL, query string or markdown link text
    return '/(?<![\w\-\/=?&#\[])(' . $alt . ')-(\d{1,9})(?![\w\-])/u';
}

/** In already escaped HTML, turns ticket keys into links to the customer portal. */
function link_ticket_refs(string $html): string
{
    $re = ticket_ref_regex();
    if (!$re) return $html;
    return preg_replace_callback($re, function ($m) {
        $key = $m[1] . '-' . $m[2];
        return '<a class="ticket-ref" href="' . h(site_url('portal/ticket?key=' . rawurlencode($key))) . '" title="' . h(tr('ticket.ref_title')) . '">' . $key . '</a>';
    }, $html);
}

/** Ticket keys mentioned in a text. */
function ticket_refs_in(string $text): array
{
    $re = ticket_ref_regex();
    if (!$re || !preg_match_all($re, $text, $m, PREG_SET_ORDER)) return [];
    return array_values(array_unique(array_map(fn($x) => $x[1] . '-' . $x[2], $m)));
}

/** Release-note entries that mention the ticket key (title or description), newest first. */
function entries_for_ticket(string $key, bool $publishedOnly = true): array
{
    $like = '%' . strtr($key, ['!' => '!!', '%' => '!%', '_' => '!_']) . '%';
    $sql = 'SELECT ' . ENTRY_COLS . ' FROM ' . t('changelog_entries') . ' e JOIN ' . t('changelog_versions') . ' v ON v.id = e.version_id'
         . " WHERE (e.title LIKE ? ESCAPE '!' OR e.body LIKE ? ESCAPE '!')"
         . ($publishedOnly ? ' AND e.published = 1 AND v.published = 1' : '')
         . ' ORDER BY v.release_date DESC, v.id DESC, e.id';
    $st = db()->prepare($sql);
    $st->execute([$like, $like]);
    // LIKE also matches ACME-1 inside ACME-12 – keep only exact references
    return array_values(array_filter(array_map('normalize_entry', $st->fetchAll()),
        fn($e) => in_array($key, ticket_refs_in($e['title'] . ' ' . $e['body']), true)));
}

/** Suggested release-note entry for a ticket (admin "Zapsat do release notes"). */
function entry_draft_from_ticket(array $t): array
{
    $type = ['incident' => 'fixed', 'feature' => 'new', 'question' => 'improved'][$t['type']] ?? 'fixed';
    $module = in_array($t['product'], module_names(), true) ? $t['product'] : '';
    return ['type' => $type, 'module' => $module, 'title' => $t['summary'], 'body' => 'Požadavek ' . $t['key'] . '.'];
}

function status_label(string $s, ?string $lang = null): string   { return tr('ticket.status.' . $s, [], $lang); }
function severity_label(string $s, ?string $lang = null): string { return tr('ticket.severity.' . $s, [], $lang); }
function ticket_type_label(string $s, ?string $lang = null): string { return tr('ticket.type.' . $s, [], $lang); }

function status_lozenge(string $s): string
{
    return '<span class="lozenge lozenge--' . h($s) . '">' . h(status_label($s)) . '</span>';
}

function severity_badge(string $s): string
{
    return '<span class="sev sev--' . h($s) . '"><i aria-hidden="true"></i>' . h(severity_label($s)) . '</span>';
}

/** Option value as shown to people ("other" becomes "Other"). */
function option_label(string $v): string
{
    return $v === 'other' ? tr('ticket.other') : $v;
}

/** Activity line for a status / severity change event. */
function event_text(string $event): string
{
    if (preg_match('/^(status|severity):(\w+)>(\w+)$/', $event, $m)) {
        $f = $m[1] === 'status' ? 'status_label' : 'severity_label';
        return tr('ticket.event.' . $m[1], ['from' => $f($m[2]), 'to' => $f($m[3])]);
    }
    if ($event === 'created') return tr('ticket.event.created');
    return $event;
}

/* ---------------------------------------------------------------- notifications */

function ticket_staff_emails(): array
{
    $n = support_config()['notify'];
    return array_values(array_filter(array_map('trim', is_array($n) ? $n : explode(',', (string)$n)),
        fn($e) => (bool)filter_var($e, FILTER_VALIDATE_EMAIL)));
}

/** E-mails support staff about a new ticket or a customer's comment. Failures are only logged. */
function notify_staff(array $ticket, string $what, string $body = ''): void
{
    $to = ticket_staff_emails();
    if (!$to) return;
    $url = site_url('admin/ticket?key=' . rawurlencode($ticket['key']));
    $subject = '[' . $ticket['key'] . '] ' . ($what === 'new' ? 'Nový požadavek: ' : 'Nový komentář: ') . $ticket['summary'];
    $meta = $ticket['customer_name'] . ' · ' . ticket_type_label($ticket['type'], 'cs') . ' · ' . severity_label($ticket['severity'], 'cs')
          . ' · ' . ($ticket['reporter_name'] ?: $ticket['reporter_email']);
    $text = $meta . "\r\n\r\n" . ($body !== '' ? $body : $ticket['description']) . "\r\n\r\n" . $url . "\r\n";
    $html = '<p style="margin:0 0 4px;color:#656565;font-size:13px">' . h($ticket['key']) . ' · ' . h($meta) . '</p>'
          . '<h2 style="margin:0 0 12px;font-size:20px">' . h($ticket['summary']) . '</h2>'
          . '<div style="color:#444">' . ticket_render($body !== '' ? $body : $ticket['description']) . '</div>'
          . '<p style="margin:24px 0 0"><a href="' . h($url) . '" style="background:#6144a3;color:#fff;text-decoration:none;padding:12px 22px;border-radius:8px;font-weight:600;display:inline-block">Otevřít požadavek</a></p>';
    $msgs = array_map(fn($e) => ['to' => $e, 'subject' => $subject, 'text' => $text, 'html' => mail_layout($ticket['key'], $html), 'tag' => $ticket['key'] . ' → podpora'], $to);
    mail_send($msgs);
}

/** E-mails the reporter about a support reply or a status change. Failures are only logged. */
function notify_customer(array $ticket, string $body = '', string $newStatus = ''): void
{
    $to = $ticket['reporter_email'];
    if (!filter_var($to, FILTER_VALIDATE_EMAIL)) return;
    $L = in_array($ticket['reporter_lang'] ?? '', LANGS, true) ? $ticket['reporter_lang'] : 'cs';
    $url = site_url('portal/ticket?key=' . rawurlencode($ticket['key']));
    $subject = '[' . $ticket['key'] . '] ' . $ticket['summary'];
    $lines = [];
    if ($body !== '') $lines[] = tr('ticket.mail.reply', [], $L);
    if ($newStatus !== '') $lines[] = tr('ticket.mail.status', ['status' => status_label($newStatus, $L)], $L);
    $text = implode("\r\n", $lines) . "\r\n\r\n" . ($body !== '' ? $body . "\r\n\r\n" : '') . $url . "\r\n";
    $html = '<p style="margin:0 0 4px;color:#656565;font-size:13px">' . h($ticket['key']) . '</p>'
          . '<h2 style="margin:0 0 12px;font-size:20px">' . h($ticket['summary']) . '</h2>'
          . '<p>' . implode('<br>', array_map('h', $lines)) . '</p>'
          . ($body !== '' ? '<div style="color:#444;border-left:3px solid #d8d0e8;padding-left:12px">' . ticket_render($body) . '</div>' : '')
          . '<p style="margin:24px 0 0"><a href="' . h($url) . '" style="background:#6144a3;color:#fff;text-decoration:none;padding:12px 22px;border-radius:8px;font-weight:600;display:inline-block">'
          . h(tr('ticket.mail.open', [], $L)) . '</a></p>';
    mail_send([['to' => $to, 'subject' => $subject, 'text' => $text, 'html' => mail_layout($ticket['key'], $html), 'tag' => $ticket['key'] . ' → zákazník']]);
}
