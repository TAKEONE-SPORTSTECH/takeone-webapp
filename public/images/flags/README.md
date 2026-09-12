# Country flags — self-hosted

4×3 SVGs from **flag-icons** 7.2.3 (MIT, © Panayiotis Lipiridis), vendored here
on 2026-09-12 and served from this box. The licence is beside them.

## Why they are files and not emoji

The platform drew flags as emoji (`🇧🇭`) built from the ISO-2 code. That works on
iOS, Android and macOS and renders as the LETTERS "BH" on Windows, which has no
flag glyphs in its emoji font — so an organiser on a Windows laptop, which is
what runs most scoring tables, saw two capital letters where every competitor's
country should be.

An SVG renders identically on every device, prints, and survives a screenshot.

## Using them

Never build the path by hand — `<x-flag :code="$iso" />` resolves it, falls back
to the code when a country has no file, and is the one place the size and the
rounding are decided.

## Updating

Replace the SVGs from the same package and keep the licence file. Nothing else
reads this directory.
