<?php
// Isolated domain regression tests. Never read live community data.
putenv('ECOLOOP_DATA_DIR='.sys_get_temp_dir().'/ecoloop-test-'.bin2hex(random_bytes(8)));
putenv('ECOLOOP_DEMO=0');
require_once __DIR__.'/../api/loop-core.php';
$checks=0;
function check($ok,$label) { global $checks; if (!$ok) throw new RuntimeException('FAILED: '.$label); $checks++; echo "PASS $label\n"; }
function rejects($fn,$label) { try {$fn();} catch (RuntimeException $e) {check(true,$label);return;} check(false,$label); }
function fixture() {
    $users=[]; foreach (['maker','asha','kabir','mira','other','admin','remote'] as $id) $users[$id]=['id'=>$id,'username'=>$id,'pincode'=>$id==='remote'?'999999':'700001','role'=>$id==='admin'?'organiser':'member'];
    writeJson('users.json',array_values($users));writeJson('trades.json',[]);writeJson('loop.json',[]);
    $s=loopState();$items=[];$requirements=[];
    foreach ([['card','asha','cardboard','pack',1,20,30,40],['tube','kabir','tubes','piece',3,5,3,12],['fabric','mira','fabric and string','pack',1,25,25,30]] as [$id,$owner,$type,$unit,$qty,$cost,$w,$h]) {
        $items[]=['id'=>$id,'owner_id'=>$owner,'owner_username'=>$owner,'pincode'=>'700001','title'=>$type];
        $s['materials'][$id]=['enabled'=>true,'material'=>$type,'unit'=>$unit,'quantity'=>$qty,'credits'=>$cost,'width'=>$w,'height'=>$h,'condition'=>'usable','mode'=>'credits'];
        $requirements[]=['material'=>$type,'unit'=>$unit,'quantity'=>$qty,'width'=>$w,'height'=>$h];
    }
    writeJson('items.json',$items);
    return [$s,$users,['name'=>'Desktop organiser','description'=>'Test organiser','deadline'=>date('Y-m-d',time()+604800),'requirements'=>$requirements]];
}
function request(&$s,$u,$in) {$q=matchKit($s,$u,$in['requirements']);return loopAction($s,$u,'request',$in+['expected_total'=>$q['total'],'expected_quote'=>$q['quote_key']])['project_id'];}
function fund(&$s,$u) {
    foreach ([20,15,25] as $v) loopAction($s,$u['maker'],'contribute',['title'=>'Useful item '.$v,'description'=>'Verified condition','value'=>$v]);
    foreach ($s['contributions'] as $c) loopAction($s,$u['admin'],'intake',['id'=>$c['id'],'confirmed'=>true]);
}
[$s,$u,$input]=fixture();
$q=matchKit($s,$u['maker'],$input['requirements']);
check($q['complete'] && $q['total']===60 && count($q['lines'])===3,'60-credit kit from three owners');
check(balance($s,'maker')['available']===0,'Listing items never awards credits');
rejects(fn()=>request($s,$u['maker'],$input),'Insufficient funds blocked');
$bad=$input['requirements'];$bad[0]['width']=300;
check(!matchKit($s,$u['maker'],$bad)['complete'],'Incompatible dimensions stay missing');
$bad=$input['requirements'];$bad[0]['unit']='piece';
check(!matchKit($s,$u['maker'],$bad)['complete'],'Incompatible units stay missing');
$bad=$input['requirements'];$bad[0]['condition']='like-new';
check(!matchKit($s,$u['maker'],$bad)['complete'],'Condition requirements enforced');
check(!matchKit($s,$u['remote'],$input['requirements'])['complete'],'Community isolation');
check(!matchKit($s,$u['asha'],$input['requirements'])['complete'],'Cannot earn by supplying yourself');
$bad=$input['requirements'];$bad[]=$bad[0];
check(!matchKit($s,$u['maker'],$bad)['complete'],'Duplicate requirements cannot count stock twice');
$bad=$input['requirements'];$bad[0]['owned']=true;
check(matchKit($s,$u['maker'],$bad)['total']===40,'Already owned materials excluded from cost');
loopAction($s,$u['maker'],'contribute',['title'=>'Book','description'=>'Complete','value'=>60]);
$cid=array_key_first($s['contributions']);
check(balance($s,'maker')['total']===0,'Pending contribution earns no credits');
rejects(fn()=>loopAction($s,$u['maker'],'intake',['id'=>$cid,'confirmed'=>true]),'Member cannot issue own credits');
rejects(fn()=>loopAction($s,$u['admin'],'intake',['id'=>$cid]),'Physical intake confirmation required');
loopAction($s,$u['admin'],'intake',['id'=>$cid,'confirmed'=>true]);
check(balance($s,'maker')['available']===60,'Accepted useful goods fund maker');
rejects(fn()=>loopAction($s,$u['admin'],'intake',['id'=>$cid,'confirmed'=>true]),'Repeated intake cannot mint twice');
rejects(fn()=>loopAction($s,$u['other'],'material',['item_id'=>'card']),'Only listing owner may configure materials');
$stale=$input+['expected_total'=>60,'expected_quote'=>'incorrect'];
rejects(fn()=>loopAction($s,$u['maker'],'request',$stale),'Stale quote rejected');
$id=request($s,$u['maker'],$input);writeJson('loop.json',$s);
check(balance($s,'maker')['held']===60 && balance($s,'maker')['available']===0,'Request holds exact cost');
check(!matchKit($s,$u['other'],$input['requirements'])['complete'],'Another maker cannot reserve held stock');
check(projectLocked('card'),'Original barter sees project reservations');
rejects(fn()=>loopAction($s,$u['other'],'approve',['id'=>$id]),'Unrelated user cannot approve');
rejects(fn()=>loopAction($s,$u['remote'],'cancel',['id'=>$id]),'Cross-community mutations refused');
rejects(fn()=>loopAction($s,$u['maker'],'collect',['id'=>$id,'confirmed'=>true]),'Collection requires all approvals and checks');
foreach (['asha','kabir','mira'] as $owner) loopAction($s,$u[$owner],'approve',['id'=>$id]);
check($s['projects'][$id]['status']==='reserved','All supplier approvals required');
$line=$s['projects'][$id]['lines'][0];
rejects(fn()=>loopAction($s,$u['maker'],'checkin',['id'=>$id,'line_id'=>$line['id'],'confirmed'=>true]),'Maker cannot impersonate collection desk');
loopAction($s,$u['admin'],'checkin',['id'=>$id,'line_id'=>$line['id'],'confirmed'=>true]);
loopAction($s,$u['asha'],'cancel',['id'=>$id]);writeJson('loop.json',$s);
check(balance($s,'maker')['available']===60 && balance($s,'maker')['held']===0,'Cancellation restores available credits');
check(count($s['projects'][$id]['return_required'])===1 && projectLocked('card'),'Received goods remain locked until returned');
check(!matchKit($s,$u['maker'],$input['requirements'])['complete'],'Cannot rematch physically held cancelled goods');
loopAction($s,$u['admin'],'returned',['id'=>$id,'confirmed'=>true]);writeJson('loop.json',$s);
check(matchKit($s,$u['maker'],$input['requirements'])['complete'],'Returned goods become available');
$id=request($s,$u['maker'],$input);
$s['projects'][$id]['expires']=time()-1;expireKits($s);
check($s['projects'][$id]['status']==='expired' && balance($s,'maker')['held']===0,'Expiry releases held credit');
check(matchKit($s,$u['maker'],$input['requirements'])['complete'],'Expiry releases undelivered stock');
[$s,$u,$input]=fixture();fund($s,$u);$id=request($s,$u['maker'],$input);
foreach (['asha','kabir','mira'] as $owner) loopAction($s,$u[$owner],'approve',['id'=>$id]);
foreach ($s['projects'][$id]['lines'] as $line) loopAction($s,$u['admin'],'checkin',['id'=>$id,'line_id'=>$line['id'],'confirmed'=>true]);
loopAction($s,$u['maker'],'collect',['id'=>$id,'confirmed'=>true]);writeJson('loop.json',$s);
check(balance($s,'maker')['total']===0,'Maker debited exactly once');
check(balance($s,'asha')['available']===20 && balance($s,'kabir')['available']===15 && balance($s,'mira')['available']===25,'Suppliers receive 20 / 15 / 25');
$count=count($s['ledger']);loopAction($s,$u['maker'],'collect',['id'=>$id,'confirmed'=>true]);
check(count($s['ledger'])===$count,'Collection retries are idempotent');
check(array_sum(array_column(array_filter($s['ledger'],fn($e)=>$e['type']==='transfer'),'amount'))===0,'Member transfers conserve credits');
check(!matchKit($s,$u['other'],$input['requirements'])['complete'] && projectLocked('card'),'Consumed materials cannot be reused or bartered');
foreach ($s['stock'] as $stock) {
    $owner=[20=>'asha',15=>'kabir',25=>'mira'][$stock['value']];
    loopAction($s,$u[$owner],'redeem-request',['id'=>$stock['id']]);
    rejects(fn()=>loopAction($s,$u[$owner],'redeem',['id'=>$stock['id'],'confirmed'=>true]),'Member cannot self-confirm shelf handover');
    loopAction($s,$u['admin'],'redeem',['id'=>$stock['id'],'confirmed'=>true]);
    rejects(fn()=>loopAction($s,$u['admin'],'redeem',['id'=>$stock['id'],'confirmed'=>true]),'Redemption cannot retire twice');
}
check(array_sum(array_column($s['ledger'],'amount'))===0,'Full shelf redemption retires all issued credits');
loopAction($s,$u['maker'],'finish',['id'=>$id,'outcome'=>'Built a useful organiser.']);
check($s['projects'][$id]['status']==='finished' && balance($s,'maker')['total']===0,'Completion records outcome without minting credits');
[$s,$u,$input]=fixture();$s['materials']['card']['mode']='gift';$s['materials']['card']['credits']=0;
check(matchKit($s,$u['maker'],$input['requirements'])['total']===40,'Voluntary gifts cost zero');
writeJson('trades.json',[['wanted_item_id'=>'card','offered_item_id'=>'unrelated','status'=>'pending']]);
check(!matchKit($s,$u['maker'],$input['requirements'])['complete'],'Original trade reservations exclude project matches');
echo "\n$checks checks passed.\n";

