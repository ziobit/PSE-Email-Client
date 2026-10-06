#!/usr/bin/env python3
"""Validate deployable PNG and Windows ICO assets using only the standard library."""
from pathlib import Path
import struct
import zlib

ROOT = Path(__file__).resolve().parents[1]


def png_size(raw):
  assert raw[:8] == b"\x89PNG\r\n\x1a\n", "Invalid PNG signature"
  position = 8
  dimensions = None
  ended = False
  while position < len(raw):
    length = struct.unpack_from(">I", raw, position)[0]
    kind = raw[position + 4:position + 8]
    body = raw[position + 8:position + 8 + length]
    assert len(body) == length, "Truncated PNG chunk"
    checksum = struct.unpack_from(">I", raw, position + 8 + length)[0]
    assert zlib.crc32(kind + body) & 0xffffffff == checksum, "Invalid PNG checksum"
    if kind == b"IHDR":
      dimensions = struct.unpack_from(">II", body)
    position += 12 + length
    if kind == b"IEND":
      ended = True
      break
  assert ended and position == len(raw) and dimensions, "Incomplete PNG"
  return dimensions


for size in (16, 32, 48, 64, 128, 192, 256, 512):
  raw = (ROOT / "assets" / f"pse-black-cat-{size}.png").read_bytes()
  assert png_size(raw) == (size, size), f"Incorrect {size}px PNG dimensions"

ico = (ROOT / "assets" / "pse-black-cat.ico").read_bytes()
reserved, kind, count = struct.unpack_from("<HHH", ico)
assert (reserved, kind, count) == (0, 1, 6), "Invalid ICO header"
sizes = []
for number in range(count):
  width, height, colors, reserved, planes, bits, length, offset = struct.unpack_from("<BBBBHHII", ico, 6 + number * 16)
  size = width or 256
  assert (height or 256) == size and reserved == 0 and planes == 1
  assert offset >= 6 + count * 16 and offset + length <= len(ico), "Invalid ICO frame range"
  frame = ico[offset:offset + length]
  assert png_size(frame) == (size, size), "ICO directory disagrees with frame dimensions"
  assert frame == (ROOT / "assets" / f"pse-black-cat-{size}.png").read_bytes()
  sizes.append(size)
assert sizes == [16, 32, 48, 64, 128, 256]
print("PNG dimensions and CRCs, and all six Windows ICO frames passed.")
