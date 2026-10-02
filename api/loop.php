<?php
session_start();
require_once __DIR__ . '/loop-core.php';
header('Content-Type: application/json'); header('Cache-Control: no-store');
if (!isset($_SESSION['user'])) { http_response_code(401); apiError('Please sign in.'); }
$u=$_SESSION['user']; $s=loopState(); expireKits($s);
try {
    $result=[];
    if ($_SERVER['REQUEST_METHOD']==='POST') {
        $input=json_decode(file_get_contents('php://input'),true); demand(is_array($input),'Invalid request.');
        $result=loopAction($s,$u,$_GET['action']??'',$input);
    }
    writeJson('loop.json',$s); $admin=organiser($u);
    $projects=array_values(array_filter($s['projects'],fn($p)=>$p['community']===$u['pincode'] && ($admin || $p['maker_id']===$u['id'] || in_array($u['id'],array_column($p['lines'],'owner_id')))));
    $contributions=array_values(array_filter($s['contributions'],fn($c)=>$c['community']===$u['pincode'] && ($admin || $c['owner_id']===$u['id'])));
    $stock=array_values(array_filter($s['stock'],fn($i)=>$i['community']===$u['pincode']));
    foreach ($stock as &$row) if (!$admin && ($row['requested_by']??'')!==$u['id']) unset($row['requested_by'],$row['requested_name']);
    unset($row);
    $ledger=array_values(array_filter($s['ledger'],fn($e)=>$e['user_id']===$u['id']));
    $circulation=array_sum(array_column(array_filter($s['ledger'],fn($e)=>$e['community']===$u['pincode']),'amount'));
    $coverage=array_sum(array_column(array_filter($stock,fn($i)=>$i['status']!=='redeemed'),'value'));
    $mine=array_values(array_filter(readJson('items.json'),fn($i)=>$i['owner_id']===$u['id']));
    foreach ($mine as &$i) $i['material']=$s['materials'][$i['id']]??null;
    echo json_encode(array_merge(['success'=>true,'user'=>$u,'organiser'=>$admin,'demo'=>getenv('ECOLOOP_DEMO')==='1','balance'=>balance($s,$u['id']),'inventory'=>inventory($s,$u),'my_items'=>$mine,'projects'=>$projects,'contributions'=>$contributions,'stock'=>$stock,'ledger'=>$ledger,'reconciliation'=>$admin?['circulation'=>$circulation,'coverage'=>$coverage]:null],$result));
} catch (RuntimeException $e) { http_response_code(422); echo json_encode(['success'=>false,'error'=>$e->getMessage()]); }
