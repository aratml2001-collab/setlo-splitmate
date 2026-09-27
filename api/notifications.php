<?php
require __DIR__ . '/../includes/api.php';

$me = api_user();

if (method() === 'GET') {
    $limit = min(50, max(1, (int) ($_GET['limit'] ?? 30)));
    $rows = q("SELECT id, type, message, link, is_read, created_at FROM notifications WHERE user_id = ? ORDER BY created_at DESC, id DESC LIMIT $limit", [$me['id']])->fetchAll();
    $unread = (int) q('SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0', [$me['id']])->fetchColumn();
    foreach ($rows as &$r) {
        $r['id'] = (int) $r['id'];
        $r['is_read'] = (bool) $r['is_read'];
    }
    json_ok(['notifications' => $rows, 'unread' => $unread]);
}

if (method() !== 'POST') {
    fail('Method not allowed.', 405);
}

switch (input('action', '')) {
    case 'read_all':
        q('UPDATE notifications SET is_read = 1 WHERE user_id = ?', [$me['id']]);
        json_ok();
    case 'read':
        q('UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?', [int_param('id'), $me['id']]);
        json_ok();
}
fail('Unknown action.', 404);
