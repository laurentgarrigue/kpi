#!/usr/bin/env python3
"""Generate the 50→950 colour scales of kpi-layer from the FFCK charter anchors.

Each anchor (charter RGB value) becomes shade 500 exactly; the other shades keep its hue and
interpolate lightness/chroma in OKLCH. Output: CSS custom properties to paste into
app/assets/css/kpi-theme.css (section "generated palette").

Usage: python3 scripts/generate-palette.py
"""
import math

# FFCK charter, "Univers Compétition" (see PUBLIC_SITE_REDESIGN_STRATEGY.md § 10).
ANCHORS = {
    'kpi-blue': '#357b9c',   # primary (AA on white)
    'kpi-red': '#c94a4c',    # accent / error
    'kpi-green': '#186a32',  # success (dark green: AA on white)
    'kpi-gold': '#9a7208',   # warning (dark gold: readable on white)
}

LIGHT_TARGETS = {50: 0.975, 100: 0.945, 200: 0.89, 300: 0.82, 400: 0.72}
DARK_OFFSETS = {600: -0.07, 700: -0.14, 800: -0.20, 900: -0.26, 950: -0.32}
LIGHT_CHROMA = {50: 0.12, 100: 0.25, 200: 0.45, 300: 0.70, 400: 0.90}


def srgb_to_linear(c: float) -> float:
    return c / 12.92 if c <= 0.04045 else ((c + 0.055) / 1.055) ** 2.4


def hex_to_oklch(hex_color: str) -> tuple[float, float, float]:
    r, g, b = (srgb_to_linear(int(hex_color[i:i + 2], 16) / 255) for i in (1, 3, 5))
    l_ = (0.4122214708 * r + 0.5363325363 * g + 0.0514459929 * b) ** (1 / 3)
    m_ = (0.2119034982 * r + 0.6806995451 * g + 0.1073969566 * b) ** (1 / 3)
    s_ = (0.0883024619 * r + 0.2817188376 * g + 0.6299787005 * b) ** (1 / 3)
    lightness = 0.2104542553 * l_ + 0.7936177850 * m_ - 0.0040720468 * s_
    a = 1.9779984951 * l_ - 2.4285922050 * m_ + 0.4505937099 * s_
    bb = 0.0259040371 * l_ + 0.7827717662 * m_ - 0.8086757660 * s_
    return lightness, math.hypot(a, bb), math.degrees(math.atan2(bb, a)) % 360


def scale(hex_color: str) -> dict[int, str]:
    lightness, chroma, hue = hex_to_oklch(hex_color)
    shades = {500: (lightness, chroma)}
    for shade, target in LIGHT_TARGETS.items():
        shades[shade] = (max(target, lightness), chroma * LIGHT_CHROMA[shade])
    for shade, offset in DARK_OFFSETS.items():
        shades[shade] = (max(lightness + offset, 0.12), chroma * 0.9)
    return {s: f'oklch({l:.3f} {c:.3f} {hue:.2f})' for s, (l, c) in sorted(shades.items())}


if __name__ == '__main__':
    for name, anchor in ANCHORS.items():
        print(f'  /* {name}: 500 = {anchor} */')
        for shade, value in scale(anchor).items():
            print(f'  --color-{name}-{shade}: {value};')
