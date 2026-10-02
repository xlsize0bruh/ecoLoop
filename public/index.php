<?php
declare(strict_types=1);
/* PHP front controller. Python owns all business logic and JSON writes. */
const MAX_BODY_BYTES = 3000000;
function unavailable(string $message, int $status = 503): never {
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    header('X-Content-Type-Options: nosniff');
    echo json_encode(['error' => $message]);
    exit;
}
if (!function_exists('proc_open')) {
    unavailable('The PHP gateway requires Python and proc_open. Configure a Python website or enable the gateway requirements.');
}
$root = dirname(__DIR__);
$config = is_file($root . '/php-config.php') ? require $root . '/php-config.php' : [];
$python = $config['python_binary'] ?? (getenv('PYTHON_BINARY') ?: 'python3');
if ((int)($_SERVER['CONTENT_LENGTH'] ?? 0) > MAX_BODY_BYTES) {
    unavailable('This upload is too large. Use a photo smaller than 2 MB.', 413);
}
$body = file_get_contents('php://input', false, null, 0, MAX_BODY_BYTES + 1);
if ($body === false || strlen($body) > MAX_BODY_BYTES) {
    unavailable('Request too large.', 413);
}
$headers = [];
foreach (['cookie', 'origin', 'host', 'x-csrf-token', 'sec-fetch-site'] as $name) {
    $headers[$name] = $_SERVER['HTTP_' . strtoupper(str_replace('-', '_', $name))] ?? '';
}
$request = json_encode([
    'method' => $_SERVER['REQUEST_METHOD'] ?? 'GET',
    'uri' => $_SERVER['REQUEST_URI'] ?? '/',
    'content_type' => $_SERVER['CONTENT_TYPE'] ?? '',
    'remote_addr' => $_SERVER['REMOTE_ADDR'] ?? '',
    'scheme' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https' : 'http',
    'headers' => $headers,
    'body' => base64_encode($body),
], JSON_THROW_ON_ERROR);
// An argument array avoids the shell. No HTTP input becomes a command or argument.
$process = proc_open([$python, $root . '/bridge.py'], [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, $root);
if (!is_resource($process)) {
    unavailable('Unable to start the Python application. Check the PHP gateway configuration.');
}
$offset = 0;
while ($offset < strlen($request)) {
    $written = fwrite($pipes[0], substr($request, $offset));
    if ($written === false || $written === 0) {
        break;
    }
    $offset += $written;
}
fclose($pipes[0]);
$output = stream_get_contents($pipes[1]);
fclose($pipes[1]);
$error = stream_get_contents($pipes[2]);
fclose($pipes[2]);
$exit = proc_close($process);
$response = json_decode($output ?: '', true);
if ($exit !== 0 || !is_array($response) || !isset($response['status'], $response['headers'], $response['body'])) {
    error_log('EcoLoop bridge: ' . substr($error ?: 'Invalid response', 0, 2000));
    unavailable('EcoLoop could not start. Check Python and the storage permissions in the server logs.');
}
http_response_code((int)$response['status']);
foreach ($response['headers'] as [$name, $value]) {
    header($name . ': ' . $value, false);
}
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'HEAD') {
    echo base64_decode($response['body'], true);
}
