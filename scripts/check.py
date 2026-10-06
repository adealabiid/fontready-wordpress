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
sfnt = fixture()
import struct
strings = b''.join(b'\0\0' + struct.pack('<H', len(v)) + v for v in (t.encode('utf-16le') for t in ['Grouped Format Font','Regular','Version 1','Grouped Format Font']))
eot_header = struct.pack('<IIII10sBBIHH7I4I',80+len(strings)+len(sfnt),len(sfnt),0x10000,0,b'\0'*10,1,0,400,0,0x504C,*([0]*11))
svg = b'<svg xmlns="http://www.w3.org/2000/svg"><defs><font id="GroupedFormatFont" horiz-adv-x="600"><font-face font-family="Grouped Format Font" units-per-em="1000"/><glyph unicode="A" d="M0 0L10 10"/></font></defs></svg>'
grouped = []
for fmt in ('woff2','woff','ttf','svg','eot'):
    if fmt == 'svg': data = svg
    elif fmt == 'eot': data = eot_header + strings + sfnt
    else:
        with TTFont(io.BytesIO(sfnt)) as font:
            font.flavor = fmt if fmt in ('woff','woff2') else None
            output = io.BytesIO(); font.save(output); data = output.getvalue()
    grouped.append({'family':'Grouped Format Font','weight':'400','style':'normal','format':fmt,'data':base64.b64encode(data).decode()})
with tempfile.TemporaryDirectory() as directory:
    target = Path(directory) / 'fixture.json'
    target.write_text(json.dumps({'version': 1, 'fonts': fonts, 'elementor_fonts':grouped}))
    subprocess.run([php, '-n', str(root / 'tests' / 'plugin.php'), str(target)], check=True)
