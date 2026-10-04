#!/usr/bin/env python3
"""Render the shared Indie House SVG for platforms that require PNG icons.

The iOS and Android clients live in their own repositories. Check them out
next to this one (`../indie-house-ios`, `../indie-house-android`) or point
`INDIE_HOUSE_IOS` / `INDIE_HOUSE_ANDROID` at them; a missing client is skipped.
"""

from io import BytesIO
import os
from pathlib import Path
import subprocess

from PIL import Image


ROOT = Path(__file__).resolve().parents[1]
IOS_ROOT = Path(os.environ.get("INDIE_HOUSE_IOS", ROOT.parent / "indie-house-ios"))
ANDROID_ROOT = Path(os.environ.get("INDIE_HOUSE_ANDROID", ROOT.parent / "indie-house-android"))
MARK = ROOT / "src/control-plane/public/images/indie-house-mark.svg"
INK = (7, 9, 14, 255)


def render_mark(size: int) -> Image.Image:
    result = subprocess.run(
        ["rsvg-convert", "-w", str(size), "-h", str(size), str(MARK)],
        check=True, capture_output=True,
    )
    return Image.open(BytesIO(result.stdout)).convert("RGBA")


master = render_mark(2048)


def save_mark(path: Path, size: int) -> None:
    path.parent.mkdir(parents=True, exist_ok=True)
    master.resize((size, size), Image.Resampling.LANCZOS).save(path)


def save_icon(path: Path, size: int) -> None:
    path.parent.mkdir(parents=True, exist_ok=True)
    icon = Image.new("RGBA", (2048, 2048), INK)
    icon.alpha_composite(master)
    icon.resize((size, size), Image.Resampling.LANCZOS).convert("RGB").save(path)


save_mark(ROOT / "src/control-plane/public/images/indie-house-mark.png", 256)

if IOS_ROOT.is_dir():
    ios_assets = IOS_ROOT / "IndieHouse/Resources/Assets.xcassets"
    save_mark(ios_assets / "BrandMark.imageset/brand_mark.png", 512)

    for path in (ios_assets / "AppIcon.appiconset").glob("*.png"):
        with Image.open(path) as existing:
            size = existing.width
        save_icon(path, size)

    for suffix, size in {"": 64, "@2x": 128, "@3x": 192}.items():
        save_mark(ios_assets / "LaunchImage.imageset" / f"LaunchImage{suffix}.png", size)
else:
    print(f"Skipping iOS: {IOS_ROOT} not found")

if ANDROID_ROOT.is_dir():
    legacy_res = ANDROID_ROOT / "res/drawable"
    save_mark(legacy_res / "brand_mark.png", 192)
    save_icon(legacy_res / "ic_launcher.png", 192)
else:
    print(f"Skipping Android: {ANDROID_ROOT} not found")

favicon = Image.new("RGBA", (2048, 2048), INK)
favicon.alpha_composite(master)
favicon.save(ROOT / "src/control-plane/public/favicon.ico",
             format="ICO", sizes=[(16, 16), (32, 32), (48, 48)])
