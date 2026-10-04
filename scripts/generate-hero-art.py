#!/usr/bin/env python3
"""Draw the Indie House hero: a future Saigon skyline across the river at night.

Everything is drawn here from plain shapes. No photo, stock image, font or
logo goes into the picture, so the result is the project's own artwork. The
towers are simplified silhouettes of the city skyline; no building names or
brand marks appear.

Not in use: the apps show the record shop (scripts/generate-record-shop-hero.py).
This drawing is kept as a fallback hero that needs no permission from anyone.
Writes src/control-plane/resources/art/hero-saigon.svg and renders
src/control-plane/public/images/hero-saigon.webp; to use it, add it to
`listen.components.hero-art` and point scripts/generate-legacy-android-assets.py
at it.
Needs `rsvg-convert` and Pillow.
"""

from io import BytesIO
import math
import random
import subprocess
from pathlib import Path

from PIL import Image

W, H = 1600, 900
SHORE = 648      # top of the embankment
WATER = 668      # start of the river

P = dict(
    sky_top="#04050a", sky_mid="#0b0a1a", sky_low="#34133f", haze="#ff3d81",
    moon="#f4ecd6", far="#0b0d18", mid="#0e111c", near="#10131f", edge="#20263a",
    warm="#ffb45e", cool="#7fd8ff", glass="#1b2a44",
    signal="#f7e23b", pink="#ff3d81", cyan="#27e3ec", water="#05060b",
)


def fill(colour, alpha=1.0):
    return f'fill="{colour}"' + (f' fill-opacity="{alpha:.3f}"' if alpha < 1 else "")


class Scene:
    def __init__(self):
        self.out = []
        self.rnd = random.Random(2081)
        self.reflect = []   # (x, width, colour, strength) columns mirrored in the river

    def add(self, markup):
        self.out.append(markup)

    def windows(self, x, top, w, bottom, density, colours, cell=(9, 12), size=(4, 3)):
        cw, ch = cell
        for wy in range(int(top) + 10, int(bottom) - 6, ch):
            for wx in range(int(x) + 6, int(x + w) - 6, cw):
                if self.rnd.random() < density:
                    colour = self.rnd.choice(colours)
                    self.add(f'<rect x="{wx}" y="{wy}" width="{size[0]}" height="{size[1]}" '
                             f'{fill(colour, self.rnd.uniform(.35, .85))}/>')

    def tower(self, x, top, w, colour, lights=.18, crown=None, beacon=True, led=None):
        self.add(f'<rect x="{x}" y="{top}" width="{w}" height="{SHORE - top + 2}" {fill(colour)}/>')
        self.windows(x, top, w, SHORE, lights, [P["warm"], P["cool"], P["warm"]])
        if crown == "spire":
            self.add(f'<path d="M{x + w * .3:.0f} {top} L{x + w / 2:.0f} {top - 44} L{x + w * .7:.0f} {top} Z" {fill(colour)}/>')
            top -= 44
        elif crown == "hat":
            self.add(f'<path d="M{x - 6} {top} L{x + w / 2:.0f} {top - 30} L{x + w + 6} {top} Z" {fill(colour)}/>')
            self.add(f'<path d="M{x - 6} {top} L{x + w / 2:.0f} {top - 30} L{x + w + 6} {top}" fill="none" '
                     f'stroke="{P["cyan"]}" stroke-opacity=".7" stroke-width="1.5"/>')
            top -= 30
        if beacon:
            self.add(f'<circle cx="{x + w / 2:.0f}" cy="{top - 4}" r="2.6" fill="#ff3348"/>')
        if led:
            self.add(f'<rect x="{x + 3}" y="{top + 8}" width="2" height="{SHORE - top - 20}" {fill(led, .8)}/>')
            self.add(f'<rect x="{x + w - 5}" y="{top + 8}" width="2" height="{SHORE - top - 20}" {fill(led, .8)}/>')
            self.reflect.append((x, w, led, .5))
        else:
            self.reflect.append((x, w, P["warm"], .18))


