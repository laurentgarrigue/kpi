"""Pictograms of the match medals catalogue (FEATURE_MATCH_MEDALS.md § 7.1), drawn for the purpose.

Single source: build.py turns them into `medals-icons.js` for the mockups and into standalone SVG files
in `../pictogrammes/` (the files api2 will serve). Grid 24 × 24, 1.75 stroke, round caps, `currentColor`
only, so the family colour and the dark theme come from the page. Sporting vocabulary (ball, goal,
kayak, chrono, cards, whistle), never a warlike one.
"""
import math

STROKE = 'fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"'
FILL = 'fill="currentColor" stroke="none"'


# Canoe polo (water polo) ball, after the reference drawing chosen on 09/11/2026: the white panels, traced from it
# (unit circle, then smoothed), are holes in a filled disc. Seams slightly thickened so they survive at 16 px.
BALL_PANELS = [
    [(-0.014, -0.934), (-0.227, -0.907), (-0.342, -0.863), (-0.074, -0.814), (0.123, -0.743), (0.347, -0.623), (0.555, -0.464), (0.615, -0.710), (0.467, -0.814), (0.309, -0.885), (0.150, -0.923)],
    [(0.697, -0.628), (0.604, -0.350), (0.495, -0.148), (0.363, 0.011), (0.265, 0.093), (0.189, 0.137), (0.189, 0.273), (0.221, 0.437), (0.566, 0.120), (0.669, -0.005), (0.773, -0.180), (0.822, -0.317), (0.839, -0.421), (0.757, -0.557)],
    [(-0.577, -0.443), (-0.833, -0.415), (-0.893, -0.273), (-0.921, -0.115), (-0.631, -0.158), (-0.347, -0.153), (-0.079, -0.082), (0.063, -0.011), (0.145, 0.055), (0.238, 0.005), (0.347, -0.098), (0.189, -0.246), (-0.036, -0.361), (-0.303, -0.426)],
    [(-0.757, -0.055), (-0.932, -0.016), (-0.926, 0.126), (-0.855, 0.383), (-0.795, 0.497), (-0.697, 0.623), (-0.735, 0.344), (-0.740, -0.055)],
    [(-0.303, -0.049), (-0.303, 0.169), (-0.243, 0.470), (-0.167, 0.672), (-0.085, 0.814), (0.025, 0.934), (0.178, 0.923), (0.336, 0.880), (0.342, 0.863), (0.265, 0.760), (0.199, 0.634), (0.134, 0.459), (0.101, 0.301), (0.096, 0.120), (0.014, 0.060), (-0.085, 0.011)],
    [(0.768, 0.541), (0.587, 0.705), (0.418, 0.809), (0.418, 0.831), (0.456, 0.825), (0.587, 0.738), (0.708, 0.623)],
]


def _smooth(points, cx, cy, scale, corner_deg=55):
    """Closed Catmull-Rom curve through the points, kept sharp where the outline turns more than corner_deg."""
    P = [(cx + x * scale, cy + y * scale) for x, y in points]
    n = len(P)

    def turn(i):
        a, b, c = P[i - 1], P[i], P[(i + 1) % n]
        v1, v2 = (b[0] - a[0], b[1] - a[1]), (c[0] - b[0], c[1] - b[1])
        return abs(math.degrees(math.atan2(v1[0] * v2[1] - v1[1] * v2[0], v1[0] * v2[0] + v1[1] * v2[1])))
    sharp = [turn(i) > corner_deg for i in range(n)]
    d = f'M{P[0][0]:.2f} {P[0][1]:.2f}'
    for i in range(n):
        p0, p1, p2, p3 = P[i - 1], P[i], P[(i + 1) % n], P[(i + 2) % n]
        t1 = (0, 0) if sharp[i] else ((p2[0] - p0[0]) / 6, (p2[1] - p0[1]) / 6)
        t2 = (0, 0) if sharp[(i + 1) % n] else ((p3[0] - p1[0]) / 6, (p3[1] - p1[1]) / 6)
        d += f'C{p1[0] + t1[0]:.2f} {p1[1] + t1[1]:.2f} {p2[0] - t2[0]:.2f} {p2[1] - t2[1]:.2f} {p2[0]:.2f} {p2[1]:.2f}'
    return d + 'Z'


def ball(cx, cy, r):
    """Filled ball: a disc of radius r with the white panels cut out (even-odd), in currentColor."""
    disc = f'M{cx - r:.2f} {cy}a{r} {r} 0 1 0 {2 * r} 0a{r} {r} 0 1 0 {-2 * r} 0Z'
    holes = ''.join(_smooth(panel, cx, cy, r * 0.933) for panel in BALL_PANELS)
    return f'<path d="{disc}{holes}" fill-rule="evenodd" {FILL}/>'


