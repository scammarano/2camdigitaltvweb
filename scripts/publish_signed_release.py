"""Publish only a pre-signed package whose source commit already passed CI.

Private signing keys are never read by this workflow or stored in the repository.
"""
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
    return json.loads(subprocess.check_output(['gh', 'api', path], text=True))

root = Path(__file__).resolve().parents[1]
bundle = root / '.release-assets'
meta = json.loads((bundle / 'release.json').read_text())
repo = os.environ['GH_REPO']
source = meta['source_commit']
assert re.fullmatch(r'[a-f0-9]{40}', source)
assert re.fullmatch(r'web-v0\.4\.(20|21|22)', meta['tag'])
version = meta['tag'][5:]
runs = api(f'repos/{repo}/actions/runs?head_sha={source}&per_page=30')['workflow_runs']
assert any(r['name'] == 'Web portal CI' and r['conclusion'] == 'success' for r in runs), 'Source CI has not passed'
with tempfile.TemporaryDirectory() as temporary:
    out = Path(temporary)
    package = out / meta['asset']
    assert re.fullmatch(r'SIGNAGE_UPDATE_v' + re.escape(version) + r'_[A-Z0-9_]+_RSA\.zip', package.name)
    package.write_bytes(base64.b64decode((bundle / 'web-update.b64').read_bytes(), validate=False))
    assert hashlib.sha256(package.read_bytes()).hexdigest() == meta['sha256']
    pub = bundle / 'update-public.txt'
    der = subprocess.check_output(['openssl', 'pkey', '-pubin', '-in', str(pub), '-outform', 'DER'])
    assert hashlib.sha256(der).hexdigest() == '1c6623f7cd4ed5b1c18b7f3254e6e050a39c6bea8f2d7cd9faaecc56287a10f4'
    with zipfile.ZipFile(package) as archive:
        raw = archive.read('manifest.json')
        manifest = json.loads(raw)
        assert manifest['source_commit'] == source and manifest['version'] == version
        assert manifest['min_version'] == '0.4.16' and manifest['signature_algorithm'] == 'RSA-SHA256'
        (out / 'manifest.json').write_bytes(raw)
        (out / 'manifest.sig').write_bytes(base64.b64decode(archive.read('manifest.sig')))
        subprocess.run(['openssl', 'dgst', '-sha256', '-verify', str(pub), '-signature', str(out / 'manifest.sig'), str(out / 'manifest.json')], check=True)
        tree = {r['path']: r['sha'] for r in api(f'repos/{repo}/git/trees/{source}?recursive=1')['tree'] if r['type'] == 'blob'}
        for path, digest in manifest['files'].items():
            data = archive.read('payload/' + path)
            assert hashlib.sha256(data).hexdigest() == digest, path
            assert hashlib.sha1(b'blob ' + str(len(data)).encode() + b'\0' + data).hexdigest() == tree[path], path
    assets = [package]
    if 'android' in meta:
        android = meta['android']
        apk = out / android['asset']
        assert re.fullmatch(r'2Cam_Digital_Signage_v0\.4\.(15|16)_DEBUG\.apk', apk.name)
        chunks = sorted(bundle.glob('android-*.b64'))
        assert chunks
        apk.write_bytes(base64.b64decode(''.join(p.read_text().strip() for p in chunks)))
        assert hashlib.sha256(apk.read_bytes()).hexdigest() == android['sha256']
        assets.append(apk)
        if 'envelope' in android:
            envelope_meta = android['envelope']
            envelope_raw = (bundle / 'apk-envelope-manifest.json').read_bytes()
            envelope_sig = (bundle / 'apk-envelope-manifest.sig').read_bytes()
            signed = json.loads(envelope_raw)
            (out / 'apk-manifest.json').write_bytes(envelope_raw)
            (out / 'apk-manifest.sig').write_bytes(base64.b64decode(envelope_sig, validate=False))
            subprocess.run(['openssl', 'dgst', '-sha256', '-verify', str(pub), '-signature', str(out / 'apk-manifest.sig'), str(out / 'apk-manifest.json')], check=True)
            assert signed['platform'] == 'android-tv' and signed['package'] == 'com.digitalsignage.tv' and signed['channel'] == 'debug'
            assert signed['certificate_sha256'] == android['certificate_sha256'] == '5390caf27181bb1163bb165867be5a5a83e748899957f0e0f68606ed6282f47a'
            assert signed['sha256'] == android['sha256'] and signed['size'] == apk.stat().st_size
            envelope = out / envelope_meta['asset']
            assert envelope.name == f'2CAM_APK_android-tv_v{signed["version_name"]}_RSA.zip'
            with zipfile.ZipFile(envelope, 'w') as archive:
                for name, data in [('manifest.json', envelope_raw), ('manifest.sig', envelope_sig), ('app.apk', apk.read_bytes())]:
                    archive.writestr(zipfile.ZipInfo(name), data)
            assert hashlib.sha256(envelope.read_bytes()).hexdigest() == envelope_meta['sha256']
            assets.append(envelope)
    notes = ('Formatos centrales de medios: una definición para todas las pantallas y modos. '
             'Se retiran los seis porcentajes por pantalla de web, APK y LAN. '
             'Solo SUPERADMIN puede cambiar los formatos; se rechazan cambios incompatibles con los medios existentes.\n\n'
             'Web acumulativa desde 0.4.16; incluye el acceso Actualizar desde GitHub. '
             'La reproducción de estas medidas requiere APK 0.4.15 o posterior. '
             'No se instala automáticamente en hosting ni Fire Stick. Prueba física Fire OS pendiente.\n\n'
             'Firma RSA y hashes comprobados; instalación aislada, respaldo SQL y conservación de configuración probados en CI. '
             'El ACK formal v2 sigue pendiente.\n\n'
             'ZIP SHA-256: ' + meta['sha256'])
    notes = meta.get('notes', notes)
    notes_file = out / 'notes.md'
    notes_file.write_text(notes)
    existing = subprocess.run(['gh', 'api', f'repos/{repo}/releases/tags/{meta["tag"]}'], capture_output=True, text=True)
    if existing.returncode:
        subprocess.run(['gh', 'release', 'create', meta['tag'], *map(str, assets), '--target', source, '--title', meta.get('title', 'Digital Signage Web ' + version), '--notes-file', str(notes_file)], check=True)
    else:
        published = {a['name']: a for a in json.loads(existing.stdout)['assets']}
        for asset in assets:
            if asset.name in published:
                expected = 'sha256:' + hashlib.sha256(asset.read_bytes()).hexdigest()
                actual = api(f'repos/{repo}/releases/assets/{published[asset.name]["id"]}').get('digest')
                assert actual == expected, 'Existing release asset differs; refusing replacement'
            else:
                subprocess.run(['gh', 'release', 'upload', meta['tag'], str(asset)], check=True)
    print('SIGNED_RELEASE_PUBLISHED', meta['tag'])
