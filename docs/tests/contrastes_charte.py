"""Rapports de contraste WCAG des couleurs de la charte (docs/tests/accessibilite.md, section 3).

Couleurs : palette Tailwind 4 en OKLCH, relevées dans var/tailwind/app.built.css.
Lancer : python3 docs/tests/contrastes_charte.py
"""
import math
C = {
 'white': (1, 0, 0), 'slate-50': (.984, .003, 247.858), 'slate-100': (.968, .007, 247.896), 'slate-200': (.929, .013, 255.508),
 'slate-400': (.704, .04, 256.788), 'slate-500': (.554, .046, 257.417), 'slate-600': (.446, .043, 257.281), 'slate-900': (.208, .042, 265.755),
 'teal-50': (.984, .014, 180.72), 'teal-700': (.511, .096, 186.391), 'teal-800': (.437, .078, 188.216),
 'green-50': (.982, .018, 155.826), 'green-700': (.527, .154, 150.069), 'blue-50': (.97, .014, 254.604), 'blue-700': (.488, .243, 264.376),
 'amber-50': (.987, .022, 95.277), 'amber-700': (.555, .163, 48.998), 'red-50': (.971, .013, 17.38), 'red-700': (.505, .213, 27.518),
}
def srgb_lineaire(l, c, h):
    a, b = c * math.cos(math.radians(h)), c * math.sin(math.radians(h))
    l_, m_, s_ = l + .3963377774*a + .2158037573*b, l - .1055613458*a - .0638541728*b, l - .0894841775*a - 1.2914855480*b
    l3, m3, s3 = l_**3, m_**3, s_**3
    rgb = (4.0767416621*l3 - 3.3077115913*m3 + .2309699292*s3, -1.2684380046*l3 + 2.6097574011*m3 - .3413193965*s3, -.0041960863*l3 - .7034186147*m3 + 1.7076147010*s3)
    return [min(1, max(0, v)) for v in rgb]  # ramené dans le gamut sRGB
def luminance(nom):
    r, g, b = srgb_lineaire(*C[nom]); return .2126*r + .7152*g + .0722*b
def rapport(t, f):
    a, b = sorted((luminance(t), luminance(f)), reverse=True); return (a + .05) / (b + .05)
COUPLES = [
 ('Texte principal (ink)', 'slate-900', 'white', 4.5), ('Texte principal sur le fond de page', 'slate-900', 'slate-50', 4.5),
 ('Texte secondaire (ink-muted)', 'slate-600', 'white', 4.5), ('Texte secondaire sur le fond de page', 'slate-600', 'slate-50', 4.5),
 ('Texte secondaire, badge « Annulée »', 'slate-600', 'slate-100', 4.5),
 ('Lien et texte principal (primary)', 'teal-700', 'white', 4.5), ('Bouton principal : texte blanc', 'white', 'teal-700', 4.5),
 ('Bouton principal survolé', 'white', 'teal-800', 4.5),
 ('Badge Disponible, message de succès', 'green-700', 'green-50', 4.5), ('Badge Réservé', 'blue-700', 'blue-50', 4.5),
 ('Badge Sorti, avertissement', 'amber-700', 'amber-50', 4.5), ('Badge Hors service, message d\'erreur', 'red-700', 'red-50', 4.5),
 ('Erreur sous un champ', 'red-700', 'white', 4.5),
 ('Texte indicatif (placeholder), avant', 'slate-400', 'white', 4.5), ('Texte indicatif (placeholder), après', 'slate-500', 'white', 4.5),
 ('Contour de champ, avant (élément d\'interface)', 'slate-200', 'white', 3), ('Contour de champ, après', 'slate-500', 'white', 3),
 ('Contour de champ en erreur', 'red-700', 'white', 3),
 ('Indicateur de focus (contour)', 'teal-700', 'white', 3), ('Indicateur de focus sur le fond de page', 'teal-700', 'slate-50', 3),
]
for nom, t, f, seuil in COUPLES:
    r = rapport(t, f); print(f'| {nom} | `{t}` sur `{f}` | {r:.2f}:1 | {seuil}:1 | {"Conforme" if r >= seuil else "**Non conforme**"} |')