def clock(cx, cy, r, hand=True):
    """Chrono: dial, crown, hand."""
    out = f'<circle cx="{cx}" cy="{cy}" r="{r}"/><path d="M{cx - 1.6} {cy - r - 1.6}h3.2M{cx} {cy - r - 1.6}v1.6"/>'
    if hand:
        out += f'<path d="M{cx} {cy}V{cy - r * 0.55:.2f}M{cx} {cy}l{r * 0.38:.2f} {r * 0.24:.2f}"/>'
    return out


def goal_frame(x, y, w, h):
    """Canoe polo goal: a frame hung above the water, with its net."""
    cols = ''.join(f'M{x + w * i / 4:.2f} {y}v{h}' for i in (1, 2, 3))
    return (f'<rect x="{x}" y="{y}" width="{w}" height="{h}" rx="1"/>'
            f'<path d="{cols}M{x} {y + h / 2:.2f}h{w}M{x + w / 2:.2f} {y}v-{y - 2:.2f}" stroke-width="1.2"/>')


WHISTLE = ('<circle cx="14.5" cy="14" r="5.5"/><path d="M14.5 8.5H3v4.5h6.6"/>'
           '<circle cx="14.5" cy="14" r="1.6"/><path d="M19.2 9.6l2.3-2.3"/>')
MEDAL = '<path d="M8 2.5l3.6 6.6M16 2.5l-3.6 6.6"/><circle cx="12" cy="14.8" r="5.7"/><path d="M12 12.4v4.8"/>'
CARD = '<rect x="7" y="3.5" width="10" height="15" rx="1.6" transform="rotate(-8 12 11)"/>'
NO_CARD = CARD + '<path d="M4 20.5L20 3.5"/>'
PERCENT = '<path d="M19 5L5 19"/><circle cx="7" cy="7" r="2.6"/><circle cx="17" cy="17" r="2.6"/>'
BARS = '<path d="M3 20.5h18M6.5 17v-5M11 17V6M15.5 17v-8M20 17V9" />'
PERSON = '<circle cx="12" cy="7.5" r="4"/><path d="M4 21c0-4.4 3.6-7 8-7s8 2.6 8 7"/>'
TEAM = '<circle cx="8" cy="8" r="3.3"/><circle cx="16.5" cy="8" r="3.3"/><path d="M2 20c0-3.8 2.7-6 6-6 1.7 0 3.1.5 4.2 1.5M10.5 20c0-3.8 2.7-6 6-6s6 2.2 6 6"/>'
KAYAK = '<path d="M2 15c4 2.6 16 2.6 20 0-4-1.7-16-1.7-20 0z"/><path d="M6 6l12 12"/><path d="M4.6 4.6l2.6.6-.6 1.7zM19.4 19.4l-2.6-.6.6-1.7z"/>'
ONE = '<path d="M9 7l4-3v16M9 20h8"/>'
BOLT = '<path d="M13.5 2.5L5 13.5h6.5l-1 8 8.5-11h-6.5z"/>'
FENCE = ('<path d="M4 21V7.5l2-2.7 2 2.7V21M10 21V7.5l2-2.7 2 2.7V21M16 21V7.5l2-2.7 2 2.7V21"/>'
         '<path d="M3 11.5h18M3 17h18"/>')
COMEBACK = '<path d="M3 7l5 8.5 4-3.5 9-8"/><path d="M15.5 4H21v5.5"/>'
EXTRA_TIME = clock(10.5, 13.5, 7.5) + '<path d="M19.5 2.5v6M16.5 5.5h6"/>'
THRILLER = '<path d="M2 12.5h4l2.5-6.5 4 13 3-9.5 1.8 3H22"/>'
GOLDEN = ball(12, 12, 5.8) + ('<path d="M12 2v2.2M12 19.8V22M2 12h2.2M19.8 12H22M4.9 4.9l1.6 1.6M17.5 17.5l1.6 1.6'
                              'M4.9 19.1l1.6-1.6M17.5 6.5l1.6-1.6"/>')
OPENER = ball(10, 13.5, 7.2) + '<path d="M17.5 4.2l2.2-1.7v7.2"/>'
POWERPLAY = BOLT
PENALTY_KILL = FENCE
DISCIPLINE = ('<rect x="3.5" y="4.5" width="9" height="13.5" rx="1.5" transform="rotate(-10 8 11)"/>'
              '<rect x="11" y="6" width="9" height="13.5" rx="1.5" transform="rotate(10 15.5 12.7)"/>')

