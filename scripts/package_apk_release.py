"""Offline final-delivery envelope, never CI's unsigned/test-signed candidate.

Use aapt and apksigner from Android build-tools. The RSA key is an OFFLINE input,
never committed. The Android APK must already carry the permanent certificate.
"""
import argparse
import base64
import hashlib
import json
from pathlib import Path
import re
import subprocess
import zipfile


def main():
    p = argparse.ArgumentParser(description=__doc__)
    p.add_argument('--apk', required=True, type=Path)
    p.add_argument('--platform', required=True, choices=['android-tv', 'turnos-control', 'sports-control'])
    p.add_argument('--package', required=True)
    p.add_argument('--certificate-sha256', required=True)
    p.add_argument('--rsa-key', required=True, type=Path)
    p.add_argument('--aapt', required=True)
    p.add_argument('--apksigner-jar', required=True, type=Path)
    p.add_argument('--notes', required=True)
    p.add_argument('--output-dir', required=True, type=Path)
    args = p.parse_args()
    verified = subprocess.check_output(['java', '-jar', str(args.apksigner_jar), 'verify', '--verbose', '--print-certs', '--min-sdk-version', '19', str(args.apk)], text=True)
    certs = re.findall(r'Signer #\d+ certificate SHA-256 digest: ([a-fA-F0-9]{64})', verified)
    if len(certs) != 1 or certs[0].lower() != args.certificate_sha256.lower():
        raise SystemExit('Permanent Android signing certificate does not match; refusing publication')
    if 'Verified using v1 scheme (JAR signing): true' not in verified:
        raise SystemExit('API 19 requires v1 verification')
    badging = subprocess.check_output([args.aapt, 'dump', 'badging', str(args.apk)], text=True)
    identity = re.search(r"package: name='([^']+)' versionCode='(\d+)' versionName='([^']+)'", badging)
    sdk = re.search(r"sdkVersion:'(\d+)'", badging)
    if not identity or not sdk or identity[1] != args.package or int(sdk[1]) < 19:
        raise SystemExit('Unexpected Android package/metadata')
    if not re.fullmatch(r'[A-Za-z0-9.-]{1,40}', identity[3]):
        raise SystemExit('Unsafe version name')
    apk = args.apk.read_bytes()
    manifest = {'schema': 1, 'product': '2cam-apk', 'platform': args.platform, 'package': identity[1],
                'version_code': int(identity[2]), 'version_name': identity[3], 'min_sdk': int(sdk[1]),
                'channel': 'debug', 'certificate_sha256': certs[0].lower(), 'size': len(apk),
                'sha256': hashlib.sha256(apk).hexdigest(), 'notes': args.notes[:5000]}
    raw = (json.dumps(manifest, ensure_ascii=False, indent=2) + '\n').encode()
    signature = subprocess.check_output(['openssl', 'dgst', '-sha256', '-sign', str(args.rsa_key)], input=raw)
    args.output_dir.mkdir(parents=True, exist_ok=True)
    out = args.output_dir / f'2CAM_APK_{args.platform}_v{identity[3]}_RSA.zip'
    if out.exists():
        raise SystemExit('Output already exists; refusing to replace a published binary')
    with zipfile.ZipFile(out, 'w') as archive:
        for name, data in [('manifest.json', raw), ('manifest.sig', base64.b64encode(signature) + b'\n'), ('app.apk', apk)]:
            entry = zipfile.ZipInfo(name)  # Reproducible fixed timestamp, ZIP_STORED.
            archive.writestr(entry, data)
    print(json.dumps({'file': str(out), 'sha256': hashlib.sha256(out.read_bytes()).hexdigest(), 'version': manifest['version_name'], 'certificate_sha256': certs[0].lower()}))


if __name__ == '__main__':
    main()
