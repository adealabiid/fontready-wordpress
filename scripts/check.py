"""Real font fixtures against isolated PHP contracts, without Django."""
import base64
import io
import json
import os
from pathlib import Path
import subprocess
import tempfile
from fontTools.fontBuilder import FontBuilder
from fontTools.pens.ttGlyphPen import TTGlyphPen
from fontTools.pens.t2CharStringPen import T2CharStringPen
from fontTools.ttLib import TTFont

root = Path(__file__).resolve().parent.parent
php = os.getenv('PHP_BINARY', 'php')
for source in (root / 'fontready').rglob('*.php'):
    subprocess.run([php, '-n', '-l', str(source)], check=True)

def fixture(ttf=True):
    fb = FontBuilder(1000, isTTF=ttf)
    fb.setupGlyphOrder(['.notdef', 'A'])
    fb.setupCharacterMap({65: 'A'})
    glyphs = {}
    for name in ['.notdef', 'A']:
        pen = TTGlyphPen(None) if ttf else T2CharStringPen(600, None)
        if name == 'A':
            pen.moveTo((100, 0)); pen.lineTo((300, 700)); pen.lineTo((500, 0)); pen.closePath()
        glyphs[name] = pen.glyph() if ttf else pen.getCharString()
    if ttf:
        fb.setupGlyf(glyphs)
    else:
        fb.setupCFF('FontReadyTest', {'FullName': 'FontReady Test', 'FamilyName': 'FontReady Test', 'Weight': 'Regular'}, glyphs, {})
    fb.setupHorizontalMetrics({name: (600, 0) for name in glyphs})
    fb.setupHorizontalHeader(ascent=800, descent=-200)
    fb.setupNameTable({'familyName': 'FontReady Test', 'styleName': 'Regular', 'uniqueFontIdentifier': 'FRTest', 'fullName': 'FontReady Test', 'psName': 'FontReadyTest'})
    fb.setupOS2(sTypoAscender=800, sTypoDescender=-200, usWinAscent=800, usWinDescent=200)
    fb.setupPost(); fb.setupMaxp()
    output = io.BytesIO(); fb.save(output)
    return output.getvalue()

fonts = []
for index, fmt in enumerate(('woff2', 'woff', 'ttf', 'otf')):
    with TTFont(io.BytesIO(fixture(fmt != 'otf'))) as font:
        font.flavor = fmt if fmt in ('woff', 'woff2') else None
        output = io.BytesIO(); font.save(output)
    fonts.append({'family': 'FontReady Test', 'weight': str((index+1)*100), 'style': 'normal', 'format': fmt, 'data': base64.b64encode(output.getvalue()).decode()})
with tempfile.TemporaryDirectory() as directory:
    target = Path(directory) / 'fixture.json'
    target.write_text(json.dumps({'version': 1, 'fonts': fonts}))
    subprocess.run([php, '-n', str(root / 'tests' / 'plugin.php'), str(target)], check=True)