ICONS = {
    # --- the ball alone: symbol of a goal everywhere (scoring, app2, app3), replacing circles and discs
    'goal': ball(12, 12, 10.5),
    # --- team and player medals
    'opener': OPENER,
    'wire_to_wire': '<path d="M4 4.5v15M20 4.5v15M7.5 12h9.5M14 9l3 3-3 3"/>',
    'scoring_run': '<path d="M3.5 7l5 5-5 5M10 7l5 5-5 5M16.5 7l5 5-5 5"/>',
    'fast_start': clock(14, 13.5, 7) + '<path d="M2 10.5h4M2.5 14h3.5M3 17.5h3"/>',
    'bounce_back': '<path d="M20 12a8 8 0 1 1-2.6-5.9"/><path d="M20 3.5v5h-5"/><circle cx="12" cy="12" r="2.6"/>',
    'comeback': COMEBACK,
    'fightback': '<path d="M2.5 19c3.5 0 4-8.5 8-8.5"/><path d="M12 9h9.5M12 14.5h9.5"/>',
    'game_winner': ball(9.5, 14, 7) + '<path d="M15 5.5l2.4 2.4 4.6-5"/>',
    'tiebreaker': ball(9.5, 14, 7) + '<path d="M15 4.5h7M15 8.5h7M20.5 2.5l-3.5 8"/>',  # the ball breaks the tie: a struck-out "="
    'late_winner': clock(12, 13, 8, hand=False) + f'<path d="M12 13L12 5A8 8 0 0 0 8 6.07Z" {FILL}/><path d="M12 13l3.2 2"/>',
    'lifeline': ('<circle cx="12" cy="12" r="8.8"/><circle cx="12" cy="12" r="4"/>'
                 '<path d="M9.2 9.2L5.8 5.8M14.8 9.2l3.4-3.4M9.2 14.8l-3.4 3.4M14.8 14.8l3.4 3.4"/>'),
    'extra_time': EXTRA_TIME,
    'marathon': '<path d="M6 2.5h12M6 21.5h12M7 2.5c0 5.5 10 6 10 9.5s-10 4-10 9.5M17 2.5c0 5.5-10 6-10 9.5s10 4 10 9.5"/>'
                '<path d="M9.5 19.5h5" stroke-width="1.2"/>',
    'golden_goal': GOLDEN,
    'powerplay': POWERPLAY,
    'shorthanded': '<path d="M11.5 2.5L4 12.5h5.8l-1 7.5 7.6-10H10.6z"/><path d="M16.5 19.5h5.5"/>',
    'penalty_kill': PENALTY_KILL,
    'hat_trick': ball(6.6, 16.6, 4.6) + ball(17.4, 16.6, 4.6) + ball(12, 7.2, 4.6),  # apart: filled balls must not touch
    'natural_hat_trick': ball(4.6, 11, 3.4) + ball(12, 11, 3.4) + ball(19.4, 11, 3.4) + '<path d="M3 18.5h18"/>',
    'quadruple': ball(7.2, 7.2, 4.2) + ball(16.8, 7.2, 4.2) + ball(7.2, 16.8, 4.2) + ball(16.8, 16.8, 4.2),  # apart: filled balls must not touch
    'natural_quadruple': ball(3.9, 11, 2.7) + ball(9.3, 11, 2.7) + ball(14.7, 11, 2.7) + ball(20.1, 11, 2.7) + '<path d="M3 18.5h18"/>',
    'clean_sheet': goal_frame(3, 6.5, 18, 11),
    'big_win': '<path d="M3 20.5h18"/><rect x="5" y="4" width="5.5" height="16.5" rx="1"/><rect x="14" y="15" width="5.5" height="5.5" rx="1"/>',
    'fair_play': ('<path d="M2 9.5l4-3h3.5l2.5 1.8"/><path d="M22 9.5l-4-3h-3.2l-5.4 4.3a1.5 1.5 0 0 0 2 2.2l3-2.1 4.6 4.4"/>'
                  '<path d="M6 6.5v8.5l4.8 4a1.4 1.4 0 0 0 2-2"/><path d="M12.6 15.3l2.3 2.3a1.4 1.4 0 0 0 2-2l-1.7-1.8"/>'),
    # --- match labels
    'thriller': THRILLER,
    'seesaw': '<path d="M3 8.5l18 7"/><path d="M12 12.2l-3.6 8.3h7.2z"/><circle cx="5" cy="5.3" r="1.8"/>',
    'goal_fest': ball(8.5, 15, 5.5) + '<circle cx="17.5" cy="7" r="2.6"/><path d="M19 15.5l1.5 1.5M14 2.5v2M21 10.5h2M4 3.5l1.5 1.5"/>',
    'chess_match': ('<circle cx="12" cy="5.8" r="2.9"/><path d="M8.8 10.2h6.4"/>'
                    '<path d="M10.2 10.2c0 3.6-1.3 6.2-3 8.3h9.6c-1.7-2.1-3-4.7-3-8.3"/><path d="M5.5 21h13"/>'),
    # --- referees (base pictograms, the whistle badge is added below)
    'whistle': WHISTLE,
    'nocard': NO_CARD,
    'calm': '<path d="M2.5 9h19M6 13.5h12M9.5 18h5"/>',  # flat water: a horizon and its shortening reflections, no wave
    'knockout': '<path d="M3 4.5h5.5v15H3M8.5 12h5M13.5 6.5h7.5M13.5 6.5v11M13.5 17.5h7.5"/>',
    # --- statistics
    'medal': MEDAL,
    'goal_timing': BARS,
    'discipline': DISCIPLINE,
    'highlights': '<circle cx="12" cy="12" r="9"/><path d="M10 8.3v7.4l5.8-3.7z"/>',
    'card_balance': '<path d="M12 4v16.5M7.5 20.5h9M4 7h16"/><path d="M4 7l-2.6 6a2.6 2.6 0 0 0 5.2 0zM20 7l-2.6 6a2.6 2.6 0 0 0 5.2 0z"/>',
    'card_reasons': '<rect x="2.5" y="4" width="8.5" height="13" rx="1.5"/><path d="M14 7h7.5M14 11h7.5M14 15h5"/>',
    'clock': clock(12, 13, 8),
}

