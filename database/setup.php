<?php
// Creates the `setlo` database, all tables, and the README demo scenario.
// Run from the project root:  C:\xampp\php\php.exe database\setup.php
// WARNING: drops and recreates every Setlo table.

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Run this script from the command line.');
}

require __DIR__ . '/../includes/bootstrap.php';

function fail(string $message, int $status = 400): void
{
    throw new RuntimeException($message);
}

require __DIR__ . '/../includes/domain.php';

// ---------- Schema ----------
$c = config('db');
$root = new PDO("mysql:host={$c['host']};port={$c['port']};charset={$c['charset']}", $c['user'], $c['pass'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$sql = preg_replace('/(^|\s)-- .*$/m', '$1', file_get_contents(__DIR__ . '/schema.sql'));
foreach (array_filter(array_map('trim', explode(';', $sql))) as $stmt) {
    $root->exec($stmt);
}
echo "Schema created.\n";

// ---------- Users ----------
$users = [
    'miguel' => ['Miguel Santos', 'miguel@setlo.app', 'GCash', '0917 555 0123 · Miguel S.', '#0f766e'],
    'ana'    => ['Ana Reyes', 'ana@setlo.app', 'Maya', '0918 222 4455 · Ana R.', '#818cf8'],
    'chris'  => ['Chris Uy', 'chris@setlo.app', 'GCash', '0927 888 1100 · Christopher U.', '#f59e0b'],
    'dani'   => ['Dani Cruz', 'dani@setlo.app', 'GCash', null, '#f43f5e'],
    'jella'  => ['Jella Ramos', 'jella@setlo.app', 'Bank Transfer', 'BPI 1234-5678-90 · Jella Ramos', '#a855f7'],
    'rey'    => ['Rey Domingo', 'rey@setlo.app', 'Cash', null, '#64748b'],
];
$id = [];
$hash = password_hash('password123', PASSWORD_DEFAULT);
foreach ($users as $key => [$name, $email, $method, $account, $color]) {
    q('INSERT INTO users (full_name, email, password_hash, payment_method, payment_account, avatar_color, pay_code, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, NOW() - INTERVAL ? DAY)',
        [$name, $email, $hash, $method, $account, $color, new_pay_code(), random_int(20, 120)]);
    $id[$key] = (int) db()->lastInsertId();
}
q("INSERT INTO users (full_name, email, password_hash, role, avatar_color) VALUES ('App Manager', 'admin@setlo.app', ?, 'admin', '#0d9488')",
    [password_hash('admin12345', PASSWORD_DEFAULT)]);

// ---------- Helpers ----------
function make_bill(string $name, int $creator, int $payer, array $members, string $created, array $items, float $tax = 0, float $svc = 0): array
{
    q('INSERT INTO bills (name, creator_id, payer_id, status, tax, service_charge, receipt_total, ocr_status, created_at) VALUES (?, ?, ?, ?, ?, ?, NULL, ?, ?)',
        [$name, $creator, $payer, 'active', $tax, $svc, 'ok', $created]);
    $billId = (int) db()->lastInsertId();
    foreach ($members as $m) {
        q('INSERT INTO bill_members (bill_id, user_id, joined_at) VALUES (?, ?, ?)', [$billId, $m, $created]);
    }
    foreach ($items as $pos => $it) {
        [$itemName, $qty, $unit, $who] = $it;
        q("INSERT INTO receipt_items (bill_id, position, name, qty, unit_price, source, ocr_name, was_corrected) VALUES (?, ?, ?, ?, ?, 'ocr', ?, ?)",
            [$billId, $pos, $itemName, $qty, $unit, $it[4] ?? $itemName, (int) isset($it[4])]);
        $itemId = (int) db()->lastInsertId();
        foreach ($who as $uid) {
            q('INSERT INTO item_assignments (item_id, user_id) VALUES (?, ?)', [$itemId, $uid]);
        }
    }
    $sub = (int) q('SELECT SUM(ROUND(qty * unit_price * 100)) FROM receipt_items WHERE bill_id = ?', [$billId])->fetchColumn();
    q('UPDATE bills SET receipt_total = ? WHERE id = ?', [pesos($sub) + $tax + $svc, $billId]);
    return bill_for($billId, ['id' => $creator, 'role' => 'admin']);
}

/** Generate settlements, then backdate them and apply the given status history per sender. */
function settle(array $bill, string $at, array $history): void
{
    start_settling($bill, ['id' => $bill['creator_id']]);
    q('UPDATE bills SET settling_at = ? WHERE id = ?', [$at, $bill['id']]);
    q('UPDATE settlements SET created_at = ? WHERE bill_id = ?', [$at, $bill['id']]);
    q('UPDATE settlement_events e JOIN settlements s ON s.id = e.settlement_id SET e.created_at = ? WHERE s.bill_id = ?', [$at, $bill['id']]);
    q('DELETE n FROM notifications n WHERE n.type = ?', ['settling']);

    foreach ($history as $fromId => $steps) {
        $s = q('SELECT id, to_user_id FROM settlements WHERE bill_id = ? AND from_user_id = ?', [$bill['id'], $fromId])->fetch();
        foreach ($steps as [$event, $when, $note]) {
            $actor = in_array($event, ['marked_paid', 'resent'], true) ? $fromId : (int) $s['to_user_id'];
            q('INSERT INTO settlement_events (settlement_id, actor_id, event, note, created_at) VALUES (?, ?, ?, ?, ?)', [$s['id'], $actor, $event, $note, $when]);
            if ($event === 'marked_paid') {
                q("UPDATE settlements SET status = 'awaiting', paid_at = ? WHERE id = ?", [$when, $s['id']]);
            } elseif ($event === 'confirmed') {
                q("UPDATE settlements SET status = 'settled', confirmed_at = ? WHERE id = ?", [$when, $s['id']]);
            } elseif ($event === 'disputed') {
                q("UPDATE settlements SET status = 'disputed', disputed_at = ?, dispute_reason = ? WHERE id = ?", [$when, $note, $s['id']]);
            }
        }
    }
    $open = (int) q("SELECT COUNT(*) FROM settlements WHERE bill_id = ? AND status <> 'settled'", [$bill['id']])->fetchColumn();
    if ($open === 0) {
        q("UPDATE bills SET status = 'closed', closed_at = (SELECT MAX(confirmed_at) FROM settlements WHERE bill_id = ?) WHERE id = ?", [$bill['id'], $bill['id']]);
    }
    q("DELETE FROM notifications WHERE type = 'closed'");
}

['miguel' => $mi, 'ana' => $an, 'chris' => $ch, 'dani' => $da, 'jella' => $je, 'rey' => $re] = $id;
$d = fn (string $rel) => date('Y-m-d H:i:s', strtotime($rel));

// 1) Closed bill with a full audit trail.
$b = make_bill('Jollibee Merienda Run', $mi, $mi, [$mi, $an, $ch], $d('-23 days 15:10'), [
    ['Chickenjoy 2pc', 2, 129.00, [$mi, $an]],
    ['Jolly Spaghetti', 1, 69.00, [$ch]],
    ['Peach Mango Pie', 3, 29.00, [$mi, $an, $ch]],
    ['Coke Float', 3, 47.00, [$mi, $an, $ch]],
]);
settle($b, $d('-23 days 15:40'), [
    $an => [['marked_paid', $d('-23 days 18:02'), 'Ana marked the payment as sent.'], ['confirmed', $d('-22 days 09:12'), 'Miguel confirmed receiving the payment.']],
    $ch => [['marked_paid', $d('-23 days 19:30'), 'Chris marked the payment as sent.'], ['confirmed', $d('-22 days 11:40'), 'Miguel confirmed receiving the payment.']],
]);

// 2) Closed bill where Miguel owed Jella.
$b = make_bill('Samgyup Payday Treat', $je, $je, [$je, $mi, $an], $d('-40 days 19:00'), [
    ['Samgyup Unli Set', 3, 399.00, [$je, $mi, $an]],
    ['Soju', 2, 145.00, [$je, $mi]],
    ['Cheese Add-on', 1, 90.00, [$an]],
], 0, 60.00);
settle($b, $d('-40 days 21:00'), [
    $mi => [['marked_paid', $d('-39 days 08:03'), 'Miguel marked the payment as sent.'], ['confirmed', $d('-39 days 09:47'), 'Jella confirmed receiving the payment.']],
    $an => [['marked_paid', $d('-39 days 10:15'), 'Ana marked the payment as sent.'], ['confirmed', $d('-39 days 12:00'), 'Jella confirmed receiving the payment.']],
]);

// 3) Settling bill (README demo): one awaiting, one disputed, one pending.
$b = make_bill('Mang Inasal Friday', $mi, $mi, [$mi, $an, $ch, $da], $d('-2 days 19:05'), [
    ['Chicken Inasal', 2, 179.00, [$mi, $da]],
    ['Bangus Sisig', 1, 149.00, [$ch], 'Banaus 5is1g'],
    ['Rice', 3, 45.00, [$mi, $da, $ch]],
    ['Halo-Halo', 2, 89.00, [$mi, $ch]],
    ['Iced Tea', 4, 65.00, [$da, $an]],
    ['Buko Pandan', 1, 70.00, [$an]],
], 55.00, 35.00);
settle($b, $d('-2 days 20:15'), [
    $an => [['marked_paid', $d('-10 minutes'), 'Ana marked the payment as sent.']],
    $ch => [['marked_paid', $d('-1 day 20:15'), 'Chris marked the payment as sent.'],
            ['disputed', $d('-1 day 21:02'), 'Payment not received yet — no GCash reference number was attached.']],
]);

// 4) Active bill created by Rey, still being assigned.
make_bill('Boodle Fight – Team Lunch', $re, $re, [$re, $mi, $an, $da], $d('-1 day 12:30'), [
    ['Boodle Platter (good for 4)', 1, 1450.00, [$re, $mi, $an, $da]],
    ['Grilled Liempo', 1, 320.00, []],
    ['Pancit Canton', 1, 220.00, []],
    ['Buko Juice Pitcher', 1, 160.00, [$mi, $an]],
]);

// Unread notifications so the bell has something to show.
$anaOwes = (float) q("SELECT amount FROM settlements WHERE from_user_id = ? AND status = 'awaiting'", [$an])->fetchColumn();
notify($mi, 'paid', 'Ana marked ' . peso_str(cents($anaOwes)) . ' as Paid for Mang Inasal Friday. Confirm you received it.', 'my-settlements.php?tab=owed');
notify($mi, 'added', 'Rey added you to Boodle Fight – Team Lunch.', 'bill-items.php?bill=' . q("SELECT id FROM bills WHERE name LIKE 'Boodle%'")->fetchColumn());
notify($ch, 'disputed', 'Miguel disputed your payment for Mang Inasal Friday.', 'my-settlements.php');

echo "Demo data loaded.\n\n";
echo "Sign in at http://localhost" . config('base_url') . "/\n";
echo "  Users:  miguel@setlo.app, ana@setlo.app, chris@setlo.app, dani@setlo.app, jella@setlo.app, rey@setlo.app  (password: password123)\n";
echo "  Admin:  admin@setlo.app  (password: admin12345)\n";
