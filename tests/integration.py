"""Run the actual PHP HTTP API against fresh, isolated JSON data (Python stdlib)."""
import http.cookiejar, json, os, pathlib, socket, subprocess, tempfile, time, urllib.request, urllib.parse, urllib.error
root=pathlib.Path(__file__).resolve().parents[1]
php=[os.environ.get('PHP_BIN','php')]
class Client:
    def __init__(self): self.http=urllib.request.build_opener(urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()))
    def call(self,path,data=None,form=False):
        body=None if data is None else (urllib.parse.urlencode(data).encode() if form else json.dumps(data).encode())
        req=urllib.request.Request(base+path,data=body,headers={'Content-Type':'application/x-www-form-urlencoded' if form else 'application/json'})
        try: response=self.http.open(req,timeout=10)
        except urllib.error.HTTPError as error: response=error
        return response.status,json.loads(response.read())
checks=0
def check(ok,label):
    global checks
    if not ok: raise AssertionError(label)
    checks+=1; print('PASS',label)
with tempfile.TemporaryDirectory(prefix='ecoloop-http-') as storage:
    env=os.environ.copy();env.update(ECOLOOP_DATA_DIR=storage,ECOLOOP_DEMO='1')
    subprocess.run(php+['manage.php','demo'],cwd=root,env=env,check=True,capture_output=True)
    sock=socket.socket();sock.bind(('127.0.0.1',0));port=sock.getsockname()[1];sock.close()
    base=f'http://127.0.0.1:{port}'
    server=subprocess.Popen(php+['-S',f'127.0.0.1:{port}','-t',str(root),str(root/'router.php')],cwd=root,env=env,stdout=subprocess.DEVNULL,stderr=subprocess.DEVNULL)
    try:
        for _ in range(50):
            try: urllib.request.urlopen(base+'/login.php',timeout=.3);break
            except OSError: time.sleep(.1)
        anon=Client(); check(anon.call('/api/loop.php', {'action': 'test'})[0]==401,'Authentication required')
        clients={role:Client() for role in ['maker','asha','kabir','mira','organiser']}
        for role,client in clients.items(): check(client.call('/api/demo.php',{'id':role})[1]['success'],'Demo role '+role)
        a=clients['asha']; b=clients['kabir']; outsider=clients['mira']
        def create(c,title):
            return c.call('/api/items.php?action=create',{'title':title,'description':'A usable test item','tags':'test','looking_for_tags':'books'},True)[1]['item']['id']
        offer=create(a,'Spare notebook'); wanted=create(b,'Unused pencil case')
        check(not outsider.call('/api/trades.php?action=propose',{'offered_item_id':offer,'wanted_item_id':wanted})[1]['success'],'Cannot offer another member\'s listing')
        trade=a.call('/api/trades.php?action=propose',{'offered_item_id':offer,'wanted_item_id':wanted})[1]
        check(trade['success'],'Original barter request works');tid=trade['trade']['id']
        check(not outsider.call('/api/messages.php?action=list&trade_id='+tid)[1]['success'],'Private trade chat remains private')
        check(a.call('/api/messages.php?action=send',{'trade_id':tid,'text':'Meet at the exchange desk'})[1]['success'],'Participants can send trade messages')
        check(len(b.call('/api/messages.php?action=list&trade_id='+tid)[1]['messages'])==1,'Recipient receives message')
        check(b.call('/api/trades.php?action=accept',{'trade_id':tid})[1]['success'],'Original trade acceptance works')
        check(not outsider.call('/api/trades.php?action=complete',{'trade_id':tid,'rating':1})[1]['success'],'Outsider cannot complete or rate trade')
        check(a.call('/api/trades.php?action=complete',{'trade_id':tid,'rating':1})[1]['success'],'First handover confirmed')
        a.call('/api/trades.php?action=complete',{'trade_id':tid,'rating':1})
        check(b.call('/api/users.php?action=profile')[1]['profile']['positive_reviews']==1,'Repeated completion cannot duplicate ratings')
        check(b.call('/api/trades.php?action=complete',{'trade_id':tid,'rating':1})[1]['success'],'Second handover completes barter')
        market=a.call('/api/items.php?action=list')[1]['items'];check(next(i for i in market if i['id']==wanted)['is_locked'],'Transferred items cannot be offered twice')
        maker=clients['maker'];admin=clients['organiser']
        for c in admin.call('/api/loop.php')[1]['contributions']:
            check(admin.call('/api/loop.php?action=intake',{'id':c['id'],'confirmed':True})[1]['success'],'HTTP stock intake')
        payload={'name':'Desktop organiser','description':'HTTP integration project','deadline':time.strftime('%Y-%m-%d',time.localtime(time.time()+604800)),'requirements':[{'material':'cardboard','quantity':1,'unit':'pack','width':30,'height':40},{'material':'tubes','quantity':3,'unit':'piece','width':3,'height':10},{'material':'fabric and string','quantity':1,'unit':'pack','width':20,'height':20}]}
        q=maker.call('/api/loop.php?action=match',payload)[1];check(q['complete'] and q['total']==60,'HTTP matches real marketplace inventory')
        result=maker.call('/api/loop.php?action=request',payload|{'expected_total':q['total'],'expected_quote':q['quote_key']})[1];check(result['success'],'HTTP kit request');pid=result['project_id']
        check(not a.call('/api/items.php?action=delete',{'item_id':'card'})[1]['success'],'Cannot delete project-reserved listing')
        check(not a.call('/api/trades.php?action=propose',{'offered_item_id':'card','wanted_item_id':create(b,'Extra book')})[1]['success'],'Barter cannot take project-reserved stock')
        for role in ['asha','kabir','mira']:check(clients[role].call('/api/loop.php?action=approve',{'id':pid})[1]['success'],'HTTP owner approval '+role)
        p=next(p for p in admin.call('/api/loop.php')[1]['projects'] if p['id']==pid)
        for line in p['lines']:check(admin.call('/api/loop.php?action=checkin',{'id':pid,'line_id':line['id'],'confirmed':True})[1]['success'],'HTTP material check-in')
        check(maker.call('/api/loop.php?action=collect',{'id':pid,'confirmed':True})[1]['success'],'HTTP complete kit settlement')
        check(a.call('/api/loop.php')[1]['balance']['available']==20,'Supplier balance visible in independent session')
        for role,item in [('asha','geometry'),('kabir','sketch'),('mira','novel')]:
            check(clients[role].call('/api/loop.php?action=redeem-request',{'id':item})[1]['success'],'Supplier redemption '+role)
            check(admin.call('/api/loop.php?action=redeem',{'id':item,'confirmed':True})[1]['success'],'Organiser shelf handover '+role)
        r=admin.call('/api/loop.php')[1]['reconciliation'];check(r=={'circulation':0,'coverage':0},'HTTP stock and credit reconciliation returns to zero')
        check(not a.call('/api/demo.php',{'id':'not-a-demo-user'})[1]['success'],'Demo roles cannot be forged')
        new=Client();check(new.call('/api/auth.php?action=register',{'username':'HTTP member','password':'test-only-password-1024','pincode':'700001'})[1]['success'],'Original account registration works')
        check(not new.call('/api/loop.php')[1]['organiser'],'Public registration cannot create organisers')
        for path in ['/data/users.json','/api/db.php','/manage.php','/.git/config','/tests/workflows.php']:
            try: response=urllib.request.urlopen(base+path);status=response.status
            except urllib.error.HTTPError as error: status=error.code
            check(status==404,'Private path blocked '+path)
        print(f'\n{checks} HTTP checks passed.')
    finally:
        server.terminate();server.wait(timeout=10)