def draw():
    s = Scene()
    add, rnd = s.add, s.rnd

    add(f'<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 {W} {H}" width="{W}" height="{H}">')
    add("<defs>")
    add(f'<linearGradient id="sky" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="{P["sky_top"]}"/>'
        f'<stop offset=".45" stop-color="{P["sky_mid"]}"/><stop offset=".72" stop-color="{P["sky_low"]}"/></linearGradient>')
    add(f'<radialGradient id="haze" cx=".5" cy="1" r=".6"><stop offset="0" stop-color="{P["haze"]}" stop-opacity=".32"/>'
        f'<stop offset="1" stop-color="{P["haze"]}" stop-opacity="0"/></radialGradient>')
    add(f'<radialGradient id="moonGlow"><stop offset="0" stop-color="{P["moon"]}" stop-opacity=".28"/>'
        f'<stop offset="1" stop-color="{P["moon"]}" stop-opacity="0"/></radialGradient>')
    add(f'<linearGradient id="river" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#140c22"/>'
        f'<stop offset=".35" stop-color="{P["water"]}"/></linearGradient>')
    add('<linearGradient id="fade" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#fff" stop-opacity=".9"/>'
        '<stop offset="1" stop-color="#fff" stop-opacity="0"/></linearGradient>')
    add(f'<mask id="riverMask"><rect x="0" y="{WATER}" width="{W}" height="{H - WATER}" fill="url(#fade)"/></mask>')
    add('<linearGradient id="glassBit" x1="0" y1="0" x2="1" y2="0"><stop offset="0" stop-color="#1a2b48"/>'
        '<stop offset=".45" stop-color="#2c4a72"/><stop offset="1" stop-color="#101a2c"/></linearGradient>')
    add('<linearGradient id="glassLm" x1="0" y1="0" x2="1" y2="0"><stop offset="0" stop-color="#141d30"/>'
        '<stop offset=".5" stop-color="#22334f"/><stop offset="1" stop-color="#0e1422"/></linearGradient>')
    add('<filter id="glow" x="-50%" y="-50%" width="200%" height="200%"><feGaussianBlur stdDeviation="6" result="b"/>'
        '<feMerge><feMergeNode in="b"/><feMergeNode in="b"/><feMergeNode in="SourceGraphic"/></feMerge></filter>')
    add('<filter id="soft" x="-50%" y="-50%" width="200%" height="200%"><feGaussianBlur stdDeviation="14"/></filter>')
    add('<filter id="ripple" x="-10%" y="-10%" width="120%" height="120%"><feGaussianBlur stdDeviation="3 1.2"/></filter>')
    add('<pattern id="scan" width="4" height="4" patternUnits="userSpaceOnUse"><rect width="4" height="4" fill="#fff" '
        'fill-opacity=".45"/><rect width="4" height="2.4" fill="#fff"/></pattern>')
    add("</defs>")

    # Sky, moon, haze.
    add(f'<rect width="{W}" height="{H}" fill="url(#sky)"/>')
    add(f'<circle cx="1420" cy="150" r="220" fill="url(#moonGlow)"/>')
    add(f'<circle cx="1420" cy="150" r="58" {fill(P["moon"], .9)}/>')
    add(f'<rect x="0" y="300" width="{W}" height="420" fill="url(#haze)"/>')

    # Hologram carp over the old town, the carp that turns into a dragon.
    koi = [
        "M760 210 C752 178 716 158 668 154 C600 148 530 160 470 184 C440 196 410 204 384 204 "
        "L384 222 C414 224 446 230 478 240 C540 262 620 266 680 252 C722 244 752 232 760 210 Z",
        "M386 206 C356 176 320 150 262 128 C290 160 304 186 310 212 C298 238 280 266 246 296 "
        "C300 284 350 254 386 222",
        "M382 212 C352 196 322 174 292 150", "M382 214 C350 214 326 214 304 214",
        "M382 218 C350 234 318 254 280 278",
        "M646 156 C614 118 562 100 498 108 C512 128 522 150 522 170",
        "M690 250 C690 282 668 308 632 320 C644 298 650 276 648 258",
        "M562 262 C554 284 534 298 508 302 C518 286 522 270 520 256",
        "M704 168 C692 194 692 222 704 246",
        "M752 226 C772 240 784 258 786 282", "M756 216 C782 210 802 218 818 234",
    ]
    for row, y in enumerate([186, 208, 230]):
        for k in range(6):
            x = 470 + k * 38 + (row % 2) * 19
            if 470 <= x <= 680 and not (row == 2 and x > 650):
                koi.append(f"M{x} {y} q12 -14 24 0")
    koi_d = " ".join(koi)
    add('<g transform="translate(-60 -20) scale(.78)">')
    add('<mask id="holo"><rect x="200" y="80" width="660" height="260" fill="url(#scan)"/></mask>')
    add(f'<path d="{koi_d}" fill="none" stroke="{P["cyan"]}" stroke-width="12" stroke-opacity=".28" filter="url(#soft)"/>')
    add(f'<g mask="url(#holo)"><path d="{koi_d}" {fill(P["cyan"], .06)} stroke="{P["cyan"]}" stroke-width="2.8" '
        f'stroke-linecap="round" stroke-linejoin="round"/><circle cx="724" cy="192" r="6" fill="{P["cyan"]}"/></g>')
    add('</g>')

    # Sky lanes: flying traffic drawn as long thin light trails.
    for d, colour, dots in (
        ("M-20 330 C300 300 700 318 1000 286 S1500 250 1640 262", P["signal"], 7),
        ("M-20 372 C380 352 820 380 1180 338 S1520 318 1640 330", P["pink"], 5),
        ("M-20 250 C420 236 760 262 1040 236", P["cyan"], 3),
    ):
        add(f'<path d="{d}" fill="none" stroke="{colour}" stroke-opacity=".18" stroke-width="1.2"/>')
    for x, y, colour in ((212, 318, P["signal"]), (548, 310, P["signal"]), (884, 296, P["signal"]),
                         (1316, 262, P["signal"]), (338, 360, P["pink"]), (1012, 352, P["pink"]),
                         (706, 254, P["cyan"])):
        add(f'<g filter="url(#glow)"><rect x="{x}" y="{y}" width="14" height="3" rx="1.5" fill="{colour}"/></g>')

    # Far city: a long low band of towers in the haze.
    x = -20
    while x < W:
        w = rnd.randint(30, 80)
        top = SHORE - rnd.randint(90, 210)
        add(f'<rect x="{x}" y="{top}" width="{w}" height="{SHORE - top}" {fill(P["far"])}/>')
        s.windows(x, top, w, SHORE, .07, ["#6f7aa8"], cell=(8, 10), size=(3, 2))
        x += w + rnd.randint(-4, 6)

    # Mid city: office towers of District 1.
    mid = [(40, 430, 74, None, P["pink"]), (130, 480, 56, "spire", None), (196, 392, 84, "hat", None),
           (300, 452, 62, None, None), (372, 360, 70, None, P["cyan"]), (452, 468, 58, None, None),
           (690, 410, 66, "hat", None), (770, 470, 54, None, None), (832, 380, 78, None, None),
           (920, 440, 60, "spire", None), (990, 470, 70, None, P["pink"]), (1330, 440, 64, None, None),
           (1400, 400, 86, "hat", None), (1500, 460, 60, None, P["signal"]), (1566, 420, 60, None, None)]
    for x, top, w, crown, led in mid:
        s.tower(x, top, w, P["mid"], .16, crown, True, led)

    # A tower whose whole face is an LED screen showing a sound wave.
    sx, stop, sw = 862, 380, 78
    add(f'<rect x="{sx + 8}" y="{stop + 30}" width="{sw - 16}" height="150" {fill("#07080d")}/>')
    for k in range(12):
        bar = 20 + int(55 * abs(math.sin(k * .9) * math.cos(k * .37)) + 12)
        colour = P["signal"] if k % 4 else P["pink"]
        add(f'<g filter="url(#glow)"><rect x="{sx + 12 + k * 5}" y="{stop + 105 - bar / 2:.0f}" width="3" '
            f'height="{bar}" {fill(colour, .9)}/></g>')
    s.reflect.append((sx + 8, sw - 16, P["signal"], .55))

    # The lotus-bud tower with its cantilevered helipad.
    bx, btop, bw = 560, 176, 104
    bud = (f"M{bx} {SHORE} L{bx} {btop + 250} C{bx - 4} {btop + 170} {bx + 6} {btop + 80} {bx + 26} {btop + 30} "
           f"L{bx + bw - 18} {btop} C{bx + bw - 2} {btop + 70} {bx + bw + 6} {btop + 160} {bx + bw} {btop + 250} "
           f"L{bx + bw} {SHORE} Z")
    add(f'<path d="{bud}" fill="url(#glassBit)"/>')
    for k in range(1, 9):
        fx = bx + k * bw / 9
        add(f'<path d="M{fx:.0f} {SHORE} L{fx:.0f} {btop + 250} C{fx:.0f} {btop + 150} {fx + 6:.0f} {btop + 70} '
            f'{fx + 12:.0f} {btop + 24 - k * 2}" fill="none" stroke="#5f86b8" stroke-opacity=".28" stroke-width="1"/>')
    for fy in range(btop + 60, SHORE - 6, 14):
        add(f'<rect x="{bx + 4}" y="{fy}" width="{bw - 8}" height="2" {fill(P["cool"], rnd.uniform(.08, .22))}/>')
    add(f'<path d="{bud}" fill="none" stroke="{P["cyan"]}" stroke-opacity=".75" stroke-width="1.6" filter="url(#glow)"/>')
    pad_y = btop + 118
    add(f'<path d="M{bx + 10} {pad_y} L{bx - 58} {pad_y + 2} C{bx - 74} {pad_y + 4} {bx - 74} {pad_y + 14} '
        f'{bx - 58} {pad_y + 16} L{bx + 12} {pad_y + 18} Z" fill="#1d2c46"/>')
    add(f'<g filter="url(#glow)"><path d="M{bx - 58} {pad_y + 2} C{bx - 74} {pad_y + 4} {bx - 74} {pad_y + 14} '
        f'{bx - 58} {pad_y + 16}" fill="none" stroke="{P["pink"]}" stroke-width="2.4"/>'
        f'<rect x="{bx - 56}" y="{pad_y + 16}" width="66" height="2" fill="{P["pink"]}"/></g>')
    add(f'<rect x="{bx + bw - 22}" y="{btop - 26}" width="3" height="28" fill="#1d2c46"/>'
        f'<circle cx="{bx + bw - 20}" cy="{btop - 28}" r="3" fill="#ff3348"/>')
    s.reflect.append((bx, bw, P["cyan"], .6))

    # The tallest tower: bundled square tubes stepping up to a crown.
    lx, lw = 1128, 150
    steps = [(0, 150, 300), (14, 122, 190), (30, 90, 110), (44, 62, 62)]   # inset, width, top
    for inset, width, top in steps:
        add(f'<rect x="{lx + inset}" y="{top}" width="{width}" height="{SHORE - top}" fill="url(#glassLm)"/>')
        for k in range(1, width // 10):
            add(f'<rect x="{lx + inset + k * 10}" y="{top + 4}" width="1" height="{SHORE - top - 4}" '
                f'{fill("#7b9ccc", .16)}/>')
    add(f'<path d="M{lx + 44} 62 L{lx + 58} 22 L{lx + 75} 4 L{lx + 92} 22 L{lx + 106} 62 Z" fill="#1c2940"/>')
    add(f'<path d="M{lx + 75} 4 L{lx + 75} -30" stroke="#1c2940" stroke-width="3"/>'
        f'<circle cx="{lx + 75}" cy="-28" r="3" fill="#ff3348"/>')
    profile = [(lx, SHORE)]
    for inset, width, top in steps:
        profile += [(lx + inset, profile[-1][1] if len(profile) > 1 else top), (lx + inset, top)]
    profile = [(lx, SHORE), (lx, 300), (lx + 14, 300), (lx + 14, 190), (lx + 30, 190), (lx + 30, 110),
               (lx + 44, 110), (lx + 44, 62), (lx + 58, 22), (lx + 75, 4), (lx + 92, 22), (lx + 106, 62),
               (lx + 106, 110), (lx + 120, 110), (lx + 120, 190), (lx + 136, 190), (lx + 136, 300),
               (lx + 150, 300), (lx + 150, SHORE)]
    outline = "M" + " L".join(f"{x} {y}" for x, y in profile)
    add(f'<path d="{outline}" fill="none" stroke="{P["signal"]}" stroke-width="1.8" stroke-opacity=".9" '
        f'stroke-linejoin="miter" filter="url(#glow)"/>')
    for k, (x0, top) in enumerate(((lx + 62, 70), (lx + 75, 40), (lx + 88, 70))):
        add(f'<rect x="{x0}" y="{top}" width="1.6" height="{SHORE - top - 10}" {fill(P["signal"], .35)}/>')
    for top in (110, 190, 300):
        for fy in range(top + 20, SHORE - 8, 18):
            add(f'<rect x="{lx + 50}" y="{fy}" width="50" height="2" {fill(P["warm"], rnd.uniform(.05, .2))}/>')
    s.reflect.append((lx, lw, P["signal"], .7))

    # Cable-stayed bridge on the right.
    deck = 612
    px, ptop = 1452, 360
    for k in range(9):
        add(f'<path d="M{px} {ptop + 20 + k * 6} L{px - 60 - k * 26} {deck}" stroke="#c9d3e8" stroke-opacity=".32" stroke-width="1"/>')
        add(f'<path d="M{px} {ptop + 20 + k * 6} L{px + 40 + k * 20} {deck}" stroke="#c9d3e8" stroke-opacity=".32" stroke-width="1"/>')
    add(f'<path d="M{px - 8} {deck + 30} L{px - 4} {ptop} L{px + 4} {ptop} L{px + 8} {deck + 30} Z" fill="#1a2031"/>')
    add(f'<circle cx="{px}" cy="{ptop - 4}" r="3" fill="#ff3348"/>')
    add(f'<rect x="1240" y="{deck}" width="{W - 1240}" height="7" fill="#141925"/>')
    add(f'<rect x="{px - 10}" y="{deck + 7}" width="20" height="{SHORE - deck - 7}" fill="#141925"/>')
    for k in range(0, W - 1250, 22):
        add(f'<g filter="url(#glow)"><circle cx="{1250 + k}" cy="{deck - 3}" r="1.8" {fill(P["signal"], .9)}/></g>')
    add(f'<rect x="1300" y="{deck + 2}" width="40" height="3" {fill("#ff2a3a", .9)} filter="url(#glow)"/>')

    # Embankment with trees and a row of lamps.
    add(f'<rect x="0" y="{SHORE}" width="{W}" height="{WATER - SHORE}" fill="#0b0d15"/>')
    canopy = [f"M-10 {SHORE + 2}"]
    x = -10
    while x < W + 20:
        step = rnd.randint(14, 34)
        peak = SHORE - rnd.randint(8, 30)
        canopy.append(f"Q{x + step / 2:.0f} {peak - rnd.randint(4, 12)} {x + step} {SHORE - rnd.randint(2, 10)}")
        x += step
    canopy.append(f"L{W + 20} {SHORE + 2} Z")
    add(f'<path d="{" ".join(canopy)}" fill="#07090e"/>')
    for x in range(20, W, 64):
        add(f'<rect x="{x}" y="{SHORE - 26}" width="2" height="26" fill="#161b27"/>')
        add(f'<g filter="url(#glow)"><circle cx="{x + 1}" cy="{SHORE - 27}" r="2.4" {fill(P["warm"], .95)}/></g>')
        s.reflect.append((x - 3, 8, P["warm"], .35))
    add(f'<rect x="0" y="{WATER - 3}" width="{W}" height="3" fill="#1a2030"/>')

    # River and reflections.
    add(f'<rect x="0" y="{WATER}" width="{W}" height="{H - WATER}" fill="url(#river)"/>')
    ripples = []
    for x, w, colour, strength in s.reflect:
        y = WATER + 6
        while y < H:
            length = w * rnd.uniform(.35, 1)
            offset = rnd.uniform(0, w - length) + rnd.uniform(-6, 6)
            ripples.append(f'<rect x="{x + offset:.0f}" y="{y:.0f}" width="{length:.0f}" height="2" '
                           f'{fill(colour, strength * rnd.uniform(.4, 1))}/>')
            y += rnd.uniform(5, 11) + (y - WATER) * .05
    add(f'<g mask="url(#riverMask)" filter="url(#ripple)">{"".join(ripples)}</g>')

    # A small boat: rower in a conical hat, lantern at the bow.
    bx, by = 900, 792
    add(f'<path d="M{bx - 90} {by - 8} C{bx - 60} {by + 16} {bx + 60} {by + 16} {bx + 96} {by - 12} '
        f'L{bx + 70} {by - 4} L{bx - 70} {by - 4} Z" fill="#0c0f18" stroke="{P["warm"]}" stroke-opacity=".35" stroke-width="1.2"/>')
    add(f'<rect x="{bx - 26}" y="{by - 44}" width="14" height="40" rx="5" fill="#0c0f18" stroke="{P["warm"]}" stroke-opacity=".3"/>')
    add(f'<path d="M{bx - 48} {by - 42} L{bx - 19} {by - 64} L{bx + 10} {by - 42} Z" fill="#141925"/>')
    add(f'<path d="M{bx - 48} {by - 42} L{bx - 19} {by - 64} L{bx + 10} {by - 42}" fill="none" '
        f'stroke="{P["warm"]}" stroke-opacity=".7" stroke-width="1.4"/>')
    add(f'<path d="M{bx - 14} {by - 30} L{bx - 64} {by + 30}" stroke="#141925" stroke-width="3"/>')
    add(f'<rect x="{bx + 70}" y="{by - 34}" width="2" height="26" fill="#141925"/>')
    add(f'<g filter="url(#glow)"><ellipse cx="{bx + 71}" cy="{by - 40}" rx="7" ry="9" fill="{P["pink"]}"/></g>')
    add(f'<g filter="url(#ripple)" opacity=".6"><rect x="{bx + 60}" y="{by + 12}" width="24" height="2" fill="{P["pink"]}"/>'
        f'<rect x="{bx + 64}" y="{by + 22}" width="16" height="2" fill="{P["pink"]}"/>'
        f'<rect x="{bx - 80}" y="{by + 10}" width="170" height="1.5" fill="#c9d3e8" fill-opacity=".25"/></g>')

    # Light drizzle and a vignette.
    for _ in range(180):
        rx, ry = rnd.randint(0, W), rnd.randint(-40, WATER)
        add(f'<path d="M{rx} {ry} l-3 {rnd.randint(12, 28)}" stroke="#c9d4ff" '
            f'stroke-opacity="{rnd.uniform(.04, .12):.2f}" stroke-width="1"/>')
    add('<radialGradient id="vig" cx=".5" cy=".45" r=".78"><stop offset=".62" stop-color="#000" stop-opacity="0"/>'
        '<stop offset="1" stop-color="#000" stop-opacity=".6"/></radialGradient>')
    add(f'<rect width="{W}" height="{H}" fill="url(#vig)"/>')
    add("</svg>")
    return "\n".join(s.out)


ROOT = Path(__file__).resolve().parents[1]
SVG = ROOT / "src/control-plane/resources/art/hero-saigon.svg"
WEBP = ROOT / "src/control-plane/public/images/hero-saigon.webp"


def main():
    SVG.parent.mkdir(parents=True, exist_ok=True)
    SVG.write_text(draw() + "\n", encoding="utf-8")
    png = subprocess.run(["rsvg-convert", "-w", str(W), "-h", str(H), str(SVG)], check=True, capture_output=True).stdout
    with Image.open(BytesIO(png)) as image:
        image.convert("RGB").save(WEBP, quality=84, method=6)


if __name__ == "__main__":
    main()
