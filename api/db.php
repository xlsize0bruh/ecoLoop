<?php
// Keep EcoTrade's document API; commit every request in one SQLite transaction.
$storage = getenv('ECOLOOP_DATA_DIR') ?: dirname(__DIR__, 2) . '/ecoloop-storage';
if (!is_dir($storage)) mkdir($storage, 0700, true);
$db = new PDO('sqlite:' . $storage . (getenv('ECOLOOP_DEMO') === '1' ? '/demo.sqlite' : '/community.sqlite'));
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$db->exec('PRAGMA busy_timeout=10000');
$db->exec('CREATE TABLE IF NOT EXISTS documents (name TEXT PRIMARY KEY, body TEXT NOT NULL)');
$db->exec('BEGIN IMMEDIATE');
register_shutdown_function(function () use ($db) {
    $error = error_get_last();
    $failed = $error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR]);
    $db->exec($failed ? 'ROLLBACK' : 'COMMIT');
});
function readJson($filename) {
    global $db;
    $query = $db->prepare('SELECT body FROM documents WHERE name = ?');
    $query->execute([$filename]); $body = $query->fetchColumn();
    return $body === false ? [] : json_decode($body, true, 512, JSON_THROW_ON_ERROR);
}
function writeJson($filename, $data) {
    global $db;
    $query = $db->prepare('INSERT INTO documents (name,body) VALUES (?,?) ON CONFLICT(name) DO UPDATE SET body=excluded.body');
    $query->execute([$filename, json_encode($data, JSON_THROW_ON_ERROR)]);
}
function apiError($message) { echo json_encode(['success'=>false,'error'=>$message]); exit; }
function projectLocked($id) {
    $state = readJson('loop.json');
    if (!empty($state['consumed'][$id])) return true;
    foreach ($state['projects'] ?? [] as $p) {
        foreach ($p['return_required'] ?? [] as $l) if ($l['item_id'] === $id) return true;
        if (in_array($p['status'], ['pending','reserved']) && $p['expires'] <= time()) {
            foreach ($p['lines'] as $l) if ($l['item_id'] === $id && $l['checked']) return true;
        }
        if (!in_array($p['status'], ['pending','reserved']) || $p['expires'] < time()) continue;
        foreach ($p['lines'] as $l) if ($l['item_id'] === $id) return true;
    }
    return false;
}
function legacyLocked($id) {
    foreach (readJson('trades.json') as $t) if ($t['status'] !== 'cancelled' && in_array($id, [$t['wanted_item_id'],$t['offered_item_id']])) return true;
    foreach (readJson('borrows.json') as $b) if (in_array($b['status'],['pending','accepted']) && $b['item_id'] === $id) return true;
    return false;
}
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    if (($_SERVER['HTTP_SEC_FETCH_SITE'] ?? '') === 'cross-site') apiError('Cross-site request refused.');
    if (isset($_SERVER['HTTP_ORIGIN'])) {
        $o=parse_url($_SERVER['HTTP_ORIGIN']); $host=($o['host']??'').(isset($o['port'])?':'.$o['port']:'');
        if (strcasecmp($host,$_SERVER['HTTP_HOST']??'')!==0) apiError('Cross-site request refused.');
    }
}
