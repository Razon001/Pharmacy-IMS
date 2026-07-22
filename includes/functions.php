<?php
/**
 * Shared helper functions.
 */
require_once __DIR__ . '/../config/database.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/* ---------- Output / input helpers ---------- */

function e($v): string
{
    return htmlspecialchars((string)($v ?? ''), ENT_QUOTES, 'UTF-8');
}

function redirect(string $url): void
{
    header('Location: ' . $url);
    exit;
}

function post(string $key, $default = '')
{
    return $_POST[$key] ?? $default;
}

function get(string $key, $default = '')
{
    return $_GET[$key] ?? $default;
}

/* ---------- Flash messages ---------- */

function flash(string $msg, string $type = 'success'): void
{
    $_SESSION['flash'][] = ['msg' => $msg, 'type' => $type];
}

function get_flashes(): array
{
    $f = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $f;
}

/* ---------- CSRF protection ---------- */

function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf" value="' . csrf_token() . '">';
}

function csrf_check(): void
{
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!isset($_POST['csrf']) || !hash_equals($_SESSION['csrf'] ?? '', $_POST['csrf'])) {
            http_response_code(419);
            die('Invalid or expired form token. Please go back and try again.');
        }
    }
}

/* ---------- Authentication / roles ---------- */

function current_user(): ?array
{
    return $_SESSION['user'] ?? null;
}

function require_login(): void
{
    if (!current_user()) {
        redirect('index.php');
    }
}

function require_role(array $roles): void
{
    require_login();
    if (!in_array(current_user()['role'], $roles, true)) {
        http_response_code(403);
        die('<h2 style="font-family:sans-serif;padding:2rem">403 - You do not have permission to view this page.</h2>');
    }
}

function has_role(array $roles): bool
{
    $u = current_user();
    return $u && in_array($u['role'], $roles, true);
}

/* ---------- Settings ---------- */

function settings(): array
{
    static $s = null;
    if ($s === null) {
        $s = db()->query('SELECT * FROM settings LIMIT 1')->fetch() ?: [];
    }
    return $s;
}

function currency(): string
{
    return settings()['currency'] ?? '$';
}

function money($amount): string
{
    return currency() . number_format((float)$amount, 2);
}

/* ---------- Stock helpers ---------- */

/** Total available quantity of a medicine across all batches. */
function medicine_stock(int $medicineId): int
{
    $st = db()->prepare('SELECT COALESCE(SUM(quantity),0) FROM batches WHERE medicine_id = ?');
    $st->execute([$medicineId]);
    return (int)$st->fetchColumn();
}

/** Default active sale price = earliest-expiring batch that has stock. */
function medicine_price(int $medicineId): float
{
    $st = db()->prepare(
        'SELECT sale_price FROM batches
         WHERE medicine_id = ? AND quantity > 0
         ORDER BY expiry_date ASC, id ASC LIMIT 1'
    );
    $st->execute([$medicineId]);
    $p = $st->fetchColumn();
    if ($p !== false) {
        return (float)$p;
    }
    // fall back to latest known price
    $st = db()->prepare('SELECT sale_price FROM batches WHERE medicine_id = ? ORDER BY id DESC LIMIT 1');
    $st->execute([$medicineId]);
    return (float)($st->fetchColumn() ?: 0);
}

/**
 * Deduct stock using FEFO (First-Expiry-First-Out). Records movements and
 * returns the batch lines actually consumed: [['batch_id'=>, 'qty'=>, 'cost'=>], ...]
 * Assumes it runs inside a transaction and stock was validated beforehand.
 */
function deduct_stock_fefo(int $medicineId, int $qty, ?int $userId, string $reference): array
{
    $pdo = db();
    $lines = [];
    $remaining = $qty;

    $st = $pdo->prepare(
        'SELECT id, quantity, purchase_price FROM batches
         WHERE medicine_id = ? AND quantity > 0
         ORDER BY expiry_date ASC, id ASC FOR UPDATE'
    );
    $st->execute([$medicineId]);
    $batches = $st->fetchAll();

    foreach ($batches as $b) {
        if ($remaining <= 0) {
            break;
        }
        $take = min($remaining, (int)$b['quantity']);
        $upd = $pdo->prepare('UPDATE batches SET quantity = quantity - ? WHERE id = ?');
        $upd->execute([$take, $b['id']]);

        $mv = $pdo->prepare(
            'INSERT INTO stock_movements (medicine_id, batch_id, type, quantity, reference, user_id)
             VALUES (?,?,?,?,?,?)'
        );
        $mv->execute([$medicineId, $b['id'], 'out', $take, $reference, $userId]);

        $lines[] = ['batch_id' => (int)$b['id'], 'qty' => $take, 'cost' => (float)$b['purchase_price']];
        $remaining -= $take;
    }

    if ($remaining > 0) {
        // Should not happen if validated; abort the transaction safely.
        throw new RuntimeException('Insufficient stock for medicine #' . $medicineId);
    }
    return $lines;
}

/* ---------- Dashboard / alert queries ---------- */

function low_stock_medicines(): array
{
    $sql = 'SELECT m.id, m.name, m.reorder_level,
                   COALESCE(SUM(b.quantity),0) AS stock
            FROM medicines m
            LEFT JOIN batches b ON b.medicine_id = m.id
            WHERE m.status = 1
            GROUP BY m.id
            HAVING stock <= m.reorder_level
            ORDER BY stock ASC';
    return db()->query($sql)->fetchAll();
}

function expiring_batches(int $days = 60): array
{
    $st = db()->prepare(
        'SELECT b.*, m.name AS medicine_name
         FROM batches b JOIN medicines m ON m.id = b.medicine_id
         WHERE b.quantity > 0
           AND b.expiry_date IS NOT NULL
           AND b.expiry_date <= DATE_ADD(CURDATE(), INTERVAL ? DAY)
         ORDER BY b.expiry_date ASC'
    );
    $st->execute([$days]);
    return $st->fetchAll();
}

/** Generate next invoice number like INV-000123. */
function next_invoice_no(): string
{
    $prefix = settings()['invoice_prefix'] ?? 'INV';
    $n = (int)db()->query('SELECT COALESCE(MAX(id),0)+1 FROM sales')->fetchColumn();
    return $prefix . '-' . str_pad((string)$n, 6, '0', STR_PAD_LEFT);
}

function nav_active(string $file): string
{
    return basename($_SERVER['PHP_SELF']) === $file ? 'active' : '';
}
