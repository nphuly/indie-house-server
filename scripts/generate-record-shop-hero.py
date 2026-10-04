#!/usr/bin/env python3
"""Draw the Indie House hero: a record shop in a Saigon alley at night.

A tube house with a neon "BĂNG ĐĨA" sign, a wall of cassettes, CRT
televisions, record bins and a clerk in headphones; a hologram record floats
over the sign. Next door: a lane with a street lamp, plastic stools and a black
cat, and a parked scooter. The scene is drawn from plain shapes as SVG, then
lit in Pillow (wet-street reflections, bloom, grain).

Everything here is the project's own work, so the picture needs no credit line.
No photo, stock image, font file or third-party logo goes in: sign lettering is
the neon stroke alphabet below, and the small blade sign carries the Indie
House mark from `public/images/indie-house-mark.svg`. Output is deterministic.

Writes `src/control-plane/public/images/hero-bang-dia-{960,1440,1920,2560}.webp`
for the web `srcset` (component `listen.components.hero-art`); the Leap crop
comes from `scripts/generate-legacy-android-assets.py`. Needs `rsvg-convert`
and Pillow.
"""

import math
import random
import re
import subprocess
from io import BytesIO
from pathlib import Path

from PIL import Image, ImageChops, ImageDraw, ImageEnhance, ImageFilter

W, H = 1920, 1080
GROUND = 880      # sidewalk line: everything standing on the street touches this
CURB = 916
SCALE = 1.5       # master render is 2880x1620

P = dict(
    ink="#07090e", yellow="#f7e23b", pink="#ff3d81", cyan="#27e3ec", warm="#ffb45e",
    ember="#ff7a3d", red="#ff3348", wire="#040509",
)
CORE = {P["yellow"]: "#fffbe0", P["pink"]: "#ffe3ee", P["cyan"]: "#e8ffff", P["warm"]: "#fff1dc"}

# Neon stroke alphabet on a 6x10 grid (y down); accents sit above y=0.
_A = [[(0, 10), (0, 2), (2, 0), (4, 0), (6, 2), (6, 10)], [(0, 6), (6, 6)]]
_D = [[(0, 0), (4, 0), (6, 2), (6, 8), (4, 10), (0, 10), (0, 0)]]
_E = [[(6, 0), (0, 0), (0, 10), (6, 10)], [(0, 5), (4.5, 5)]]
_I = [[(1.5, 0), (4.5, 0)], [(3, 0), (3, 10)], [(1.5, 10), (4.5, 10)]]
GLYPHS = {
    "A": _A,
    "Ă": _A + [[(1.5, -3.2), (2.4, -1.9), (3.6, -1.9), (4.5, -3.2)]],
    "B": [[(0, 10), (0, 0), (4.4, 0), (5.6, 1.2), (5.6, 3.8), (4.4, 5), (0, 5)],
          [(4.4, 5), (6, 6.4), (6, 8.6), (4.6, 10), (0, 10)]],
    "C": [[(6, 1.5), (4.5, 0), (1.5, 0), (0, 1.5), (0, 8.5), (1.5, 10), (4.5, 10), (6, 8.5)]],
    "D": _D,
    "Đ": _D + [[(-1.6, 5), (2.4, 5)]],
    "E": _E,
    "É": _E + [[(2.4, -1.5), (4.2, -3.4)]],
    "F": [[(6, 0), (0, 0), (0, 10)], [(0, 5), (4.5, 5)]],
    "G": [[(6, 1.5), (4.5, 0), (1.5, 0), (0, 1.5), (0, 8.5), (1.5, 10), (4.5, 10), (6, 8.5), (6, 5.4), (3.2, 5.4)]],
    "I": _I,
    "Ĩ": _I + [[(0.8, -2.2), (2, -3.4), (4, -2.2), (5.2, -3.4)]],
    "K": [[(0, 0), (0, 10)], [(6, 0), (1.4, 5.2), (0, 5.2)], [(1.4, 5.2), (6, 10)]],
    "N": [[(0, 10), (0, 0), (6, 10), (6, 0)]],
    "O": [[(1.5, 0), (4.5, 0), (6, 1.5), (6, 8.5), (4.5, 10), (1.5, 10), (0, 8.5), (0, 1.5), (1.5, 0)]],
    "R": [[(0, 10), (0, 0), (4.5, 0), (6, 1.5), (6, 3.6), (4.5, 5.1), (0, 5.1)], [(3, 5.1), (6, 10)]],
}


def f(colour, alpha=1.0):
    return f'fill="{colour}"' + (f' fill-opacity="{alpha:.3f}"' if alpha < 1 else "")


def pts(points):
    return "M" + " L".join(f"{x:.1f} {y:.1f}" for x, y in points)


def text_path(text, x, y, size, vertical=False, track=3.2):
    u = size / 10
    out = []
    for ch in text:
        if ch != " ":
            out += [pts([(x + gx * u, y + gy * u) for gx, gy in stroke]) for stroke in GLYPHS[ch]]
        if vertical:
            y += 14.8 * u
        else:
            x += (6 + track) * u
    return " ".join(out)


def text_width(text, size, track=3.2):
    return (len(text) * (6 + track) - track) * size / 10


class Scene:
    def __init__(self):
        self.out = []
        self.rnd = random.Random(1987)

    def add(self, markup):
        self.out.append(markup)

    def rect(self, x, y, w, h, colour, alpha=1.0, extra=""):
        self.add(f'<rect x="{x:.1f}" y="{y:.1f}" width="{w:.1f}" height="{h:.1f}" {f(colour, alpha)} {extra}/>')

    def neon(self, d, colour, width, glow=.6, opacity=1.0):
        core = CORE.get(colour, "#ffffff")
        self.add(f'<g fill="none" stroke-linecap="round" stroke-linejoin="round" opacity="{opacity}">')
        if glow:
            self.add(f'<path d="{d}" stroke="{colour}" stroke-width="{width * 3.4:.1f}" stroke-opacity="{glow}" filter="url(#neon)"/>')
        self.add(f'<path d="{d}" stroke="{colour}" stroke-width="{width:.1f}"/>')
        self.add(f'<path d="{d}" stroke="{core}" stroke-width="{width * .36:.1f}" stroke-opacity=".92"/>')
        self.add("</g>")

    def spill(self, cx, cy, rx, ry, colour, alpha):
        """Coloured light falling on a wall or the ground."""
        gid = f"spill{len(self.out)}"
        self.add(f'<radialGradient id="{gid}"><stop offset="0" stop-color="{colour}" stop-opacity="{alpha}"/>'
                 f'<stop offset="1" stop-color="{colour}" stop-opacity="0"/></radialGradient>')
        self.add(f'<ellipse cx="{cx}" cy="{cy}" rx="{rx}" ry="{ry}" fill="url(#{gid})"/>')

    def shutter(self, x, y, w, h, colour, rib="#000", step=7):
        self.rect(x, y, w, h, colour)
        for ry in range(int(y) + 3, int(y + h), step):
            self.rect(x, ry, w, 1.4, rib, .45)
            self.rect(x, ry + 1.6, w, 1, "#ffffff", .035)

    def wires(self, a, b, sag, width=1.6, colour=None, alpha=1.0):
        (x1, y1), (x2, y2) = a, b
        mx, my = (x1 + x2) / 2, max(y1, y2) + sag
        self.add(f'<path d="M{x1:.0f} {y1:.0f} Q{mx:.0f} {my:.0f} {x2:.0f} {y2:.0f}" fill="none" '
                 f'stroke="{colour or P["wire"]}" stroke-opacity="{alpha}" stroke-width="{width}"/>')


