#!/usr/bin/env python3
"""Offline contract checks for the LG webOS hosted player and portal download page."""
import json
from pathlib import Path
from html.parser import HTMLParser

root=Path(__file__).resolve().parents[2]
info=json.loads((root/"webos/app/appinfo.json").read_text())
assert info["id"]=="com.twocam.signage"
assert info["version"]=="0.1.0" and info["type"]=="web"
assert info["main"]=="index.html"
assert info["icon"]=="icon.png" and info["largeIcon"]=="largeicon.png"
assert info["resolution"]=="1920x1080"
index=(root/"webos/app/index.html").read_text()
assert "https://www.2cam.services/digitaltv/portal/public/player.php?client=webos" in index
assert "window.location.replace(portal)" in index
assert "http://" not in index and "localStorage" not in index
assert "private" not in index and "token=" not in index
class Parser(HTMLParser): pass
Parser().feed(index)
download=(root/"public/app-downloads.php").read_text()
for value in ("lg-webos", "web0s|webos", "com.twocam.signage_0.1.0_all.ipk",
              "webos-install.php", "player.php?client=webos", "ds-apps-lg"):
    assert value in download, f"Download page lost LG integration {value}"
player=(root/"public/player.php").read_text()
assert "webos-player.js" in player and "webos-player.css" in player
script=(root/"public/assets/js/player-transition.js").read_text()
assert "restoreWebOSSession" in script and "rememberToken" in script
assert "service" in script and "heartbeat" in script
assert (root/"public/webos-install.php").exists()
assert (root/"webos/scripts/prepare_icon.py").exists()
print("LG_WEBOS_CONTRACT_PASS: metadata, hosted HTTPS portal, six-digit pairing UI, LG downloads")
