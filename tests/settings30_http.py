import json,urllib.request,urllib.error
from pathlib import Path
f=json.loads(Path('.settings30-fixture.json').read_text());base='http://127.0.0.1:8080/'
def get(pid,token):
 try:
  with urllib.request.urlopen(urllib.request.Request(base+'api-device.php?op=preview&playlist_id='+str(pid),headers={'Authorization':'Bearer '+token})) as r:return r.status,json.load(r)
 except urllib.error.HTTPError as e:return e.code,json.load(e)
s,v=get(f['playlist'],f['token']);assert s==200,(s,v)
assert v['preview_layout']=='bottom' and len(v['fallback']['items'])==1
assert get(f['private'],f['token'])[0]==404
assert get(f['playlist'],'bad')[0]==401
print('PREVIEW30_HTTP_PASS real shared playlist preview, private tenant and bearer rejection')
