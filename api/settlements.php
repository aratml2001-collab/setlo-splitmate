<?php
// Two-sided settlement confirmation: sender marks Paid, receiver Confirms or Rejects.
require __DIR__ . '/../includes/api.php';
require __DIR__ . '/../includes/uploads.php';

const NUDGE_COOLDOWN_HOURS = 12;

$me = api_user();

const SETTLEMENT_SELECT = 'SELECT s.*, b.name AS bill_name, b.creator_id AS bill_creator_id, fu.full_name AS from_name, fu.avatar_color AS from_color, fu.role AS from_role,
    tu.full_name AS to_name, tu.avatar_color AS to_color, tu.payment_method AS to_method, tu.payment_account AS to_account, tu.pay_code AS to_pay_code
    FROM settlements s JOIN bills b ON b.id = s.bill_id
    JOIN users fu ON fu.id = s.from_user_id JOIN users tu ON tu.id = s.to_user_id';

function load_settlement(int $id, array $me): array
{
    $s = q(SETTLEMENT_SELECT . ' WHERE s.id = ?', [$id])->fetch();
    if (!$s) {
        fail('Settlement not found.', 404);
    }
    if ($me['role'] !== 'admin' && !is_member((int) $s['bill_id'], $me['id'])) {
        fail('You are not part of this settlement.', 403);
    }
    return $s;
}

