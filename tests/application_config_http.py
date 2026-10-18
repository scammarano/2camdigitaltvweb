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
