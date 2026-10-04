#!/usr/bin/env python3
"""Resize the hero artwork for the listener web app.

The source is `src/control-plane/resources/art/hero-hu-tieu-go.jpg`, artwork by
An Nguyen (https://www.artstation.com/annguyen1996). Not in use until the artist
gives written permission. Every screen that shows
it must carry a credit line. Only resize or crop it; never paint over the
artist's signature. The source file is not distributed with this repository.

Writes `public/images/hero-hu-tieu-go-{960,1440,1920}.webp` for the `srcset` on
the home and sign-in pages; `listen.components.hero-art` picks the hero and
shows the credit. Needs Pillow.
"""

from pathlib import Path

from PIL import Image


ROOT = Path(__file__).resolve().parents[1]
SOURCE = ROOT / "src/control-plane/resources/art/hero-hu-tieu-go.jpg"
OUT = ROOT / "src/control-plane/public/images"
WIDTHS = (960, 1440, 1920)


def main() -> None:
    with Image.open(SOURCE) as source:
        art = source.convert("RGB")
    for width in WIDTHS:
        if width > art.width:
            continue
        height = round(art.height * width / art.width)
        image = art if width == art.width else art.resize((width, height), Image.Resampling.LANCZOS)
        # High quality: the neon edges and wet street band badly at the default of 80.
        image.save(OUT / f"hero-hu-tieu-go-{width}.webp", quality=88, method=6)


if __name__ == "__main__":
    main()
