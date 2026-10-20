#!/usr/bin/env python3
"""Prepare a P5-continuity web ZIP and Android envelope. Never creates or replaces keys.

Requires the exact Android signing certificate used for 0.4.22 and the 2026 RSA
private key corresponding to the trusted public key installed in production.
Refuses a debug/test keystore and mismatched binaries. Only output signed files.
"""
import argparse
import base64
import hashlib
import json
from pathlib import Path
import re
import subprocess
import zipfile

P5_CERT = "52468eda51fbe7de6886fd800874f73566bdaa7165732e96f03764483840ec2f"
P5_RSA_DER_SHA256 = "5ad31c4313966cbb414facb56fcd8196be065910201aabfedfbee042bc2c49ae"
APK_PACKAGE = "com.digitalsignage.tv"
WEB_VERSION = "0.4.34"
APK_VERSION = "0.4.23-debug"
APK_CODE = 423


def sha(data: bytes) -> str:
    return hashlib.sha256(data).hexdigest()


def invoke(command: list[str], *, input_data: bytes | None = None) -> bytes:
    return subprocess.check_output(command, input=input_data)


def zip_write(archive: zipfile.ZipFile, name: str, content: bytes) -> None:
    info = zipfile.ZipInfo(name, (2026, 10, 20, 0, 0, 0))
    info.compress_type = archive.compression
    info.external_attr = 0o100644 << 16
    archive.writestr(info, content)


def apk_metadata(path: Path, aapt: str, apksigner: str) -> dict:
    proof = invoke([apksigner, "verify", "--verbose", "--print-certs",
                    "--min-sdk-version", "19", str(path)]).decode()
    certs = re.findall(r"Signer #\d+ certificate SHA-256 digest: ([a-fA-F0-9]{64})", proof)
    if len(certs) != 1 or certs[0].lower() != P5_CERT:
        raise ValueError("APK signing certificate differs from permanent P5; refusing delivery")
    if "Verified using v1 scheme (JAR signing): true" not in proof:
        raise ValueError("Missing Android 4.4/Fire OS 5 v1 signature")
    badging = invoke([aapt, "dump", "badging", str(path)]).decode()
    match = re.search(r"package: name='([^']+)' versionCode='(\d+)' versionName='([^']+)'", badging)
    sdk = re.search(r"sdkVersion:'(\d+)'", badging)
    if not match or not sdk or match[1] != APK_PACKAGE or int(sdk[1]) != 19:
        raise ValueError("Unexpected APK identity or minimum SDK")
    return {"package": match[1], "version_code": int(match[2]),
            "version_name": match[3], "min_sdk": int(sdk[1])}


