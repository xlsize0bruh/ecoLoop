<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/api/loop-core.php';
$command=$argv[1]??'';
if ($command==='demo') {
    demand(getenv('ECOLOOP_DEMO')==='1','Set ECOLOOP_DEMO=1 to seed the isolated demo database.');
    demand(!readJson('users.json'),'Demo already contains accounts. Use a new data directory for a fresh run.');
    $users=[];
    foreach (['maker'=>'Pratyush','asha'=>'Asha','kabir'=>'Kabir','mira'=>'Mira','organiser'=>'Community organiser'] as $id=>$name) $users[]=['id'=>$id,'username'=>$name,'password'=>password_hash(bin2hex(random_bytes(20)),PASSWORD_DEFAULT),'pincode'=>'700001','role'=>$id==='organiser'?'organiser':'member','positive_reviews'=>0,'negative_reviews'=>0];
    writeJson('users.json',$users); $s=loopState(); $items=[];
    foreach ([['card','asha','Asha','Three cardboard sheets','cardboard','pack',1,20,30,40],['tube','kabir','Kabir','Cardboard tubes','tubes','piece',3,5,3,12],['fabric','mira','Mira','Fabric & string bundle','fabric and string','pack',1,25,25,30]] as [$id,$owner,$name,$title,$type,$unit,$qty,$credits,$w,$h]) {
        $items[]=['id'=>$id,'owner_id'=>$owner,'owner_username'=>$name,'pincode'=>'700001','title'=>$title,'description'=>'Demo material. Clean and usable; dimensions checked for the sample project.','tags'=>[$type],'looking_for_tags'=>['books','stationery'],'image'=>'','created_at'=>time()];
        $s['materials'][$id]=['enabled'=>true,'material'=>$type,'unit'=>$unit,'quantity'=>$qty,'credits'=>$credits,'width'=>$w,'height'=>$h,'condition'=>'usable','mode'=>'credits'];
    }
    foreach ([['geometry','Geometry box',20],['sketch','Unused sketch pad',15],['novel','Readable novel',25]] as [$id,$title,$value]) $s['contributions'][$id]=['id'=>$id,'owner_id'=>'maker','owner'=>'Pratyush','community'=>'700001','title'=>$title,'description'=>'Demo contribution, awaiting physical acceptance.','value'=>$value,'status'=>'pending'];
    writeJson('items.json',$items); writeJson('loop.json',$s);
    echo "Demo created. Open login.php and choose Enter demo. All initial credits are zero.\n";
} elseif ($command==='organiser') {
    $username=$argv[2]??''; demand($username!=='','Usage: php manage.php organiser username');
    $users=readJson('users.json'); $found=false;
    foreach ($users as &$u) if ($u['username']===$username) {$u['role']='organiser';$found=true;}
    demand($found,'Register this account first.'); writeJson('users.json',$users); echo "Organiser role granted.\n";
} else echo "Commands: demo | organiser username\n";
