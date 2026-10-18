import json,urllib.request,urllib.error
from pathlib import Path
base='http://127.0.0.1:8080/'
f=json.loads(Path('.application-http-fixture.json').read_text())
def get(path,token):
 try:
  with urllib.request.urlopen(urllib.request.Request(base+path,headers={'Authorization':'Bearer '+token})) as r:return r.status,json.load(r)
 except urllib.error.HTTPError as e:return e.code,json.load(e)
status,body=get('api-application-config.php?app=turnos',f['token']);assert status==200,(status,body)
assert body['tenant_id']==1 and body['application']=='turnos' and body['revision']>0
assert 'Sala ajena 29' not in json.dumps(body) and 'token_hash' not in json.dumps(body)
assert get('api-application-config.php?app=sports',f['token'])[0]==401
assert get('api-application-config.php?app=turnos','bad')[0]==401
print('APPLICATION_CONFIG_HTTP_PASS: real bearer access, tenant/application scoping, invalid token rejection')
# A display receives only its bound public scope, never operators or other screens.
sf=json.loads(Path('.service-http-fixture.json').read_text())
status,screen=get('api-application-config.php?app=turnos',sf['tokens'][0]);assert status==200,(status,screen)
assert all(e['kind'] not in ('operator','control','kiosk') and 'user_id' not in e for e in screen['entities'])
assert get('api-application-config.php?app=turnos',sf['tokens'][1])[0]==422
# Revoke using the real CSRF-protected portal action.
import urllib.parse
session=json.loads(Path('.browser-session.json').read_text())['session']
data=urllib.parse.urlencode({'_csrf':'scope-fixture','action':'revoke','tenant_id':'1','id':f['id']}).encode()
with urllib.request.urlopen(urllib.request.Request(base+'applications.php?app=turnos',data=data,headers={'Cookie':'PHPSESSID='+session})) as r:
 assert r.status==200 and b'alert alert-danger' not in r.read()
assert get('api-application-config.php?app=turnos',f['token'])[0]==401
print('APPLICATION_CONFIG_SCOPE_REVOKE_PASS: bound screen, no operator directory, unbound screen blocked, real portal revocation')
