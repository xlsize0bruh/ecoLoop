<?php
// Keep EcoTrade's original JSON-file storage model. Demo data is isolated from
// ordinary community data, and one lock is held for the complete request so two
// simultaneous writes cannot read the same old state and overwrite each other.
$storage = getenv('ECOLOOP_DATA_DIR') ?: __DIR__ . '/../data';
if (getenv('ECOLOOP_DEMO') === '1') $storage .= '/demo';
if (!is_dir($storage) && !mkdir($storage, 0700, true) && !is_dir($storage)) {
    throw new RuntimeException('Unable to create the EcoLoop data directory.');
}
$storage = realpath($storage);
$storageLock = fopen($storage . '/.lock', 'c');
if (!$storageLock || !flock($storageLock, LOCK_EX)) {
    throw new RuntimeException('Unable to lock the EcoLoop data directory.');
}
register_shutdown_function(function () use ($storageLock) {
    flock($storageLock, LOCK_UN);
    fclose($storageLock);
});
function dataPath($filename) {
    global $storage;
    if (!is_string($filename) || !preg_match('/^[A-Za-z0-9_-]+\.json$/', $filename)) {
        throw new InvalidArgumentException('Invalid data filename.');
    }
    return $storage . DIRECTORY_SEPARATOR . $filename;
}
function readJson($filename) {
    $path = dataPath($filename);
    if (!is_file($path)) return [];
    $body = file_get_contents($path);
    if ($body === false || trim($body) === '') return [];
    return json_decode($body, true, 512, JSON_THROW_ON_ERROR);
}
function writeJson($filename, $data) {
    $path = dataPath($filename);
    $temporary = tempnam(dirname($path), '.write-');
    if ($temporary === false) throw new RuntimeException('Unable to create a temporary data file.');
    $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n";
    if (file_put_contents($temporary, $json) === false) {
        @unlink($temporary);
        throw new RuntimeException('Unable to write EcoLoop data.');
    }
    if (!@rename($temporary, $path)) {
        // Windows cannot always replace an existing file with rename(). The
        // request-wide lock still prevents other requests from seeing this gap.
        @unlink($path);
        if (!rename($temporary, $path)) {
            @unlink($temporary);
            throw new RuntimeException('Unable to replace EcoLoop data.');
        }
    }
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

