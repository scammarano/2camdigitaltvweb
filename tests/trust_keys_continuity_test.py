#!/usr/bin/env python3
"""CI guard: protect the two different PUBLIC RSA trust anchors."""
from pathlib import Path
from hashlib import sha256
from subprocess import check_output

root = Path(__file__).resolve().parents[1]
keys = {
    ".release-assets/update-public.txt": "5ad31c4313966cbb414facb56fcd8196be065910201aabfedfbee042bc2c49ae",
    "app/trust/legacy-apk-rsa-public.txt": "1c6623f7cd4ed5b1c18b7f3254e6e050a39c6bea8f2d7cd9faaecc56287a10f4",
}
for name, expected in keys.items():
    path = root / name
    if not path.is_file():
        raise SystemExit(f"Missing pinned public key: {name}")
    der = check_output(["openssl", "pkey", "-pubin", "-in", str(path), "-outform", "DER"])
    actual = sha256(der).hexdigest()
    if actual != expected:
        raise SystemExit(f"Unexpected public key rotation: {name}; DER SHA256={actual}")
    print(f"PUBLIC_KEY_PIN_OK {name} {actual}")
print("TRUST_KEY_CONTINUITY_PASS")