def defs(s):
    s.add("<defs>")
    s.add('<filter id="neon" x="-60%" y="-60%" width="220%" height="220%"><feGaussianBlur stdDeviation="7"/></filter>')
    s.add('<filter id="glow" x="-80%" y="-80%" width="260%" height="260%"><feGaussianBlur stdDeviation="5" result="b"/>'
          '<feMerge><feMergeNode in="b"/><feMergeNode in="SourceGraphic"/></feMerge></filter>')
    s.add('<filter id="soft" x="-50%" y="-50%" width="200%" height="200%"><feGaussianBlur stdDeviation="16"/></filter>')
    s.add('<filter id="haze" x="-50%" y="-50%" width="200%" height="200%"><feGaussianBlur stdDeviation="30"/></filter>')
    s.add('<filter id="blur2"><feGaussianBlur stdDeviation="2"/></filter>')
    s.add('<pattern id="scan" width="4" height="4" patternUnits="userSpaceOnUse"><rect width="4" height="4" fill="#fff" '
          'fill-opacity=".35"/><rect width="4" height="2.2" fill="#fff"/></pattern>')
    s.add(f'<linearGradient id="sky" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#05050b"/>'
          f'<stop offset=".55" stop-color="#140c24"/><stop offset="1" stop-color="#3a1438"/></linearGradient>')
    s.add('<linearGradient id="interior" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#4a2a18"/>'
          '<stop offset=".6" stop-color="#2c1a12"/><stop offset="1" stop-color="#1a110d"/></linearGradient>')
    s.add('<linearGradient id="storeWall" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#0c1316"/>'
          '<stop offset="1" stop-color="#1a2a2e"/></linearGradient>')
    s.add('<linearGradient id="beam" x1="0" y1="1" x2="0" y2="0"><stop offset="0" stop-color="#27e3ec" stop-opacity=".34"/>'
          '<stop offset="1" stop-color="#27e3ec" stop-opacity="0"/></linearGradient>')
    s.add('<linearGradient id="lampCone" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#bff6ff" stop-opacity=".16"/>'
          '<stop offset="1" stop-color="#bff6ff" stop-opacity="0"/></linearGradient>')
    s.add('<linearGradient id="gapHaze" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#ff3d81" stop-opacity="0"/>'
          '<stop offset="1" stop-color="#ff3d81" stop-opacity=".30"/></linearGradient>')
    s.add('<radialGradient id="tube" cx=".35" cy=".3" r=".9"><stop offset="0" stop-color="#9aa3b5"/>'
          '<stop offset=".5" stop-color="#4b5263"/><stop offset="1" stop-color="#1a1d26"/></radialGradient>')
    s.add('<radialGradient id="vig" cx=".46" cy=".46" r=".8"><stop offset=".58" stop-color="#000" stop-opacity="0"/>'
          '<stop offset="1" stop-color="#000" stop-opacity=".62"/></radialGradient>')
    s.add("</defs>")


def sky_and_far(s):
    rnd = s.rnd
    s.rect(0, 0, W, H, "url(#sky)")
    s.spill(1500, 520, 700, 300, P["pink"], .18)
    # Far towers, only visible above the low roofs on the right and through the alley.
    x = 980
    while x < W:
        w = rnd.randint(40, 110)
        top = rnd.randint(60, 420)
        s.rect(x, top, w, GROUND - top, "#0d0c1a")
        for wy in range(top + 12, 600, 11):
            for wx in range(x + 6, x + w - 6, 9):
                if rnd.random() < .08:
                    s.rect(wx, wy, 3, 2, rnd.choice(["#6f7aa8", P["warm"], "#6f7aa8"]), rnd.uniform(.3, .7))
        if rnd.random() < .35:
            s.add(f'<g filter="url(#glow)">')
            s.rect(x + 3, top + 10, 2, GROUND - top - 20, rnd.choice([P["pink"], P["cyan"]]), .55)
            s.add("</g>")
        x += w + rnd.randint(-8, 14)
    # One far megatower with a vertical screen.
    s.rect(1588, 20, 120, 900, "#110f20")
    s.add('<g filter="url(#glow)">')
    s.rect(1606, 60, 26, 300, P["cyan"], .35)
    s.rect(1650, 40, 4, 500, P["pink"], .6)
    s.add("</g>")
    # Light trails of far traffic.
    for d, colour in (("M1000 150 C1300 130 1600 170 1940 120", P["yellow"]),
                      ("M1000 205 C1350 190 1650 222 1940 190", P["pink"])):
        s.add(f'<path d="{d}" fill="none" stroke="{colour}" stroke-opacity=".22" stroke-width="1.2"/>')


def alley_gap(s):
    """The lane between the shop and the next house, receding into haze."""
    rnd = s.rnd
    x0, x1 = 1040, 1236
    vp = (1150, 650)
    s.rect(x0, 300, x1 - x0, GROUND - 300, "#0a0914")
    # Stepped far facades on both sides of the lane.
    for k, depth in enumerate((0.0, .35, .6, .78)):
        lx = x0 + (vp[0] - x0) * depth
        rx = x1 + (vp[0] - x1) * depth
        top = 300 + (vp[1] - 300) * depth * .6
        shade = ["#0d0c18", "#110f1f", "#161326", "#1d1730"][k]
        s.rect(lx, top, 14 + k * 4, GROUND - top, shade)
        s.rect(rx - 14 - k * 4, top, 14 + k * 4, GROUND - top, shade)
    for k in range(5):
        y = 420 + k * 34
        s.wires((x0 + 20 + k * 12, y), (x1 - 20 - k * 12, y + 6), 10 - k, 1, "#1a1726")
        s.add(f'<g filter="url(#glow)"><ellipse cx="{(x0 + x1) / 2 + (k % 2) * 16 - 8}" cy="{y + 18 - k}" rx="{7 - k}" '
              f'ry="{9 - k}" fill="{P["red"]}" fill-opacity="{.9 - k * .13:.2f}"/></g>')
    # Far signs down the lane.
    for x, y, w, h, colour in ((1098, 560, 18, 46, P["pink"]), (1188, 590, 12, 30, P["yellow"]),
                               (1128, 612, 30, 8, P["cyan"]), (1170, 540, 8, 36, P["cyan"])):
        s.add('<g filter="url(#glow)">')
        s.rect(x, y, w, h, colour, .7)
        s.add("</g>")
    for _ in range(40):
        s.rect(rnd.uniform(1080, 1210), rnd.uniform(480, 700), 2, 2, rnd.choice([P["warm"], "#7f8ac0"]), rnd.uniform(.3, .8))
    s.rect(x0, 560, x1 - x0, GROUND - 560, "url(#gapHaze)")
    # Steam rolling out of the lane.
    s.add('<g filter="url(#haze)">')
    for cx, cy, r, a in ((1150, 800, 70, .12), (1110, 740, 60, .09), (1190, 700, 80, .07), (1140, 640, 60, .05)):
        s.add(f'<circle cx="{cx}" cy="{cy}" r="{r}" fill="#dfe6ff" fill-opacity="{a}"/>')
    s.add("</g>")


