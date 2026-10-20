#!/usr/bin/env python3
"""Live PHP test: LG webOS clients must never be routed to Android APK downloads."""
from urllib.request import Request, urlopen

base = "http://127.0.0.1:8080/"
lg = "Mozilla/5.0 (Web0S; Linux/SmartTV) AppleWebKit/537.36 Chrome/120.0.6099.270 Safari/537.36 WebAppManager"

def read(path, ua=lg):
    with urlopen(Request(base + path, headers={"User-Agent": ua}), timeout=10) as r:
        assert r.status == 200, (path, r.status)
        return r.read().decode("utf-8")

catalog=read("app-downloads.php?standalone=1")
assert 'data-platform="lg-webos"' in catalog, "LG webOS card missing"
assert "com.twocam.signage_0.1.0_all.ipk" in catalog, "IPK button missing"
assert "webos-install.php" in catalog
assert "player.php?client=webos" in catalog
assert "2Cam Digital Signage para LG" in catalog
assert 'application/vnd.android.package-archive' not in catalog, "Android offered to LG"
assert "Descargar APK" not in catalog, "LG shown Android APK"
guide=read("webos-install.php")
assert "Developer Mode" in guide and "ares-install" in guide
player=read("player.php?client=webos")
assert "webos-player.js" in player and 'id="code"' in player
generic=read("app-downloads.php?standalone=1", "Mozilla/5.0 Chrome/120.0")
assert 'data-platform="lg-webos"' in generic, "LG option not discoverable from desktop"
assert "Android" in generic, "Android downloads must remain"
explicit=read("app-downloads.php?app=android-tv&standalone=1",lg)
assert 'data-platform="lg-webos"' not in explicit, "Explicit Android filter ignored"
print("LG_WEBOS_HTTP_PASS: native LG selected, Android APK hidden, desktop catalog preserved, install guide public")
