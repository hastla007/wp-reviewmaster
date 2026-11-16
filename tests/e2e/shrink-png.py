#!/usr/bin/env python3
"""Verkleinert Screenshots (Palette 128 Farben), damit docs/qa/screenshots klein bleibt. Aufruf: python3 -I shrink-png.py <dir>"""
import sys, pathlib
from PIL import Image
for p in pathlib.Path(sys.argv[1]).glob('*.png'):
    im = Image.open(p).convert('RGB').quantize(colors=128, method=Image.Quantize.MEDIANCUT)
    im.save(p, optimize=True)