def right_house(s):
    rnd = s.rnd
    x0 = 1236
    s.rect(x0, 180, W - x0, GROUND - 180, "#121020")
    s.rect(x0, 176, W - x0, 8, "#1b1830")
    # Roof clutter: stainless water tanks, an antenna.
    for tx, tw in ((1470, 120), (1720, 96)):
        s.rect(tx + 12, 150, 6, 30, "#15131f")
        s.rect(tx + tw - 18, 150, 6, 30, "#15131f")
        s.add(f'<rect x="{tx}" y="{106}" width="{tw}" height="46" rx="23" fill="url(#tube)"/>')
        s.add(f'<path d="M{tx + 20} 108 H{tx + tw - 20}" stroke="{P["pink"]}" stroke-opacity=".5" stroke-width="2"/>')
        for k in range(1, 4):
            s.rect(tx + k * tw / 4, 108, 1.2, 42, "#000", .25)
    s.add('<path d="M1860 180 V60 M1840 80 H1880 M1846 100 H1874" stroke="#15131f" stroke-width="3"/>')
    s.add(f'<circle cx="1860" cy="58" r="3" fill="{P["red"]}" filter="url(#glow)"/>')
    # Upper floor windows.
    for wx, wy, lit in ((1300, 240, "#5b7fd6"), (1480, 240, P["warm"]), (1660, 250, "#5b7fd6")):
        s.rect(wx, wy, 130, 150, "#08070e")
        s.rect(wx + 6, wy + 6, 118, 138, lit, .22)
        s.spill(wx + 65, wy + 90, 70, 60, lit, .35)
        for gx in range(wx + 10, wx + 126, 16):
            s.rect(gx, wy + 4, 2.4, 142, "#08070e", .9)
        s.rect(wx - 8, wy + 150, 146, 10, "#1e1b30")
    # Laundry on a line.
    s.wires((1470, 214), (1640, 214), 16, 1, "#2a2740")
    for k, colour in enumerate(("#b9486e", "#d8d2c0", "#3b6f8c", "#c9a13a", "#d8d2c0")):
        lx = 1490 + k * 30
        s.add(f'<path d="M{lx} {222 + (k % 2) * 3} h20 v{rnd.randint(26, 40)} h-20 Z" {f(colour, .55)}/>')
    # A cat asleep on the ledge.
    s.add('<path d="M1690 400 c6 -14 30 -16 40 -4 l6 -8 l2 10 c4 2 4 8 0 10 h-48 c-8 0 -6 -6 0 -8 Z" fill="#050409"/>')
    # AC units.
    for ax, ay in ((1440, 420), (1820, 300)):
        s.rect(ax, ay, 74, 48, "#2a2d38")
        s.add(f'<circle cx="{ax + 50}" cy="{ay + 24}" r="16" fill="#15171f"/>')
        for k in range(4):
            s.rect(ax + 6, ay + 8 + k * 9, 24, 2, "#15171f")
    # Ground floor: shutter with posters and a cassette stencil.
    s.rect(x0, 492, W - x0, 14, "#1b1830")
    s.shutter(1262, 506, 400, GROUND - 506, "#1a1b26")
    s.add(f'<g transform="translate(1372 600)" fill="none" stroke="{P["pink"]}" stroke-opacity=".55" stroke-width="7" stroke-linejoin="round">'
          '<rect x="0" y="0" width="170" height="108" rx="10"/><circle cx="52" cy="48" r="20"/><circle cx="118" cy="48" r="20"/>'
          '<path d="M34 108 L46 82 H124 L136 108"/><path d="M72 48 H98"/></g>')
    for k in range(6):
        s.rect(1380 + k * 28, 708 + rnd.randint(0, 6), 3, rnd.randint(14, 40), P["pink"], .4)
    for px, py, pw, ph, colour in ((1290, 560, 56, 78, "#d8d2c0"), (1296, 650, 48, 64, P["yellow"]),
                                   (1590, 590, 50, 70, "#3b6f8c"), (1560, 700, 60, 44, "#d8d2c0")):
        s.rect(px, py, pw, ph, colour, .3)
        s.rect(px + 6, py + 8, pw - 12, ph * .4, "#000", .25)
    # Lit doorway with stairs.
    s.rect(1690, 540, 120, GROUND - 540, "#0b0d1a")
    s.rect(1698, 548, 104, GROUND - 548, "#3a4f8c", .25)
    for k in range(6):
        s.rect(1698 + k * 8, 820 - k * 40, 104 - k * 8, 3, "#8fa6e8", .25)
    s.spill(1750, 860, 140, 40, "#8fa6e8", .25)
    s.add(f'<g filter="url(#glow)"><ellipse cx="1750" cy="560" rx="12" ry="16" fill="{P["red"]}" fill-opacity=".9"/></g>')
    # Round cafe lightbox on a bracket.
    s.rect(1236, 520, 60, 5, "#2a2d38")
    s.spill(1300, 540, 160, 120, P["cyan"], .22)
    s.add(f'<circle cx="1300" cy="560" r="46" fill="#0a1216"/>')
    s.neon(f"M1300 514 a46 46 0 1 1 -0.1 0", P["cyan"], 4)
    s.neon(text_path("CAFÉ", 1300 - text_width("CAFÉ", 22, 2.4) / 2, 551, 22, track=2.4), P["cyan"], 2.8)


def left_house(s):
    rnd = s.rnd
    s.rect(0, 0, 360, GROUND, "#100e18")
    for k in range(6):
        s.rect(rnd.uniform(0, 330), rnd.uniform(0, 400), rnd.uniform(8, 30), rnd.uniform(80, 300), "#000", .12)
    # Window with television light.
    s.rect(60, 150, 160, 190, "#07060c")
    s.rect(68, 158, 144, 174, "#4a6fe0", .2)
    s.spill(140, 250, 90, 80, "#6f8fff", .3)
    s.add('<path d="M68 332 V260 C110 250 150 262 212 256 V332 Z" fill="#07060c" fill-opacity=".8"/>')
    for gx in range(72, 212, 14):
        s.rect(gx, 156, 2.4, 178, "#07060c")
    s.rect(52, 340, 176, 12, "#1d1a2b")
    # Karaoke sign running down the corner.
    s.spill(300, 320, 180, 280, P["pink"], .3)
    s.rect(268, 110, 64, 376, "#0b0910")
    s.add(f'<rect x="268" y="110" width="64" height="376" fill="none" stroke="{P["pink"]}" stroke-opacity=".35" stroke-width="2"/>')
    s.neon(text_path("KARAOKE", 300 - 3 * 3.4, 126, 34, vertical=True, ), P["pink"], 3.8)
    for by in (150, 440):
        s.rect(334, by, 26, 6, "#2a2d38")
    # Shutter and the earth-god shrine at the door.
    s.rect(0, 492, 360, 14, "#1a1726")
    s.shutter(18, 506, 330, GROUND - 506, "#17161f")
    s.spill(318, 850, 100, 60, P["red"], .4)
    s.add('<path d="M288 826 L318 808 L348 826 Z" fill="#5a1616"/>')
    s.rect(294, 826, 48, 54, "#3a0f0f")
    s.rect(300, 834, 36, 30, "#170606")
    s.add(f'<g filter="url(#glow)"><rect x="304" y="838" width="28" height="4" fill="{P["warm"]}"/>'
          f'<ellipse cx="302" cy="822" rx="3.5" ry="5" fill="{P["red"]}"/><ellipse cx="334" cy="822" rx="3.5" ry="5" fill="{P["red"]}"/>'
          f'<circle cx="314" cy="846" r="1.6" fill="{P["ember"]}"/><circle cx="318" cy="844" r="1.6" fill="{P["ember"]}"/>'
          f'<circle cx="322" cy="846" r="1.6" fill="{P["ember"]}"/></g>')
    s.add('<path d="M318 842 C312 820 326 806 316 780 C310 764 322 752 318 736" fill="none" stroke="#d8d2e8" '
          'stroke-opacity=".18" stroke-width="2"/>')


