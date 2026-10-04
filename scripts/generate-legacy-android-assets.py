#!/usr/bin/env python3
"""Render the listener web app's icons and hero image for the native clients.

Android 4.3 has no vector drawables, so each icon from
`src/control-plane/app/Support/Icon.php` becomes a white PNG per density; the
app tints it at runtime. The iOS app gets the same glyphs as vector template
PDFs in its asset catalog. Keeping one source means the web app, the Leap and
the iPhone share the same glyphs. Needs `rsvg-convert` and Pillow.
Pass `--only ios` or `--only android` to write one client's files.

The clients live in their own repositories. Check them out next to this one
(`../indie-house-ios`, `../indie-house-android`) or point `INDIE_HOUSE_IOS` /
`INDIE_HOUSE_ANDROID` at them; a missing client is skipped.
"""

import argparse
from io import BytesIO
import json
import os
from pathlib import Path
import re
import shutil
import subprocess

from PIL import Image


ROOT = Path(__file__).resolve().parents[1]
ICON_SOURCE = ROOT / "src/control-plane/app/Support/Icon.php"
HERO = ROOT / "src/control-plane/public/images/hero-bang-dia-2560.webp"  # from scripts/generate-record-shop-hero.py
IOS_ROOT = Path(os.environ.get("INDIE_HOUSE_IOS", ROOT.parent / "indie-house-ios"))
ANDROID_ROOT = Path(os.environ.get("INDIE_HOUSE_ANDROID", ROOT.parent / "indie-house-android"))
RES = ANDROID_ROOT / "res"
IOS_ASSETS = IOS_ROOT / "IndieHouse/Resources/Assets.xcassets"
DENSITIES = {"mdpi": 1.0, "hdpi": 1.5, "xhdpi": 2.0, "xxhdpi": 3.0}

# Icons the native clients use. The web set has no QR, mail or key glyph, so those are defined here.
ICONS = [
    "home", "search", "compass", "library", "plus", "back", "chevron-down",
    "play", "pause", "previous", "next", "shuffle", "repeat", "queue", "close", "more",
    "history", "note", "logout", "eye", "eye-off", "pencil", "trash",
    "check", "alert", "disc", "lock", "globe", "device", "user", "signal", "shield", "qr", "mail", "key",
]
EXTRA_PATHS = {
    "qr": '<rect x="4" y="4" width="6" height="6" rx="1.2"/><rect x="14" y="4" width="6" height="6" rx="1.2"/>'
          '<rect x="4" y="14" width="6" height="6" rx="1.2"/><path d="M14 14h2.5v2.5M20 14v.01M14 20h.01M17.5 20H20v-2.5"/>',
    "mail": '<rect x="3.5" y="5.5" width="17" height="13" rx="2"/><path d="m4.5 7.5 7.5 5.5 7.5-5.5"/>',
    "key": '<circle cx="8" cy="15.5" r="3.5"/><path d="m10.5 13 8.5-8.5M16 7.5l2.5 2.5M13.5 10l2 2"/>',
}


def web_icons() -> tuple[dict[str, str], set[str]]:
    source = ICON_SOURCE.read_text()
    filled = set(re.findall(r"'([a-z-]+)'", re.search(r"FILLED = \[(.*?)\];", source).group(1)))
    paths = dict(re.findall(r"^\s*'([a-z-]+)' => '(.*)',$", source, re.MULTILINE))
    return paths, filled


def render(svg: str, size: int) -> Image.Image:
    result = subprocess.run(
        ["rsvg-convert", "-w", str(size), "-h", str(size)],
        input=svg.encode(), check=True, capture_output=True,
    )
    return Image.open(BytesIO(result.stdout)).convert("RGBA")


def main() -> None:
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--only", choices=["android", "ios"], help="write one client's assets only")
    only = parser.parse_args().only
    paths, filled = web_icons()
    paths.update(EXTRA_PATHS)
    svgs = {}
    for name in ICONS:
        # Same stroke weight as `.icon` in resources/css/app.css.
        style = ('fill="#fff"' if name in filled else
                 'fill="none" stroke="#fff" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"')
        svgs[name] = f'<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" {style}>{paths[name]}</svg>'

    # The hero, cropped to 4:3 around the record shop, its sign and the lane
    # for the connect and sign-in screens and the home spotlight. The box is in
    # 1920x1080 units so it holds for any render size.
    with Image.open(HERO) as art:
        k = art.width / 1920
        stage = art.convert("RGB").crop(tuple(round(v * k) for v in (130, 50, 1370, 980)))
        stage = stage.resize((960, 720), Image.Resampling.LANCZOS)

    android = only != "ios" and ANDROID_ROOT.is_dir()
    ios = only != "android" and IOS_ROOT.is_dir()
    if only != "ios" and not android:
        print(f"Skipping android: {ANDROID_ROOT} not found")
    if only != "android" and not ios:
        print(f"Skipping ios: {IOS_ROOT} not found")

    if android:
        for name, svg in svgs.items():
            for density, scale in DENSITIES.items():
                folder = RES / f"drawable-{density}"
                folder.mkdir(parents=True, exist_ok=True)
                render(svg, round(24 * scale)).save(folder / f"ic_{name.replace('-', '_')}.png", optimize=True)
        stage.save(RES / "drawable-nodpi/connect_hero.jpg", quality=88, optimize=True, progressive=False)

    if ios:
        write_ios(svgs, stage)


def write_ios(svgs: dict[str, str], stage: Image.Image) -> None:
    """Vector template PDFs (tinted in SwiftUI) and the hero, in the iOS asset catalog."""
    icons = IOS_ASSETS / "Glyphs"
    if icons.exists():
        shutil.rmtree(icons)
    icons.mkdir(parents=True)
    write_json(icons / "Contents.json", {"info": {"author": "xcode", "version": 1}})
    for name, svg in svgs.items():
        folder = icons / f"icon-{name}.imageset"
        folder.mkdir()
        pdf = subprocess.run(["rsvg-convert", "-f", "pdf"], input=svg.encode(), check=True, capture_output=True).stdout
        (folder / f"icon-{name}.pdf").write_bytes(pdf)
        write_json(folder / "Contents.json", {
            "images": [{"filename": f"icon-{name}.pdf", "idiom": "universal"}],
            "info": {"author": "xcode", "version": 1},
            "properties": {"preserves-vector-representation": True, "template-rendering-intent": "template"},
        })
    hero = IOS_ASSETS / "ConnectHero.imageset"
    hero.mkdir(exist_ok=True)
    stage.save(hero / "connect_hero.jpg", quality=88, optimize=True, progressive=False)
    write_json(hero / "Contents.json", {
        "images": [{"filename": "connect_hero.jpg", "idiom": "universal"}],
        "info": {"author": "xcode", "version": 1},
    })


def write_json(path: Path, value: dict) -> None:
    path.write_text(json.dumps(value, indent=2) + "\n")


if __name__ == "__main__":
    main()
