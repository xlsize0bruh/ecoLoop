<?php
session_start();
require_once __DIR__ . '/db.php';
header('Content-Type: application/json');
if (getenv('ECOLOOP_DEMO') !== '1' || $_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(404); apiError('Not available.'); }
$input=json_decode(file_get_contents('php://input'),true);
foreach (readJson('users.json') as $u) if ($u['id']===($input['id']??'') && in_array($u['id'],['maker','asha','kabir','mira','organiser'])) {
    session_regenerate_id(true); $_SESSION['user']=['id'=>$u['id'],'username'=>$u['username'],'pincode'=>$u['pincode']];
    echo json_encode(['success'=>true]); exit;
}
apiError('Seed the demo community first.');
