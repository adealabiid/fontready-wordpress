"""Render the existing FontReady SVG mark into directory GIFs and PNG fallbacks.
Install requirements-assets.txt. Directory assets stay outside the plugin ZIP.
"""
from pathlib import Path
import io
import math
import cairosvg
from PIL import Image
root = Path(__file__).resolve().parent.parent
output = root / 'assets'
output.mkdir(exist_ok=True)
svg = '''<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 40 40"><rect width="40" height="40" rx="11" fill="#7256e8"/><path d="M11 29V11h16v5H16v4h8v5h-8v4Z" fill="white"/><path d="m24 29 3 3 7-8" fill="none" stroke="white" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" {animation}/></svg>'''
(output / 'icon.svg').write_text(svg.format(animation=''))
for size in (128,256):
    def render(animation=''):
        raw=cairosvg.svg2png(bytestring=svg.format(animation=animation).encode(),output_width=size*2,output_height=size*2)
        return Image.open(io.BytesIO(raw)).convert('RGBA').resize((size,size),Image.Resampling.LANCZOS)
    still=render()
    still.save(output/f'icon-{size}x{size}.png')
    frames=[still]
    durations=[900]
    # Reveal the check gently, then hold the complete logo before the next cycle.
    for i in range(13):
        fraction=i/12
        length=(math.sqrt(18)+math.sqrt(113))*fraction
        frame=render(f'stroke-dasharray="{length:.3f} 30"')
        frames.append(frame);durations.append(60)
    frames.append(still);durations.append(1600)
    # Opaque canvas avoids GIF disposal artefacts on light or dark listings.
    rgb=[]
    for frame in frames:
        canvas=Image.new('RGB',frame.size,'#ffffff');canvas.paste(frame,mask=frame.getchannel('A'));rgb.append(canvas)
    palette=rgb[0].quantize(colors=128)
    indexed=[frame.quantize(palette=palette,dither=Image.Dither.NONE) for frame in rgb]
    indexed[0].save(output/f'icon-{size}x{size}.gif',save_all=True,append_images=indexed[1:],duration=durations,loop=0,optimize=False,disposal=2)
    print(size, 'GIF bytes:',(output/f'icon-{size}x{size}.gif').stat().st_size)