def main() -> None:
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--web-root", required=True, type=Path)
    parser.add_argument("--android-apk", required=True, type=Path)
    parser.add_argument("--previous-apk", required=True, type=Path)
    parser.add_argument("--android-source", required=True)
    parser.add_argument("--rsa-key", required=True, type=Path)
    parser.add_argument("--public-key", required=True, type=Path)
    parser.add_argument("--aapt", required=True)
    parser.add_argument("--apksigner", required=True)
    parser.add_argument("--out", required=True, type=Path)
    args = parser.parse_args()
    root = args.web_root.resolve()
    if not re.fullmatch(r"[0-9a-f]{40}", args.android_source):
        raise ValueError("Android source commit SHA is required")
    web_source = invoke(["git", "-C", str(root), "rev-parse", "HEAD"]).decode().strip()
    if not re.fullmatch(r"[0-9a-f]{40}", web_source):
        raise ValueError("Cannot identify web source commit")
    if (root / "version.txt").read_text().strip() != WEB_VERSION:
        raise ValueError("Unexpected web version")
    if not args.rsa_key.is_file() or not args.public_key.is_file():
        raise ValueError("P5 RSA signing material not configured")
    actual_der = invoke(["openssl", "pkey", "-in", str(args.rsa_key), "-pubout", "-outform", "DER"])
    trusted_der = invoke(["openssl", "pkey", "-pubin", "-in", str(args.public_key), "-outform", "DER"])
    if sha(actual_der) != P5_RSA_DER_SHA256 or actual_der != trusted_der:
        raise ValueError("RSA private key does not match 2026 P5 trust anchor")
    previous = apk_metadata(args.previous_apk, args.aapt, args.apksigner)
    current = apk_metadata(args.android_apk, args.aapt, args.apksigner)
    if previous["version_code"] != 422 or previous["version_name"] != "0.4.22-debug":
        raise ValueError("Unexpected 0.4.22 baseline")
    if current["version_code"] != APK_CODE or current["version_name"] != APK_VERSION:
        raise ValueError("New APK is not version 0.4.23-debug / code 423")
    paths = json.loads((root / "tests/update_files.json").read_text())
    if not isinstance(paths, list) or len(paths) != len(set(paths)):
        raise ValueError("Invalid or duplicated web update file list")
    required = {"version.txt", "app/device_pin.php", "app/device_config.php",
                "app/mode_runtime.php", "public/api-device.php", "public/device-manager.php",
                "database/migrations/0.4.34.php"}
    if not required.issubset(set(paths)):
        raise ValueError("Missing P6 files or cumulative migration")
    digests: dict[str, str] = {}
    for name in paths:
        if not re.fullmatch(r"(?:app|public|database)/[A-Za-z0-9_./-]+|version\.txt", name):
            raise ValueError("Forbidden web payload path")
        if ".." in name or "//" in name:
            raise ValueError("Unsafe payload path")
        file = root / name
        if not file.is_file() or file.is_symlink() or not file.resolve().is_relative_to(root):
            raise ValueError("Missing or linked payload file: " + name)
        digests[name] = sha(file.read_bytes())
    manifest = {"product": "digital-signage", "version": WEB_VERSION,
                "min_version": "0.4.16", "source_commit": web_source,
                "signature_algorithm": "RSA-SHA256", "files": digests,
                "migrations": ["database/migrations/0.4.34.php"]}
    web_raw = (json.dumps(manifest, ensure_ascii=False, separators=(",", ":")) + "\n").encode()
    web_sig = invoke(["openssl", "dgst", "-sha256", "-sign", str(args.rsa_key)], input_data=web_raw)
    apk_bytes = args.android_apk.read_bytes()
    apk_manifest = {"schema": 1, "product": "2cam-apk", "platform": "android-tv",
                    "package": APK_PACKAGE, "version_name": APK_VERSION,
                    "version_code": APK_CODE, "min_sdk": 19, "channel": "debug",
                    "certificate_sha256": P5_CERT, "size": len(apk_bytes),
                    "sha256": sha(apk_bytes), "source_repository": "scammarano/digital-signage-android",
                    "source_commit": args.android_source,
                    "notes": "P6: PIN previo al emparejamiento, arranque automatico y Overlay; misma firma P5."}
    apk_raw = (json.dumps(apk_manifest, ensure_ascii=False, separators=(",", ":")) + "\n").encode()
    apk_sig = invoke(["openssl", "dgst", "-sha256", "-sign", str(args.rsa_key)], input_data=apk_raw)
    dest = args.out.resolve()
    dest.mkdir(parents=True, exist_ok=True)
    web_zip = dest / "SIGNAGE_UPDATE_v0.4.34_P6_PIN_OVERLAY_BOOT_RSA.zip"
    apk_final = dest / "2Cam_Digital_Signage_v0.4.23_DEBUG.apk"
    envelope = dest / "2CAM_APK_android-tv_v0.4.23-debug_RSA.zip"
    if any(p.exists() for p in (web_zip, apk_final, envelope)):
        raise FileExistsError("Refusing to overwrite an existing signed delivery")
    with zipfile.ZipFile(web_zip, "w", compression=zipfile.ZIP_DEFLATED, compresslevel=6) as archive:
        zip_write(archive, "manifest.json", web_raw)
        zip_write(archive, "manifest.sig", base64.b64encode(web_sig) + b"\n")
        for name in paths:
            zip_write(archive, "payload/" + name, (root / name).read_bytes())
    with zipfile.ZipFile(envelope, "w", compression=zipfile.ZIP_STORED) as archive:
        zip_write(archive, "manifest.json", apk_raw)
        zip_write(archive, "manifest.sig", base64.b64encode(apk_sig) + b"\n")
        zip_write(archive, "app.apk", apk_bytes)
    apk_final.write_bytes(apk_bytes)
    for path in (web_zip, envelope):
        with zipfile.ZipFile(path) as archive:
            assert archive.testzip() is None
    (dest / "SHA256SUMS.txt").write_text("".join(
        sha(file.read_bytes()) + "  " + file.name + "\n"
        for file in (web_zip, apk_final, envelope)))
    (dest / "release-notes.md").write_text(
        "Web 0.4.34 y Android 0.4.23-debug — P6. Certificado Android y RSA de P5 reutilizados.\n"
        "PIN desde portal previo al emparejamiento, BOOT sin cache y Overlay como modo base.\n"
        "Importar la release en canal Pruebas; promover a Estable solo tras las pruebas.\n"
        "La publicación en GitHub no instala la web en el hosting ni obliga a Android a aceptar instalaciones silenciosas.\n")
    print(json.dumps({"web_source": web_source, "android_source": args.android_source,
                      "certificate_sha256": P5_CERT, "rsa_public_der_sha256": sha(actual_der),
                      "assets": {p.name: sha(p.read_bytes()) for p in (web_zip, apk_final, envelope)}},
                     indent=2))


if __name__ == "__main__":
    main()
