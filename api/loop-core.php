<?php
require_once __DIR__ . '/db.php';
function loopState() {
    return readJson('loop.json') ?: ['materials'=>[], 'projects'=>[], 'contributions'=>[], 'stock'=>[], 'ledger'=>[], 'consumed'=>[]];
}
function demand($ok, $message) { if (!$ok) throw new RuntimeException($message); }
function shortText($value, $max=200) {
    demand(is_string($value) && strlen(trim($value))>0 && strlen($value)<=$max, 'Please complete the required text fields.');
    return trim($value);
}
function whole($value, $min=1, $max=10000) {
    demand(filter_var($value,FILTER_VALIDATE_INT)!==false && $value>=$min && $value<=$max, 'Enter a valid whole number.');
    return (int)$value;
}
function organiser($u) {
    foreach (readJson('users.json') as $record) if ($record['id']===$u['id']) return ($record['role']??'')==='organiser';
    return false;
}
function expireKits(&$s) {
    foreach ($s['projects'] as &$p) if (in_array($p['status'],['pending','reserved']) && $p['expires']<=time()) {
        $p['status']='expired'; $p['return_required']=array_values(array_filter($p['lines'],fn($l)=>$l['checked']));
    }
}
function balance($s, $id) {
    $total=0; $held=0;
    foreach ($s['ledger'] as $e) if ($e['user_id']===$id) $total+=$e['amount'];
    foreach ($s['projects'] as $p) if ($p['maker_id']===$id && in_array($p['status'],['pending','reserved']) && $p['expires']>time()) $held+=$p['total'];
    return ['available'=>$total-$held,'held'=>$held,'total'=>$total];
}
function entry(&$s, $id, $amount, $type, $ref, $label, $community) {
    $s['ledger'][]=['id'=>bin2hex(random_bytes(10)),'user_id'=>$id,'amount'=>$amount,'type'=>$type,'reference'=>$ref,'label'=>$label,'community'=>$community,'created_at'=>time()];
}
function inventory($s, $u) {
    $rows=[];
    foreach (readJson('items.json') as $item) {
        $m=$s['materials'][$item['id']]??null;
        if (!$m || !$m['enabled'] || $item['pincode']!==$u['pincode'] || legacyLocked($item['id'])) continue;
        $available=$m['quantity']-($s['consumed'][$item['id']]??0);
        foreach ($s['projects'] as $p) {
            foreach ($p['return_required'] ?? [] as $l) if ($l['item_id']===$item['id']) $available-=$l['quantity'];
            if (!in_array($p['status'],['pending','reserved']) || $p['expires']<=time()) continue;
            foreach ($p['lines'] as $l) if ($l['item_id']===$item['id']) $available-=$l['quantity'];
        }
        $rows[]=array_merge($item,$m,['available'=>max(0,$available)]);
    }
    return $rows;
}
function matchKit($s, $u, $requirements) {
    demand(is_array($requirements) && count($requirements)>0 && count($requirements)<=20,'Add between 1 and 20 materials.');
    $inventory=inventory($s,$u); $used=[]; $lines=[]; $results=[]; $total=0;
    usort($inventory,fn($a,$b)=>$a['credits']<=>$b['credits']);
    foreach ($requirements as $index=>$r) {
        $material=shortText($r['material']??'',60); $unit=shortText($r['unit']??'',20);
        $quantity=whole($r['quantity']??0); $width=whole($r['width']??0,0); $height=whole($r['height']??0,0);
        $condition=$r['condition']??'usable'; demand(in_array($condition,['usable','like-new']),'Choose a valid condition.');
        $owned=!empty($r['owned']); $remaining=$owned?0:$quantity;
        foreach ($inventory as $i) {
            if (!$remaining) break;
            if ($i['owner_id']===$u['id'] || strcasecmp($i['material'],$material)!==0 || $i['unit']!==$unit || $i['width']<$width || $i['height']<$height || ($condition==='like-new' && $i['condition']!=='like-new')) continue;
            $take=min($remaining,max(0,$i['available']-($used[$i['id']]??0))); if (!$take) continue;
            $used[$i['id']]=($used[$i['id']]??0)+$take; $remaining-=$take; $cost=$take*$i['credits']; $total+=$cost;
            $lines[]=['id'=>bin2hex(random_bytes(8)),'requirement'=>$index,'item_id'=>$i['id'],'title'=>$i['title'],'material'=>$material,'owner_id'=>$i['owner_id'],'owner'=>$i['owner_username'],'quantity'=>$take,'unit'=>$unit,'cost'=>$cost,'approved'=>false,'checked'=>false];
        }
        $results[]=['material'=>$material,'quantity'=>$quantity,'unit'=>$unit,'missing'=>$remaining,'owned'=>$owned,'status'=>$owned?'Already have':($remaining?'Missing':'Matched')];
    }
    $quoteKey=hash('sha256',json_encode(array_map(fn($l)=>[$l['requirement'],$l['item_id'],$l['owner_id'],$l['quantity'],$l['cost']],$lines)));
    return ['lines'=>$lines,'requirements'=>$results,'total'=>$total,'quote_key'=>$quoteKey,'complete'=>!array_filter($results,fn($r)=>$r['missing']>0)];
}
function loopAction(&$s, $u, $action, $in) {
    expireKits($s); $admin=organiser($u);
    if ($action==='material') {
        $id=$in['item_id']??''; $item=null;
        foreach (readJson('items.json') as $candidate) if ($candidate['id']===$id) $item=$candidate;
        demand($item && $item['owner_id']===$u['id'],'Only the owner can offer this material.');
        demand(!legacyLocked($id) && !projectLocked($id),'This listing has a reservation or completed transfer. Relist remaining materials.');
        $condition=$in['condition']??'usable'; demand(in_array($condition,['usable','like-new']),'Choose a valid condition.');
        $mode=$in['mode']??'credits'; demand(in_array($mode,['credits','gift']),'Choose credits or gift.');
        $s['materials'][$id]=['enabled'=>!empty($in['enabled']),'material'=>strtolower(shortText($in['material']??'',60)),'unit'=>shortText($in['unit']??'',20),'quantity'=>whole($in['quantity']??0),'width'=>whole($in['width']??0,0),'height'=>whole($in['height']??0,0),'credits'=>$mode==='gift'?0:whole($in['credits']??0),'condition'=>$condition,'mode'=>$mode];
    } elseif ($action==='match') return matchKit($s,$u,$in['requirements']??[]);
    elseif ($action==='request' || $action==='draft') {
        $name=shortText($in['name']??'',100); $description=shortText($in['description']??'',2000); $deadline=$in['deadline']??'';
        demand(preg_match('/^\d{4}-\d{2}-\d{2}$/',$deadline) && strtotime($deadline.' 23:59:59')>=time(),'Choose a future collection date.');
        $kit=matchKit($s,$u,$in['requirements']??[]);
        if ($action==='request') {
            demand($kit['complete'] && count($kit['lines'])>0,'Fill missing requirements before requesting a kit.');
            demand(balance($s,$u['id'])['available']>=$kit['total'],'You need more available credits. Contribute useful goods or complete an exchange first.');
            demand(isset($in['expected_total']) && (int)$in['expected_total']===$kit['total'],'Prices changed. Match again before requesting.');
            demand(($in['expected_quote']??'')===$kit['quote_key'],'Inventory changed. Match again and review the suppliers.');
        }
        $id=bin2hex(random_bytes(12));
        $s['projects'][$id]=array_merge($kit,['id'=>$id,'maker_id'=>$u['id'],'maker'=>$u['username'],'community'=>$u['pincode'],'name'=>$name,'description'=>$description,'raw_requirements'=>$in['requirements'],'deadline'=>$deadline,'expires'=>min(time()+172800,strtotime($deadline.' 23:59:59')),'status'=>$action==='draft'?'draft':'pending','created_at'=>time()]);
        return ['project_id'=>$id];
    } elseif ($action==='contribute') {
        $id=bin2hex(random_bytes(12));
        $s['contributions'][$id]=['id'=>$id,'owner_id'=>$u['id'],'owner'=>$u['username'],'community'=>$u['pincode'],'title'=>shortText($in['title']??'',100),'description'=>shortText($in['description']??'',1000),'value'=>whole($in['value']??0),'status'=>'pending'];
    } elseif ($action==='intake' || $action==='decline-intake') {
        demand($admin,'An organiser must check the goods.'); $id=$in['id']??''; $c=$s['contributions'][$id]??null;
        demand($c && $c['community']===$u['pincode'] && $c['status']==='pending','Contribution is no longer pending.');
        demand($c['owner_id']!==$u['id'],'Another organiser must review your contribution.');
        if ($action==='intake') {
            demand(!empty($in['confirmed']),'Confirm physical receipt, condition and agreed value.');
            $s['stock'][$id]=['id'=>$id,'title'=>$c['title'],'description'=>$c['description'],'value'=>$c['value'],'community'=>$c['community'],'status'=>'available'];
            entry($s,$c['owner_id'],$c['value'],'issue',$id,'Accepted: '.$c['title'],$u['pincode']);
        }
        $s['contributions'][$id]['status']=$action==='intake'?'accepted':'declined';
    } elseif ($action==='redeem-request') {
        $id=$in['id']??''; $item=$s['stock'][$id]??null;
        demand($item && $item['community']===$u['pincode'] && $item['status']==='available','This shelf item is unavailable.');
        demand(balance($s,$u['id'])['available']>=$item['value'],'You need more available credits.');
        $s['stock'][$id]['requested_by']=$u['id']; $s['stock'][$id]['requested_name']=$u['username']; $s['stock'][$id]['status']='requested';
    } elseif ($action==='redeem') {
        demand($admin,'An organiser must confirm the shelf handover.'); $id=$in['id']??''; $item=$s['stock'][$id]??null;
        demand($item && $item['community']===$u['pincode'] && $item['status']==='requested','Shelf request is unavailable.');
        demand(!empty($in['confirmed']),'Confirm the physical handover.');
        demand(balance($s,$item['requested_by'])['available']>=$item['value'],'The member no longer has enough available credits.');
        entry($s,$item['requested_by'],-$item['value'],'retire',$id,'Collected: '.$item['title'],$u['pincode']); $s['stock'][$id]['status']='redeemed';
    } elseif ($action==='release-stock') {
        $id=$in['id']??''; $item=$s['stock'][$id]??null;
        demand($item && $item['community']===$u['pincode'] && $item['status']==='requested' && ($admin || $item['requested_by']===$u['id']),'Cannot release this shelf request.');
        $s['stock'][$id]['status']='available'; unset($s['stock'][$id]['requested_by'],$s['stock'][$id]['requested_name']);
    } else {
        $id=$in['id']??''; demand(isset($s['projects'][$id]),'Project not found.'); $p=&$s['projects'][$id];
        demand($p['community']===$u['pincode'],'Project not found.');
        $maker=$p['maker_id']===$u['id']; $supplier=in_array($u['id'],array_column($p['lines'],'owner_id'));
        demand($maker || $supplier || $admin,'This project is private.');
        if ($action==='cancel') {
            demand($maker || $supplier,'Only a participant can cancel the kit.'); demand(in_array($p['status'],['pending','reserved','draft']),'This kit cannot be cancelled.');
            $p['status']='cancelled'; $p['return_required']=array_values(array_filter($p['lines'],fn($l)=>$l['checked']));
        } elseif ($action==='approve') {
            demand($supplier && $p['status']==='pending','No supplier approval is pending.');
            foreach ($p['lines'] as &$l) if ($l['owner_id']===$u['id']) $l['approved']=true;
            unset($l); if (!array_filter($p['lines'],fn($l)=>!$l['approved'])) $p['status']='reserved';
        } elseif ($action==='checkin') {
            demand($admin && $p['status']==='reserved','Every supplier must approve first.'); demand(!empty($in['confirmed']),'Confirm physical receipt and specification checks.'); $found=false;
            foreach ($p['lines'] as &$l) if ($l['id']===($in['line_id']??'')) { $l['checked']=true; $found=true; }
            unset($l); demand($found,'Material line not found.');
        } elseif ($action==='collect') {
            demand($maker,'Only the maker can confirm collection.');
            if (in_array($p['status'],['collected','finished'])) return ['already_settled'=>true];
            demand($p['status']==='reserved' && !array_filter($p['lines'],fn($l)=>!$l['checked']),'Wait until the entire kit passes inspection.'); demand(!empty($in['confirmed']),'Confirm receipt of the complete kit.');
            entry($s,$u['id'],-$p['total'],'transfer',$id,'Collected kit: '.$p['name'],$u['pincode']);
            foreach ($p['lines'] as $l) {
                entry($s,$l['owner_id'],$l['cost'],'transfer',$id,'Supplied: '.$l['title'],$u['pincode']);
                $s['consumed'][$l['item_id']]=($s['consumed'][$l['item_id']]??0)+$l['quantity'];
            }
            $p['status']='collected';
        } elseif ($action==='finish') {
            demand($maker && $p['status']==='collected','Collect this kit before recording completion.'); $p['outcome']=shortText($in['outcome']??'',2000); $p['status']='finished';
        } elseif ($action==='returned') {
            demand($admin && in_array($p['status'],['cancelled','expired']) && !empty($in['confirmed']),'Confirm all received materials were returned.'); $p['return_required']=[];
        } else throw new RuntimeException('Unknown action.');
    }
    return [];
}