# A badge in the bottom-right corner: the base is cut out under it (mask), so both stay legible at 16 px.
BADGES = {'whistle': WHISTLE, 'percent': PERCENT, 'bars': BARS, 'person': PERSON, 'kayak': KAYAK, 'team': TEAM, 'one': ONE, 'card': CARD}
COMPOSED = {
    'ref_smooth_game': ('nocard', 'whistle'), 'ref_calm_game': ('calm', 'whistle'), 'ref_knockout': ('knockout', 'whistle'),
    'ref_extra_time': ('extra_time', 'whistle'), 'ref_thriller': ('thriller', 'whistle'),
    'ref_golden_goal': ('golden_goal', 'whistle'),
    'medals_teams': ('medal', 'team'), 'medals_players': ('medal', 'person'), 'medals_referees': ('medal', 'whistle'),
    'opener_wins': ('opener', 'percent'), 'powerplay_rate': ('powerplay', 'percent'),
    'penalty_kill_rate': ('penalty_kill', 'percent'), 'first_goal_time': ('clock', 'one'),
    'comebacks': ('comeback', 'bars'), 'overtimes': ('extra_time', 'bars'),
    'referee_games': ('whistle', 'bars'), 'referee_cards': ('discipline', 'whistle'),
    'referee_smooth_rate': ('nocard', 'percent'), 'referee_card_timing': ('clock', 'card'),
    'referee_card_balance': ('card_balance', 'whistle'), 'referee_card_reasons': ('card_reasons', 'whistle'),
    'referee_powerplay': ('powerplay', 'whistle'), 'referee_pairs': ('whistle', 'whistle'),
}

# One generic pictogram per family, shown until an element has its own (§ 7.1).
FAMILIES = {
    'elan': 'wire_to_wire', 'reaction': 'bounce_back', 'endurance': 'clock', 'superiorite': 'powerplay',
    'finition': 'goal', 'defense': 'clean_sheet', 'esprit': 'fair_play', 'arbitrage': 'whistle',
    'match': 'thriller', 'analyse': 'goal_timing',
}


def inner(code):
    """SVG content (no <svg> wrapper) of a pictogram."""
    if code in COMPOSED:
        base, badge = COMPOSED[code]
        mask = f'm-{code}'
        return (f'<mask id="{mask}"><rect width="24" height="24" fill="#fff" stroke="none"/><circle cx="18" cy="18" r="7.2" fill="#000" stroke="none"/></mask>'
                f'<g mask="url(#{mask})">{ICONS[base]}</g>'
                f'<g transform="translate(12.3 12.3) scale(.475)" stroke-width="3.6">{BADGES[badge]}</g>')
    return ICONS[code]


def all_codes():
    return [c for c in ICONS if c not in ('medal', 'clock', 'card_balance', 'card_reasons', 'knockout', 'nocard', 'calm')] + list(COMPOSED)


def svg(code, title=None):
    t = f'<title>{title}</title>' if title else ''
    return f'<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" {STROKE}>{t}{inner(code)}</svg>\n'
