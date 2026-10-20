"""P5 update safety guard tests; no production private material needed."""
from pathlib import Path
import importlib.util
import io
import tempfile
from unittest.mock import patch
import zipfile

ROOT = Path(__file__).resolve().parents[1]
spec = importlib.util.spec_from_file_location("p5_release", ROOT / "scripts/package_p5_signed_release.py")
builder = importlib.util.module_from_spec(spec)
spec.loader.exec_module(builder)

assert builder.P5_CERT == "52468eda51fbe7de6886fd800874f73566bdaa7165732e96f03764483840ec2f"
assert builder.P5_RSA_DER_SHA256 == "5ad31c4313966cbb414facb56fcd8196be065910201aabfedfbee042bc2c49ae"
assert builder.APK_CODE == 423 and builder.APK_VERSION == "0.4.23-debug"

badging = b"package: name='com.digitalsignage.tv' versionCode='423' versionName='0.4.23-debug'\\nsdkVersion:'19'\\n"
valid_proof = ("Verified using v1 scheme (JAR signing): true\\n"
               "Signer #1 certificate SHA-256 digest: " + builder.P5_CERT + "\\n").encode()
with tempfile.TemporaryDirectory() as directory:
    apk = Path(directory) / "fake.apk"
    apk.write_bytes(b"mock")
    with patch.object(builder, "invoke", side_effect=[valid_proof, badging]):
        result = builder.apk_metadata(apk, "aapt", "apksigner")
    assert result["version_code"] == 423 and result["min_sdk"] == 19
    untrusted = valid_proof.replace(builder.P5_CERT.encode(), b"0" * 64)
    with patch.object(builder, "invoke", side_effect=[untrusted]):
        try:
            builder.apk_metadata(apk, "aapt", "apksigner")
            raise AssertionError("Unexpected signature accepted")
        except ValueError as ex:
            assert "permanent P5" in str(ex)
    no_v1 = valid_proof.replace(b"true", b"false")
    with patch.object(builder, "invoke", side_effect=[no_v1]):
        try:
            builder.apk_metadata(apk, "aapt", "apksigner")
            raise AssertionError("Android 4.4 without v1 accepted")
        except ValueError as ex:
            assert "v1" in str(ex)
    package = Path(directory) / "package.zip"
    with zipfile.ZipFile(package, "w", compression=zipfile.ZIP_STORED) as z:
        builder.zip_write(z, "manifest.json", b"{}")
    with zipfile.ZipFile(package) as z:
        assert z.testzip() is None and z.read("manifest.json") == b"{}"
print("P5_SIGNING_GUARDS_PASS: certificate mismatch rejected, v1 mandatory, identity pinned")
