#!/usr/bin/env python3
"""Reuse the approved Android 2Cam icon in webOS 80/130 px metadata."""
import argparse
import base64
from pathlib import Path
from PIL import Image

parser = argparse.ArgumentParser()
parser.add_argument("--source", type=Path, required=True,
                    help="branding/ic_2cam_signage.png.b64 from Android repo")
parser.add_argument("--app", type=Path, required=True)
a = parser.parse_args()
a.app.mkdir(parents=True, exist_ok=True)
original = a.app / ".temporary-approved-brand.png"
original.write_bytes(base64.b64decode(a.source.read_text().strip(), validate=True))
try:
    with Image.open(original) as image:
        image = image.convert("RGBA")
        for pixels, name in [(80, "icon.png"), (130, "largeicon.png")]:
            image.resize((pixels, pixels), Image.Resampling.LANCZOS).save(a.app / name)
    print("WEBOS_BRANDING_OK: approved 2Cam signage logo in 80px and 130px")
finally:
    original.unlink(missing_ok=True)
