"""Publish a standalone, permanently signed APK without changing the installed web version."""
import base64
import hashlib
import json
import os
from pathlib import Path
import re
import subprocess
import tempfile
import zipfile

def api(path):
    return json.loads(subprocess.check_output(['gh','api',path],text=True))

root=Path(__file__).resolve().parents[1]
bundle=root/'.apk-release-assets/0419'
meta=json.loads((bundle/'release.json').read_text())
source=meta['source_commit'];source_repo='scammarano/digital-signage-android';repo=os.environ['GH_REPO']
assert re.fullmatch('[a-f0-9]{40}',source)
assert meta['tag']=='apk-v0.4.19-debug'
run=api(f'repos/{source_repo}/actions/runs/{meta["source_run"]}')
assert run['head_sha']==source and run['name']=='Fire Sports direct LAN APK' and run['conclusion']=='success','Android source validation not successful'
jobs=api(f'repos/{source_repo}/actions/runs/{meta["source_run"]}/jobs')['jobs']
assert all(any(j['name']==f'build-test ({sdk})' and j['conclusion']=='success' for j in jobs) for sdk in [22,29]),'API22/API29 verification missing'
public=root/'.release-assets/update-public.txt'
der=subprocess.check_output(['openssl','pkey','-pubin','-in',str(public),'-outform','DER'])
assert hashlib.sha256(der).hexdigest()=='1c6623f7cd4ed5b1c18b7f3254e6e050a39c6bea8f2d7cd9faaecc56287a10f4'
with tempfile.TemporaryDirectory() as directory:
    out=Path(directory);apk=out/'2Cam_Digital_Signage_v0.4.19_DEBUG.apk'
    apk.write_bytes(base64.b64decode(''.join(p.read_text().strip() for p in sorted(bundle.glob('android-*.b64'))),validate=True))
    raw=(bundle/'manifest.json').read_bytes();signature=(bundle/'manifest.sig').read_bytes();manifest=json.loads(raw)
    (out/'manifest.json').write_bytes(raw);(out/'manifest.sig').write_bytes(base64.b64decode(signature))
    subprocess.run(['openssl','dgst','-sha256','-verify',str(public),'-signature',str(out/'manifest.sig'),str(out/'manifest.json')],check=True)
    assert manifest['source_commit']==source and manifest['source_repository']==source_repo
    assert manifest['version_code']==419 and manifest['version_name']=='0.4.19-debug' and manifest['min_sdk']==19
    assert manifest['package']=='com.digitalsignage.tv' and manifest['platform']=='android-tv' and manifest['channel']=='debug'
    assert manifest['sha256']==meta['sha256']==hashlib.sha256(apk.read_bytes()).hexdigest() and manifest['size']==apk.stat().st_size
    cert='5390caf27181bb1163bb165867be5a5a83e748899957f0e0f68606ed6282f47a'
    signer=Path(os.environ['ANDROID_HOME'])/'build-tools/35.0.0/apksigner'
    proof=subprocess.check_output([str(signer),'verify','--verbose','--print-certs','--min-sdk-version','19',str(apk)],text=True)
    assert re.findall(r'Signer #\d+ certificate SHA-256 digest: ([a-f0-9]{64})',proof)==[cert] and manifest['certificate_sha256']==cert
    assert 'Verified using v1 scheme (JAR signing): true' in proof
    envelope=out/'2CAM_APK_android-tv_v0.4.19-debug_RSA.zip'
    with zipfile.ZipFile(envelope,'w') as z:
        for name,data in [('manifest.json',raw),('manifest.sig',signature),('app.apk',apk.read_bytes())]:z.writestr(zipfile.ZipInfo(name),data)
    assert hashlib.sha256(envelope.read_bytes()).hexdigest()==meta['envelope_sha256']
    notes=out/'notes.md';notes.write_text(manifest['notes']+'\n\n'+meta['notes']+'\n\nAPK SHA256: '+meta['sha256'])
    existing=subprocess.run(['gh','api',f'repos/{repo}/releases/tags/{meta["tag"]}'],capture_output=True,text=True)
    if existing.returncode:
        subprocess.run(['gh','release','create',meta['tag'],str(apk),str(envelope),'--target',os.environ['GITHUB_SHA'],'--prerelease','--latest=false','--title','APK Signage0.4.19 · Instalador Fire OS y franja Sports20%','--notes-file',str(notes)],check=True)
    else:
        assets={a['name']:a for a in json.loads(existing.stdout)['assets']}
        for file in [apk,envelope]:
            assert file.name in assets and assets[file.name]['digest']=='sha256:'+hashlib.sha256(file.read_bytes()).hexdigest(),'Existing release differs: never overwrite'
    print('STANDALONE_APK_PUBLISHED',meta['tag'])