if (method() === 'GET') {
    if (isset($_GET['proof'])) {
        // Proof screenshots are private to the two parties (and the app manager).
        $s = load_settlement(int_param('proof'), $me);
        $actsForGuest = $s['from_role'] === 'guest' && (int) $s['bill_creator_id'] === $me['id'];
        if ($me['role'] !== 'admin' && !$actsForGuest && !in_array($me['id'], [(int) $s['from_user_id'], (int) $s['to_user_id']], true)) {
            fail('Only the sender and receiver can view this proof.', 403);
        }
        serve_upload('proofs', $s['proof_image']);
    }

    if (isset($_GET['id'])) {
        $s = load_settlement(int_param('id'), $me);
        $events = q(
            'SELECT e.event, e.note, e.created_at, u.full_name AS actor FROM settlement_events e
             LEFT JOIN users u ON u.id = e.actor_id WHERE e.settlement_id = ? ORDER BY e.created_at, e.id',
            [$s['id']]
        )->fetchAll();
        $others = array_values(array_filter(bill_settlements((int) $s['bill_id']), fn ($o) => $o['id'] !== (int) $s['id']));
        json_ok(['settlement' => settlement_row($s), 'events' => $events, 'others' => $others]);
    }

    // Includes settlements of guests on bills I created — I settle on their behalf.
    $rows = q(SETTLEMENT_SELECT . " WHERE s.from_user_id = ? OR s.to_user_id = ? OR (fu.role = 'guest' AND b.creator_id = ?)
        ORDER BY FIELD(s.status, 'awaiting', 'disputed', 'pending', 'settled'), s.created_at DESC", [$me['id'], $me['id'], $me['id']])->fetchAll();
    // When I last sent a reminder on each settlement, so the UI can show the cooldown.
    $nudged = q(
        "SELECT settlement_id, MAX(created_at) FROM settlement_events WHERE actor_id = ? AND event = 'nudged' GROUP BY settlement_id",
        [$me['id']]
    )->fetchAll(PDO::FETCH_KEY_PAIR);
    $owe = $owed = [];
    foreach ($rows as $r) {
        $row = settlement_row($r) + ['my_last_nudge' => $nudged[$r['id']] ?? null];
        if ((int) $r['to_user_id'] === $me['id']) {
            $owed[] = $row;
        } else {
            $owe[] = $row; // mine, or a guest's I pay on behalf of
        }
    }
    json_ok(['owe' => $owe, 'owed' => $owed]);
}

if (method() !== 'POST') {
    fail('Method not allowed.', 405);
}

$s = load_settlement(int_param('id'), $me);
$id = (int) $s['id'];
$fromGuest = $s['from_role'] === 'guest';
// The Bill Creator acts for guests, who have no account.
$isSender = (int) $s['from_user_id'] === $me['id'] || ($fromGuest && (int) $s['bill_creator_id'] === $me['id']);
$onBehalf = $fromGuest ? ' on behalf of ' . first_name($s['from_name']) : '';
$isReceiver = (int) $s['to_user_id'] === $me['id'];
$amount = peso_str(cents($s['amount']));
$myName = first_name($me['full_name']);

/** Atomically move a settlement from one status to another; fails if someone else changed it first. */
// $extraSql is interpolated into the SQL below, so every caller in this file must pass one of these
// fixed literal fragments — never anything built from request input.
const TRANSITION_EXTRA_SQL = [
    '',
    ', paid_at = NOW(), payment_ref = ?, proof_image = ?',
    ', confirmed_at = NOW()',
    ', disputed_at = NOW(), dispute_reason = ?',
    ', paid_at = NULL, payment_ref = NULL, proof_image = NULL',
    ', paid_at = NOW(), confirmed_at = NOW()',
];

function transition(int $id, string $from, string $to, string $extraSql = '', array $extra = []): void
{
    if (!in_array($extraSql, TRANSITION_EXTRA_SQL, true)) {
        throw new InvalidArgumentException('Invalid $extraSql for transition().');
    }
    $n = q("UPDATE settlements SET status = ? $extraSql WHERE id = ? AND status = ?", array_merge([$to], $extra, [$id, $from]))->rowCount();
    if ($n === 0) {
        fail('This settlement changed in the meantime. Refresh and try again.', 409);
    }
}

$pdo = db();
switch (input('action', '')) {
    case 'mark_paid':
        if (!$isSender) {
            fail('Only the person who owes can mark this as paid.', 403);
        }
        $ref = trim(str_input('payment_ref', 60, false));
        require_valid(valid_ref($ref), 'payment_ref');
        $proof = save_uploaded_image('proof', 'proofs', 'settlement' . $id, false);
        $pdo->beginTransaction();
        try {
            transition($id, 'pending', 'awaiting', ', paid_at = NOW(), payment_ref = ?, proof_image = ?', [$ref ?: null, $proof]);
        } catch (ApiError $e) {
            delete_upload('proofs', $proof);
            throw $e;
        }
        $details = array_filter([$ref ? "ref no. $ref" : null, $proof ? 'proof attached' : null]);
        log_event($id, $me['id'], 'marked_paid', "$myName$onBehalf marked $amount as sent to " . first_name($s['to_name']) . ($details ? ' (' . implode(', ', $details) . ')' : '') . '.');
        notify((int) $s['to_user_id'], 'paid', "$myName$onBehalf marked $amount as Paid for {$s['bill_name']}. Confirm you received it.", 'my-settlements?tab=owed');
        $pdo->commit();
        break;

    case 'confirm':
        if (!$isReceiver) {
            fail('Only the receiver can confirm this payment.', 403);
        }
        $pdo->beginTransaction();
        transition($id, 'awaiting', 'settled', ', confirmed_at = NOW()');
        log_event($id, $me['id'], 'confirmed', "$myName confirmed receiving the payment. Settlement closed.");
        notify((int) $s['from_user_id'], 'confirmed', "$myName confirmed your $amount payment for {$s['bill_name']}.", 'settlement-audit?id=' . $id);
        maybe_close_bill((int) $s['bill_id']);
        $pdo->commit();
        break;

    case 'reject':
        if (!$isReceiver) {
            fail('Only the receiver can reject this payment.', 403);
        }
        $reason = str_input('reason', 500);
        if (strlen($reason) < 5) {
            fail('Please explain in at least 5 characters.', 422, ['fields' => ['reason' => 'Please explain in at least 5 characters.']]);
        }
        $pdo->beginTransaction();
        transition($id, 'awaiting', 'disputed', ', disputed_at = NOW(), dispute_reason = ?', [$reason]);
        log_event($id, $me['id'], 'disputed', $reason);
        notify((int) $s['from_user_id'], 'disputed', "$myName disputed your $amount payment for {$s['bill_name']}.", 'my-settlements');
        $pdo->commit();
        break;

    case 'resend':
        if (!$isSender) {
            fail('Only the sender can resend this payment.', 403);
        }
        $pdo->beginTransaction();
        transition($id, 'disputed', 'pending', ', paid_at = NULL, payment_ref = NULL, proof_image = NULL');
        delete_upload('proofs', $s['proof_image']);
        log_event($id, $me['id'], 'resent', "$myName reviewed the dispute and reopened the payment.");
        notify((int) $s['to_user_id'], 'resent', "$myName reopened the disputed $amount payment for {$s['bill_name']}.", 'my-settlements?tab=owed');
        $pdo->commit();
        break;

    case 'record':
        // Creator who is also the receiver records a guest's cash payment in one step.
        if (!$fromGuest || !$isReceiver || !$isSender) {
            fail('Only the Bill Creator can record a guest’s payment they received.', 403);
        }
        $pdo->beginTransaction();
        transition($id, 'pending', 'settled', ', paid_at = NOW(), confirmed_at = NOW()');
        $guest = first_name($s['from_name']);
        log_event($id, $me['id'], 'marked_paid', "$myName recorded $amount received from guest $guest.");
        log_event($id, $me['id'], 'confirmed', "$myName confirmed receiving the payment. Settlement closed.");
        maybe_close_bill((int) $s['bill_id']);
        $pdo->commit();
        break;

    case 'nudge':
        // Receiver reminds the sender to pay; sender reminds the receiver to confirm.
        if ($fromGuest) {
            fail('Guests don’t have an account, so they can’t get reminders.', 409);
        }
        if ($isReceiver && $s['status'] === 'pending') {
            [$target, $msg] = [(int) $s['from_user_id'], "$myName sent a reminder: you owe $amount for {$s['bill_name']}."];
            $link = 'my-settlements';
        } elseif ($isSender && $s['status'] === 'awaiting') {
            [$target, $msg] = [(int) $s['to_user_id'], "$myName is waiting for you to confirm their $amount payment for {$s['bill_name']}."];
            $link = 'my-settlements?tab=owed';
        } else {
            fail('Nothing to remind about right now.', 409);
        }
        $recent = q(
            "SELECT 1 FROM settlement_events WHERE settlement_id = ? AND actor_id = ? AND event = 'nudged' AND created_at > NOW() - INTERVAL " . NUDGE_COOLDOWN_HOURS . ' HOUR',
            [$id, $me['id']]
        )->fetchColumn();
        if ($recent) {
            fail('You already sent a reminder recently. Try again later.', 429);
        }
        log_event($id, $me['id'], 'nudged', "$myName sent a reminder.");
        notify($target, 'nudge', $msg, $link);
        break;

    default:
        fail('Unknown action.', 404);
}

json_ok(['settlement' => settlement_row(load_settlement($id, $me))]);