def ih_mark(s, x, y, size):
    """The Indie House mark, read from its SVG so the sign follows any change to the logo."""
    mark = MARK.read_text(encoding="utf-8")
    group = re.search(r"<g .*</g>", mark, re.S).group(0).replace("url(#glow)", "url(#neon)")
    s.add(f'<g transform="translate({x} {y}) scale({size / 200:.4f})">{group}</g>')


def store(s):
    rnd = s.rnd
    x0, x1 = 360, 1040
    # Facade.
    s.rect(x0, 56, x1 - x0, GROUND - 56, "url(#storeWall)")
    s.rect(x0 - 6, 44, x1 - x0 + 12, 14, "#1f2e33")
    s.add(f'<path d="M{x0 + 250} 44 L{x0 + 290} 18 H{x0 + 390} L{x0 + 430} 44 Z" fill="#1f2e33"/>')
    for k in range(9):
        s.rect(rnd.uniform(x0, x1 - 30), 58, rnd.uniform(6, 26), rnd.uniform(40, 220), "#000", .14)
    s.spill(x0 + 40, 300, 160, 260, P["pink"], .22)
    # Upper window: warm room, red lantern, iron grille.
    wx, wy, ww, wh = 470, 124, 270, 206
    s.rect(wx - 10, wy - 10, ww + 20, wh + 20, "#0a1012")
    s.rect(wx, wy, ww, wh, P["warm"], .5)
    s.rect(wx, wy + wh * .55, ww, wh * .45, P["ember"], .25)
    s.spill(wx + ww / 2, wy + wh / 2, 240, 170, P["warm"], .3)
    s.add(f'<path d="M{wx} {wy} H{wx + 70} C{wx + 60} {wy + 80} {wx + 40} {wy + 150} {wx + 10} {wy + wh} H{wx} Z" fill="#3a1d12" fill-opacity=".9"/>')
    s.add(f'<path d="M{wx + 150} {wy} v34" stroke="#2a130b" stroke-width="2"/>'
          f'<g filter="url(#glow)"><ellipse cx="{wx + 150}" cy="{wy + 56}" rx="20" ry="24" fill="{P["red"]}"/></g>'
          f'<rect x="{wx + 142}" y="{wy + 30}" width="16" height="5" fill="#caa24a"/><rect x="{wx + 142}" y="{wy + 78}" width="16" height="5" fill="#caa24a"/>')
    for gx in range(wx + 14, wx + ww, 20):
        s.rect(gx, wy, 2.6, wh, "#081012")
    for gy in (wy + 70, wy + 140):
        s.rect(wx, gy, ww, 2.6, "#081012")
    for k in range(7):
        cx = wx + 20 + k * 38
        s.add(f'<path d="M{cx} {wy + 70} l19 35 l-19 35 l-19 -35 Z" fill="none" stroke="#081012" stroke-width="2.6"/>')
    # Balcony with potted plants.
    s.rect(x0 + 20, 330, x1 - x0 - 40, 8, "#243439")
    for bx in range(x0 + 24, x1 - 20, 13):
        s.rect(bx, 338, 3, 56, "#152024")
    s.rect(x0 + 10, 394, x1 - x0 - 20, 12, "#243439")
    for px in (430, 560, 690, 800):
        s.add(f'<path d="M{px} 330 l6 -34 h28 l6 34 Z" fill="#3a2418"/>')
        for k in range(7):
            a = -math.pi / 2 + (k - 3) * .38
            lx, ly = px + 20 + math.cos(a) * 46, 300 + math.sin(a) * 46
            s.add(f'<path d="M{px + 20} 300 Q{px + 20 + math.cos(a) * 20:.0f} {300 + math.sin(a) * 34:.0f} {lx:.0f} {ly:.0f}" '
                  f'stroke="#0c1a12" stroke-width="{rnd.uniform(6, 10):.1f}" stroke-linecap="round" fill="none"/>')
    # AC unit and the blade sign with the Indie House mark.
    s.rect(800, 150, 84, 54, "#2c3440")
    s.add('<circle cx="856" cy="177" r="18" fill="#161b22"/>')
    for k in range(4):
        s.rect(806, 158 + k * 10, 26, 2.4, "#161b22")
    s.rect(x0 + 4, 150, 26, 6, "#2a2d38")
    s.rect(x0 + 4, 290, 26, 6, "#2a2d38")
    s.spill(x0 + 64, 222, 120, 120, P["yellow"], .2)
    s.rect(x0 + 28, 130, 80, 186, "#07090e")
    s.add(f'<rect x="{x0 + 28}" y="130" width="80" height="186" fill="none" stroke="{P["yellow"]}" stroke-opacity=".4" stroke-width="2"/>')
    ih_mark(s, x0 + 32, 152, 72)
    s.neon(pts([(x0 + 46, 250), (x0 + 90, 250)]), P["pink"], 3)
    s.neon(pts([(x0 + 46, 266), (x0 + 78, 266)]), P["cyan"], 3)
    s.neon(pts([(x0 + 46, 282), (x0 + 84, 282)]), P["pink"], 3)
    # Fascia sign.
    s.spill(700, 446, 420, 110, P["yellow"], .28)
    s.rect(380, 410, 640, 80, "#06080c")
    s.add(f'<rect x="380" y="410" width="640" height="80" fill="none" stroke="#2a3238" stroke-width="3"/>')
    size = 46
    tw = text_width("BĂNG ĐĨA", size)
    s.neon(text_path("BĂNG ĐĨA", 700 - tw / 2, 432, size), P["yellow"], 4.8)
    s.neon("M420 434 h60 v38 h-60 Z M436 452 m-8 0 a8 8 0 1 0 16 0 a8 8 0 1 0 -16 0 M464 452 m-8 0 a8 8 0 1 0 16 0 a8 8 0 1 0 -16 0 "
           "M432 472 l6 -8 h24 l6 8", P["pink"], 3.2)
    s.neon("M960 453 m-22 0 a22 22 0 1 0 44 0 a22 22 0 1 0 -44 0 M960 453 m-6 0 a6 6 0 1 0 12 0 a6 6 0 1 0 -12 0 "
           "M988 430 L978 466", P["cyan"], 3.2)
    # Rolled-up shutter and a cyan LED strip.
    s.rect(398, 492, 604, 22, "#2b303a")
    for k in range(5):
        s.rect(398, 494 + k * 4, 604, 1.2, "#000", .35)
    s.add(f'<g filter="url(#glow)"><rect x="400" y="514" width="600" height="3" fill="{P["cyan"]}"/></g>')
    # Interior.
    ix0, ix1, iy0 = 400, 1000, 517
    s.rect(ix0, iy0, ix1 - ix0, GROUND - iy0, "url(#interior)")
    s.rect(ix0, iy0, ix1 - ix0, 18, "#120b08", .8)
    for bx in (452, 566, 680, 794, 908):
        drop = rnd.randint(24, 44)
        s.add(f'<path d="M{bx} {iy0} v{drop}" stroke="#120b08" stroke-width="1.5"/>')
        s.spill(bx, iy0 + drop + 20, 90, 80, P["warm"], .32)
        s.add(f'<g filter="url(#glow)"><circle cx="{bx}" cy="{iy0 + drop + 6}" r="6" fill="#fff1dc"/></g>')
    # Cassette wall.
    palette = ["#e8c547", "#d9583b", "#3fa7a3", "#e9e0c9", "#8b5a9b", "#2f5d8a", "#f08a4b", "#c9c2b0"]
    for row in range(9):
        for col in range(8):
            cx, cy = 418 + col * 27, 580 + row * 18
            colour = rnd.choice(palette)
            a = rnd.uniform(.55, .9)
            s.rect(cx, cy, 24, 15, colour, a)
            s.rect(cx + 3, cy + 3, 18, 6, "#f4ecd6", .55)
            s.rect(cx + 7, cy + 5, 3, 2, "#1a110d")
            s.rect(cx + 14, cy + 5, 3, 2, "#1a110d")
    # Gig poster.
    s.rect(662, 572, 66, 92, "#e9e0c9", .75)
    s.add(f'<circle cx="695" cy="604" r="18" fill="{P["pink"]}" fill-opacity=".85"/>')
    for k in range(4):
        s.rect(668, 626 + k * 8, 54, 4, "#1a110d", .7 - k * .12)
    # Stacked CRT televisions.
    s.spill(905, 640, 150, 110, P["cyan"], .3)
    screens = ["bars", "eq", "wave", "static", "rec", "eq2"]
    for k, kind in enumerate(screens):
        tx, ty = 822 + (k % 3) * 60, 572 + (k // 3) * 56
        s.rect(tx, ty, 56, 52, "#2b2a30")
        s.rect(tx + 44, ty + 8, 8, 36, "#1c1b20")
        sx, sy, sw, sh = tx + 5, ty + 6, 36, 40
        s.add(f'<rect x="{sx}" y="{sy}" width="{sw}" height="{sh}" rx="5" fill="#0a1a1e"/>')
        if kind == "bars":
            for b, colour in enumerate(("#e9e0c9", "#e8c547", "#3fd9e0", "#4fd06a", "#d04fb4", "#e0453f", "#3f5de0")):
                s.rect(sx + 2 + b * 4.6, sy + 3, 4.6, sh - 12, colour, .85)
            s.rect(sx + 2, sy + sh - 9, sw - 4, 6, "#1b2f8c", .9)
        elif kind in ("eq", "eq2"):
            colour = P["yellow"] if kind == "eq" else P["pink"]
            for b in range(6):
                bh = rnd.randint(6, sh - 8)
                s.rect(sx + 3 + b * 5.4, sy + sh - 3 - bh, 3.6, bh, colour, .9)
        elif kind == "wave":
            d = "M" + " L".join(f"{sx + 2 + i * 1.6:.1f} {sy + sh / 2 + math.sin(i * .55) * 10 * math.sin(i * .09):.1f}" for i in range(21))
            s.add(f'<path d="{d}" fill="none" stroke="{P["cyan"]}" stroke-width="1.6"/>')
        elif kind == "static":
            for _ in range(60):
                s.rect(sx + rnd.uniform(1, sw - 3), sy + rnd.uniform(1, sh - 3), 2, 1.4, "#cfe8ff", rnd.uniform(.2, .8))
        elif kind == "rec":
            s.add(f'<circle cx="{sx + 10}" cy="{sy + 10}" r="4" fill="{P["red"]}"/>')
            s.add(f'<path d="M{sx + 14} {sy + 20} l10 6 l-10 6 Z" fill="{P["cyan"]}" fill-opacity=".8"/>')
        s.add(f'<rect x="{sx}" y="{sy}" width="{sw}" height="{sh}" rx="5" fill="url(#scan)" fill-opacity=".18"/>')
    s.rect(816, 684, 190, 60, "#2a1a12")
    s.rect(816, 684, 190, 5, "#4a2f20")
    # Counter, turntable and the clerk in headphones.
    s.add('<path d="M722 700 c-16 0 -26 -12 -26 -28 c0 -16 11 -28 26 -28 c15 0 26 12 26 28 c0 16 -10 28 -26 28 Z" fill="#0b0706"/>')
    s.add('<path d="M670 760 C672 724 692 706 722 704 C752 706 772 724 774 760 Z" fill="#0b0706"/>')
    s.add(f'<path d="M694 672 C694 636 750 636 750 672" fill="none" stroke="#141010" stroke-width="6"/>'
          f'<rect x="688" y="664" width="11" height="20" rx="4" fill="{P["pink"]}"/><rect x="745" y="664" width="11" height="20" rx="4" fill="{P["pink"]}"/>')
    s.add(f'<path d="M744 650 C752 662 752 688 740 702" fill="none" stroke="{P["cyan"]}" stroke-opacity=".7" stroke-width="2"/>')
    s.rect(644, 740, 160, 60, "#3a2418")
    s.rect(644, 736, 160, 6, "#5a3a26")
    s.rect(662, 728, 70, 9, "#1c1b20")
    s.add('<ellipse cx="690" cy="727" rx="24" ry="5" fill="#0a0a0c"/><ellipse cx="690" cy="727" rx="7" ry="1.6" fill="#d9583b"/>')
    s.add('<path d="M724 724 l-14 4" stroke="#b8b8c0" stroke-width="1.6"/>')
    s.add(f'<path d="M780 736 v-24 l-10 -10" fill="none" stroke="#1c1b20" stroke-width="2.4"/>'
          f'<g filter="url(#glow)"><path d="M762 700 l14 -6 l4 10 Z" fill="{P["warm"]}"/></g>')
    # Record bins in front: columns of sleeves, each one a little higher than the one in front of it.
    covers = ["#d9583b", "#e8c547", "#3fa7a3", "#e9e0c9", "#8b5a9b", "#2f5d8a", "#f08a4b", "#1c1b20", "#b9486e"]
    fronts = iter(("sun", "rings", "stripes", "split", "dot", "grid"))
    for bx0, bx1 in ((410, 630), (820, 990)):
        cols = 3 if bx1 - bx0 > 200 else 2
        cw = (bx1 - bx0 - 12) / cols
        for c in range(cols):
            cx = bx0 + 6 + c * cw
            sw = cw - 8
            for layer in range(6, 0, -1):
                top = 786 - layer * 6 + rnd.uniform(-1.5, 1.5)
                s.rect(cx + rnd.uniform(-2, 2), top, sw, 40, rnd.choice(covers), .95)
                s.rect(cx, top, sw, 1.4, "#000", .35)
            top = 786
            art = next(fronts)
            base = {"sun": "#e9e0c9", "rings": "#1c1b20", "stripes": "#2f5d8a", "split": "#e8c547", "dot": "#b9486e", "grid": "#e9e0c9"}[art]
            s.rect(cx, top, sw, 60, base)
            mx, my = cx + sw / 2, top + 22
            if art == "sun":
                for k in range(4):
                    s.rect(cx, top + 24 + k * 7, sw, 3.5, P["pink"], .8)
                s.add(f'<circle cx="{mx:.0f}" cy="{my:.0f}" r="13" fill="{P["ember"]}"/>')
            elif art == "rings":
                for r in (17, 11, 5):
                    s.add(f'<circle cx="{mx:.0f}" cy="{my:.0f}" r="{r}" fill="none" stroke="{P["cyan"]}" stroke-width="1.8"/>')
            elif art == "stripes":
                for k in range(5):
                    s.add(f'<path d="M{cx:.0f} {top + 10 + k * 9} L{cx + sw:.0f} {top + k * 9}" stroke="{P["yellow"]}" stroke-width="3"/>')
            elif art == "split":
                s.rect(cx, top, sw / 2, 60, "#1c1b20")
                s.add(f'<circle cx="{mx:.0f}" cy="{my:.0f}" r="12" fill="{P["pink"]}"/>')
            elif art == "dot":
                s.add(f'<circle cx="{mx:.0f}" cy="{my:.0f}" r="7" fill="#e9e0c9"/>')
            else:
                for gx in range(3):
                    for gy in range(3):
                        s.rect(cx + 8 + gx * (sw - 16) / 3, top + 6 + gy * 10, (sw - 16) / 3 - 3, 7, covers[(gx + gy * 3) % 9])
        s.rect(bx0, 818, bx1 - bx0, GROUND - 818, "#3d2616")
        s.rect(bx0, 818, bx1 - bx0, 5, "#6a4428")
        for k in range(1, 4):
            s.rect(bx0, 818 + k * 16, bx1 - bx0, 1.2, "#1c120c", .7)
        s.rect(bx0, GROUND - 8, bx1 - bx0, 8, "#1c120c")
    # Pillars with posters.
    for px in (360, 1000):
        s.rect(px, 490, 40, GROUND - 490, "#1d2a2e")
        s.rect(px + (32 if px < 700 else 0), 490, 8, GROUND - 490, "#000", .3)
    for px, py, colour, a in ((364, 560, P["yellow"], .7), (366, 650, "#e9e0c9", .55), (1004, 580, P["pink"], .7),
                              (1004, 680, "#3fa7a3", .6)):
        s.rect(px, py, 32, 70, colour, a)
        s.rect(px + 4, py + 8, 24, 30, "#000", .35)
        s.rect(px + 4, py + 44, 24, 3, "#000", .5)
        s.rect(px + 4, py + 52, 16, 3, "#000", .5)
    # Warm light spilling out onto the sidewalk.
    s.add(f'<path d="M400 {GROUND} L1000 {GROUND} L1140 {H} L260 {H} Z" fill="{P["warm"]}" fill-opacity=".09" filter="url(#soft)"/>')


def pole_and_wires(s):
    rnd = s.rnd
    px = 1236
    s.rect(px, 30, 20, GROUND - 30, "#1a1c24")
    s.rect(px + 14, 30, 6, GROUND - 30, "#000", .3)
    for y in range(80, 400, 36):
        s.rect(px - 10, y, 40, 5, "#23252f")
    s.add(f'<rect x="{px - 34}" y="226" width="30" height="64" rx="6" fill="#2a2d38"/>'
          f'<rect x="{px + 22}" y="250" width="28" height="50" rx="6" fill="#2a2d38"/>')
    for k in range(3):
        s.add(f'<ellipse cx="{px + 10}" cy="{330 + k * 14}" rx="{34 - k * 6}" ry="7" fill="none" stroke="{P["wire"]}" stroke-width="2.4"/>')
    # Street lamp over the lane.
    s.add(f'<path d="M{px} 262 C{px - 40} 252 {px - 70} 256 {px - 96} 266" fill="none" stroke="#23252f" stroke-width="5"/>')
    s.add(f'<rect x="{px - 118}" y="262" width="40" height="9" rx="3" fill="#2a2d38"/>')
    s.add(f'<g filter="url(#glow)"><rect x="{px - 114}" y="270" width="32" height="3" fill="#dffaff"/></g>')
    s.add(f'<path d="M{px - 114} 272 L{px - 82} 272 L{px + 10} {GROUND} L{px - 210} {GROUND} Z" fill="url(#lampCone)"/>')
    # A string of coloured bulbs across the lane.
    x1, y1, x2, y2, sag = 1030, 380, px + 4, 360, 46
    s.wires((x1, y1), (x2, y2), sag, 1.2, "#1a1726")
    colours = [P["yellow"], P["pink"], P["cyan"], P["warm"]]
    for k in range(1, 12):
        t = k / 12
        mx, my = (x1 + x2) / 2, max(y1, y2) + sag
        bx = (1 - t) ** 2 * x1 + 2 * (1 - t) * t * mx + t * t * x2
        by = (1 - t) ** 2 * y1 + 2 * (1 - t) * t * my + t * t * y2
        s.add(f'<g filter="url(#glow)"><circle cx="{bx:.1f}" cy="{by + 5:.1f}" r="3.2" fill="{colours[k % 4]}"/></g>')
    # Wires: to the shop, across to the right house, and a tangle.
    anchors_left = [(1030, 80), (1030, 120), (980, 60), (1030, 230), (1040, 300), (360, 110), (300, 60)]
    for k, a in enumerate(anchors_left):
        s.wires(a, (px + 10, 90 + k * 22), rnd.randint(30, 90), rnd.uniform(1.2, 2.6))
    for k in range(9):
        s.wires((px + 10, 80 + k * 18), (W + 20, rnd.randint(40, 260)), rnd.randint(20, 90), rnd.uniform(1.2, 2.4))
    for k in range(6):
        s.wires((0, rnd.randint(20, 120)), (1030, rnd.randint(40, 140)), rnd.randint(60, 140), rnd.uniform(1, 2))


def hologram(s):
    cx, cy, r = 900, 238, 116
    # Projector on the fascia, beam up to the disc.
    s.rect(cx - 16, 398, 32, 12, "#2a2d38")
    s.add(f'<g filter="url(#glow)"><rect x="{cx - 8}" y="396" width="16" height="3" fill="{P["cyan"]}"/></g>')
    s.add(f'<path d="M{cx - 8} 398 L{cx + 8} 398 L{cx + r} {cy + 30} L{cx - r} {cy + 40} Z" fill="url(#beam)"/>')
    disc = [f'<ellipse cx="0" cy="0" rx="{r}" ry="{r}" fill="{P["cyan"]}" fill-opacity=".10"/>']
    for gr in range(r - 6, 44, -7):
        disc.append(f'<circle cx="0" cy="0" r="{gr}" fill="none" stroke="{P["cyan"]}" stroke-width="1.2" '
                    f'stroke-opacity="{.25 + .25 * ((gr // 7) % 2):.2f}"/>')
    disc.append(f'<circle cx="0" cy="0" r="{r}" fill="none" stroke="{P["cyan"]}" stroke-width="3"/>')
    disc.append(f'<circle cx="0" cy="0" r="40" fill="{P["pink"]}" fill-opacity=".35" stroke="{P["pink"]}" stroke-width="2"/>')
    disc.append(f'<circle cx="0" cy="0" r="5" fill="#e8ffff"/>')
    disc.append(f'<path d="M0 0 L{r * .95:.0f} {-r * .32:.0f} A{r} {r} 0 0 0 {r * .5:.0f} {-r * .86:.0f} Z" fill="#e8ffff" fill-opacity=".12"/>')
    body = "".join(disc)
    s.add(f'<g filter="url(#soft)" opacity=".7"><g transform="translate({cx} {cy}) rotate(-14) scale(1 .62)">'
          f'<circle r="{r}" fill="none" stroke="{P["cyan"]}" stroke-width="10"/></g></g>')
    s.add('<mask id="holoMask"><rect x="700" y="80" width="400" height="330" fill="url(#scan)"/></mask>')
    s.add(f'<g mask="url(#holoMask)">')
    s.add(f'<g transform="translate({cx + 4} {cy}) rotate(-14) scale(1 .62)" opacity=".35" style="mix-blend-mode:screen">'
          + body.replace(P["cyan"], P["pink"]) + "</g>")
    s.add(f'<g transform="translate({cx} {cy}) rotate(-14) scale(1 .62)">' + body + "</g>")
    # Tone arm and a level meter floating beside the disc.
    s.add(f'<path d="M{cx + 128} {cy - 70} L{cx + 116} {cy - 6} L{cx + 70} {cy + 12}" fill="none" stroke="{P["cyan"]}" stroke-width="3"/>'
          f'<circle cx="{cx + 128}" cy="{cy - 70}" r="7" fill="none" stroke="{P["cyan"]}" stroke-width="2.4"/>')
    for k in range(9):
        bh = 8 + 26 * abs(math.sin(k * 1.3))
        s.rect(cx - 70 + k * 16, cy + 96 - bh, 9, bh, P["cyan"], .7)
    s.add("</g>")
    # A glitch slice.
    s.rect(cx - 120, cy - 20, 240, 3, P["cyan"], .35)
    s.rect(cx - 90, cy + 44, 160, 2, P["pink"], .35)


def street_objects(s):
    # Plastic stools and a cat.
    for x, colour in ((1086, "#2f6fd0"), (1150, "#d0343f")):
        s.add(f'<path d="M{x} {GROUND} L{x + 6} {GROUND - 40} H{x + 46} L{x + 52} {GROUND} H{x + 42} L{x + 38} {GROUND - 26} '
              f'H{x + 14} L{x + 10} {GROUND} Z" fill="{colour}" fill-opacity=".85"/>')
        s.rect(x + 2, GROUND - 46, 48, 7, colour)
        s.rect(x + 2, GROUND - 46, 48, 2, "#ffffff", .25)
    cat(s, 1176, GROUND - 46, .56)
    # A parked step-through scooter.
    scooter(s, 1470, GROUND, 1.0)


def cat(s, x, y, k):
    """A black cat sitting on a stool, facing us: round head, separate ears, haunches, curled tail."""
    body = "#040308"
    outline = ("M-28 0 C-33 -10 -31 -26 -23 -38 C-19 -48 -16 -58 -13 -67 C-20 -73 -23 -81 -21 -90 "
               "L-22 -117 L-8 -105 C-3 -107 3 -107 8 -105 L22 -117 L21 -90 C23 -81 20 -73 13 -67 "
               "C16 -58 19 -48 23 -38 C31 -26 33 -10 28 0 Z")
    parts = [
        f'<ellipse cx="0" cy="1" rx="34" ry="3" fill="#000" fill-opacity=".45"/>',
        f'<path d="M24 -5 C44 -3 52 -22 46 -40 C42 -52 48 -62 56 -58" fill="none" stroke="{body}" stroke-width="8" stroke-linecap="round"/>',
        f'<path d="{outline}" fill="{body}"/>',
        # Inner ears, the gap between the front legs and the paws.
        '<path d="M-19 -110 L-17 -96 L-10 -103 Z M19 -110 L17 -96 L10 -103 Z" fill="#2a1020"/>',
        '<path d="M0 -40 V0" stroke="#16121e" stroke-width="1.6"/>',
        '<path d="M-12 0 C-12 -5 -2 -5 -2 0 M2 0 C2 -5 12 -5 12 0" fill="none" stroke="#1c1826" stroke-width="1.4"/>',
        # Rim light: warm from the shop on the left, cool from the street lamp on top.
        f'<path d="M-28 -2 C-33 -12 -31 -26 -23 -38 C-19 -48 -16 -58 -13 -67 C-20 -73 -23 -81 -21 -90 L-22 -117" '
        f'fill="none" stroke="{P["warm"]}" stroke-opacity=".7" stroke-width="1.8"/>',
        f'<path d="M-22 -117 L-8 -105 C-3 -107 3 -107 8 -105 L22 -117" fill="none" stroke="#dffaff" stroke-opacity=".55" stroke-width="1.4"/>',
        f'<path d="M24 -5 C44 -3 52 -22 46 -40 C42 -52 48 -62 56 -58" fill="none" stroke="{P["pink"]}" stroke-opacity=".35" '
        f'stroke-width="1.4" transform="translate(2 -2)"/>',
        # Whiskers catch the light.
        '<path d="M-6 -80 L-30 -85 M-6 -78 L-31 -78 M-6 -76 L-28 -71 M6 -80 L30 -85 M6 -78 L31 -78 M6 -76 L28 -71" '
        'stroke="#e8ecff" stroke-opacity=".45" stroke-width=".9"/>',
        # Glowing eyes with slit pupils.
        f'<g filter="url(#glow)"><ellipse cx="-8" cy="-89" rx="4.6" ry="3.2" fill="#e9f25a"/>'
        f'<ellipse cx="8" cy="-89" rx="4.6" ry="3.2" fill="#e9f25a"/></g>',
        '<ellipse cx="-8" cy="-89" rx="1" ry="2.8" fill="#040308"/><ellipse cx="8" cy="-89" rx="1" ry="2.8" fill="#040308"/>',
    ]
    s.add(f'<g transform="translate({x} {y}) scale({k})">' + "".join(parts) + "</g>")


def scooter(s, x, y, k):
    """A retro step-through scooter with a round rear cowl, parked facing right."""
    body = "#2b6663"
    rim = P["cyan"]
    wheel = lambda cx: (f'<circle cx="{cx}" cy="-34" r="34" fill="#07080c"/>'
                        f'<circle cx="{cx}" cy="-34" r="20" fill="#2a2e38"/><circle cx="{cx}" cy="-34" r="7" fill="#11131a"/>'
                        f'<path d="M{cx - 30} -40 A30 30 0 0 1 {cx + 30} -40" fill="none" stroke="#5a6070" stroke-opacity=".6" stroke-width="2"/>')
    parts = [
        '<linearGradient id="vespa" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#3d8c86"/>'
        '<stop offset="1" stop-color="#163634"/></linearGradient>',
        wheel(0), wheel(236),
        # Floorboard and fork.
        '<path d="M92 -46 H206 L200 -30 H96 Z" fill="#13161d"/>',
        '<path d="M222 -80 L236 -34" stroke="#1a1d26" stroke-width="8"/>',
        # Rear cowl over the engine.
        '<path d="M-64 -40 C-72 -96 -30 -130 34 -128 C84 -126 110 -100 114 -62 L116 -44 C60 -40 -10 -38 -64 -40 Z" fill="url(#vespa)"/>',
        '<path d="M-40 -64 C-20 -92 40 -96 74 -80" fill="none" stroke="#000" stroke-opacity=".3" stroke-width="3"/>',
        # Seat and rack.
        '<path d="M-6 -124 C4 -146 96 -146 110 -126 L104 -116 C70 -122 30 -122 -2 -116 Z" fill="#0c0d12"/>',
        '<path d="M-56 -122 H0 M-50 -122 V-112" stroke="#3a3f4d" stroke-width="3"/>',
        # Leg shield and front fender.
        '<path d="M178 -46 C196 -90 196 -150 206 -184 L228 -186 C230 -140 226 -90 214 -46 Z" fill="url(#vespa)"/>',
        '<path d="M204 -58 C210 -86 262 -88 268 -58 L258 -54 C252 -74 218 -74 214 -54 Z" fill="url(#vespa)"/>',
        # Handlebar with the headlight, mirror.
        '<path d="M212 -184 L218 -196" stroke="#1a1d26" stroke-width="7"/>',
        '<path d="M192 -200 C204 -214 246 -216 262 -200 L256 -190 H200 Z" fill="url(#vespa)"/>',
        '<path d="M186 -204 H266" stroke="#1a1d26" stroke-width="5" stroke-linecap="round"/>',
        '<path d="M196 -206 L188 -236" stroke="#1a1d26" stroke-width="2.4"/><circle cx="187" cy="-240" r="7" fill="#2a2d38"/>',
        f'<g filter="url(#glow)"><ellipse cx="262" cy="-196" rx="5" ry="9" fill="#fff1dc"/>'
        f'<rect x="-70" y="-78" width="6" height="12" fill="{P["red"]}"/></g>',
        # Rim light.
        f'<path d="M-64 -44 C-72 -96 -30 -130 34 -128 C84 -126 110 -100 114 -62" fill="none" stroke="{rim}" stroke-opacity=".75" stroke-width="2"/>',
        f'<path d="M206 -184 L228 -186 M204 -58 C210 -86 262 -88 268 -58 M192 -200 C204 -214 246 -216 262 -200" fill="none" '
        f'stroke="{rim}" stroke-opacity=".75" stroke-width="2"/>',
        f'<path d="M-6 -124 C4 -146 96 -146 110 -126" fill="none" stroke="{P["pink"]}" stroke-opacity=".6" stroke-width="1.6"/>',
    ]
    s.add(f'<g transform="translate({x} {y}) scale({k})">' + "".join(parts) + "</g>")


def ground(s):
    rnd = s.rnd
    s.rect(0, GROUND, W, CURB - GROUND, "#16151d")
    for x in range(-20, W, 64):
        s.add(f'<path d="M{x} {GROUND} L{x - 14} {CURB}" stroke="#0a0a10" stroke-width="1.4"/>')
    s.rect(0, GROUND + 17, W, 1.2, "#0a0a10")
    s.rect(0, CURB, W, 12, "#1d1c26")
    s.rect(0, CURB, W, 2, "#3a3848")
    s.rect(0, CURB + 12, W, H - CURB - 12, "#0a0a10")
    for x in range(40, W, 260):
        s.rect(x + rnd.randint(0, 80), 1020, 90, 4, "#d8d2c0", .08)


def rain(s):
    rnd = s.rnd
    drops = []
    for _ in range(420):
        x, y = rnd.uniform(-40, W), rnd.uniform(-40, H - 20)
        length = rnd.uniform(14, 34)
        drops.append(f'<path d="M{x:.0f} {y:.0f} l-4 {length:.0f}" stroke="#d6e2ff" stroke-opacity="{rnd.uniform(.05, .16):.2f}"/>')
    s.add('<g stroke-width="1.1">' + "".join(drops) + "</g>")
    for _ in range(70):
        x, y = rnd.uniform(0, W), rnd.uniform(CURB + 16, H)
        s.add(f'<ellipse cx="{x:.0f}" cy="{y:.0f}" rx="{rnd.uniform(6, 14):.0f}" ry="{rnd.uniform(1.2, 2.4):.1f}" fill="none" '
              f'stroke="#d6e2ff" stroke-opacity="{rnd.uniform(.05, .14):.2f}"/>')


def draw():
    s = Scene()
    s.add(f'<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 {W} {H}" width="{W}" height="{H}">')
    defs(s)
    sky_and_far(s)
    alley_gap(s)
    right_house(s)
    left_house(s)
    store(s)
    pole_and_wires(s)
    hologram(s)
    street_objects(s)
    ground(s)
    s.add("</svg>")
    fg = Scene()
    fg.rnd = random.Random(7)
    fg.add(f'<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 {W} {H}" width="{W}" height="{H}">')
    defs(fg)
    rain(fg)
    fg.add(f'<rect width="{W}" height="{H}" fill="url(#vig)"/>')
    fg.add("</svg>")
    return "\n".join(s.out), "\n".join(fg.out)


def render(svg):
    png = subprocess.run(["rsvg-convert", "-w", str(int(W * SCALE)), "-h", str(int(H * SCALE))],
                         input=svg.encode(), check=True, capture_output=True).stdout
    return Image.open(BytesIO(png)).convert("RGBA")


def scaled(image, k):
    return ImageEnhance.Brightness(image).enhance(k)


def wet_street(img):
    """Mirror everything above the sidewalk line into the wet ground, smeared and broken by ripples."""
    w, h = img.size
    g = int(GROUND * SCALE)
    depth = h - g
    refl = img.crop((0, g - depth, w, g)).transpose(Image.Transpose.FLIP_TOP_BOTTOM)
    smear = refl.resize((w, max(1, depth // 14)), Image.Resampling.BILINEAR).resize((w, depth), Image.Resampling.BILINEAR)
    refl = Image.blend(refl.filter(ImageFilter.GaussianBlur(2.5 * SCALE)), smear, .55)
    rnd = random.Random(11)
    # Reflectivity: sidewalk tiles a little, road more, puddles most.
    wet = Image.new("L", (w, depth), 0)
    d = ImageDraw.Draw(wet)
    d.rectangle((0, 0, w, int((CURB - GROUND) * SCALE)), fill=125)
    d.rectangle((0, int((CURB - GROUND + 12) * SCALE), w, depth), fill=175)
    for cx, cy, rx, ry in ((720, 990, 330, 34), (1330, 1045, 360, 30), (260, 962, 190, 16), (1700, 975, 200, 18)):
        d.ellipse([(cx - rx) * SCALE, (cy - ry - GROUND) * SCALE, (cx + rx) * SCALE, (cy + ry - GROUND) * SCALE], fill=255)
    wet = wet.filter(ImageFilter.GaussianBlur(6 * SCALE))
    ripples = Image.new("L", (w, depth), 255)
    d = ImageDraw.Draw(ripples)
    for _ in range(900):
        y = rnd.uniform(0, depth)
        x = rnd.uniform(-100, w)
        d.line([(x, y), (x + rnd.uniform(40, 260) * SCALE, y)], fill=rnd.randint(40, 150), width=max(1, int(rnd.uniform(1, 2.6) * SCALE)))
    ripples = ripples.filter(ImageFilter.GaussianBlur(1.2 * SCALE))
    mask = ImageChops.multiply(wet, ripples)
    base = img.crop((0, g, w, h))
    lit = ImageChops.screen(base, scaled(refl, .95))
    img.paste(Image.composite(lit, base, mask), (0, g))
    return img


def bloom(img):
    w, h = img.size
    lum = img.convert("L")
    hi = lum.point(lambda v: 0 if v < 135 else min(255, (v - 135) * 2.2))
    bright = ImageChops.multiply(img, Image.merge("RGB", (hi, hi, hi)))
    out = None
    for div, radius, gain in ((4, 3, .6), (6, 10, .55), (10, 24, .55)):
        small = bright.resize((w // div, h // div), Image.Resampling.BILINEAR).filter(ImageFilter.GaussianBlur(radius))
        layer = scaled(small.resize((w, h), Image.Resampling.BILINEAR), gain)
        out = layer if out is None else ImageChops.add(out, layer)
    return ImageChops.screen(img, out)


def finish(img):
    w, h = img.size
    r, g, b = img.split()
    img = Image.merge("RGB", (ImageChops.offset(r, 1, 0), g, ImageChops.offset(b, -1, 0)))
    # Film grain from a seeded generator, so every run gives the same file.
    noise = Image.frombytes("L", (w, h), random.Random(5).randbytes(w * h)).point(lambda v: int(128 + (v - 128) * .14))
    img = ImageChops.soft_light(img, Image.merge("RGB", (noise, noise, noise)))
    return img


ROOT = Path(__file__).resolve().parents[1]
MARK = ROOT / "src/control-plane/public/images/indie-house-mark.svg"
OUT = ROOT / "src/control-plane/public/images"
WIDTHS = (960, 1440, 1920, 2560)


def main():
    scene_svg, fg_svg = draw()
    img = wet_street(render(scene_svg).convert("RGB"))
    img = bloom(img)
    img = Image.alpha_composite(img.convert("RGBA"), render(fg_svg)).convert("RGB")
    img = finish(img)
    for width in WIDTHS:
        height = round(img.height * width / img.width)
        # High quality: the neon edges and wet street band badly at the default of 80.
        img.resize((width, height), Image.Resampling.LANCZOS).save(OUT / f"hero-bang-dia-{width}.webp", quality=88, method=6)


if __name__ == "__main__":
    main()
