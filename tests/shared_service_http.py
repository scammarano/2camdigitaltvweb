import hashlib,json,re,urllib.request,urllib.error,urllib.parse
from pathlib import Path

base='http://127.0.0.1:8080/'
f=json.loads(Path('.service-http-fixture.json').read_text())
cookie=json.loads(Path('.browser-session.json').read_text())['session']
def request(path,token=None,payload=None,form=False):
    headers={}
    if token:headers['Authorization']='Bearer '+token
    if form:headers['Cookie']='PHPSESSID='+cookie
    data=None
    if payload is not None:
        data=(urllib.parse.urlencode(payload) if form else json.dumps(payload)).encode()
        headers['Content-Type']='application/x-www-form-urlencoded' if form else 'application/json'
    req=urllib.request.Request(base+path,data=data,headers=headers)
    try:
        with urllib.request.urlopen(req) as r:return r.status,r.read()
    except urllib.error.HTTPError as e:return e.code,e.read()
def get_service(i=0):
    status,raw=request('api-device.php?op=service',f['tokens'][i]);assert status==200,raw
    return json.loads(raw)['service']
status,raw=request('device-media.php?id='+str(f['media']),f['tokens'][1]);assert status==200 and raw.startswith(b'\x89PNG'),(status,raw[:100])
assert request('device-media.php?id='+str(f['private']),f['tokens'][1])[0]==404
s=get_service()
def change(value,revision):
    status,body=request('devices.php',payload={'_csrf':'scope-fixture','action':'service','id':'1','tenant_id':'1','suspended':'1' if value else '0','service_revision':revision,'reason':'HTTP test'},form=True)
    assert status==200 and b'alert alert-danger' not in body,body[:500]
change(True,s['revision']);s=get_service();assert s['suspended'] and s['reported_revision']!=s['revision']
status,raw=request('api-device.php?op=manifest',f['tokens'][0]);m=json.loads(raw);assert status==200 and m['service']['suspended'] and not m['playback']['items']
assert request('device-media.php?id='+str(f['media']),f['tokens'][0])[0]==403
assert request('api-device.php?op=manifest',f['tokens'][1])[0]==200
assert request('api-device.php?op=service',f['tokens'][0],{'revision':s['revision'],'suspended':False})[0]==409
assert request('api-device.php?op=service',f['tokens'][0],{'revision':s['revision'],'suspended':True})[0]==200
assert get_service()['reported_revision']==s['revision']
change(False,s['revision']);s=get_service();assert not s['suspended']
status,raw=request('api-device.php?op=manifest',f['tokens'][0]);assert status==200 and json.loads(raw)['playback']['items']
print('SHARED_SERVICE_HTTP_PASS: authenticated shared bytes, private denial, administrator suspend/resume POST, empty manifest/403 media, independent screen and explicit ACK')
