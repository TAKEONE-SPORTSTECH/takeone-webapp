# Changelog

## Unreleased — 2026-09-30

- **Arrange the page.** `certificate_templates.layout` (JSON, additive migration): per element x/y/w/h in mm, type
  scale, colour, alignment, hidden (`Support\Layout` — defaults reproduce the classic sheet, are mirrored for RTL,
  and only the changes are stored). The view is now absolutely positioned from it.
- **Readable on any background.** `Support\Contrast` samples the background under each element and keeps its
  intended colour only when it meets WCAG contrast (4.5:1, 3:1 for large type); otherwise dark or white ink.
  A colour chosen by hand is never changed. An optional **text panel** element sits behind the text.
- **Never cut a name.** `Support\Fit` sets a one-line element smaller when it is longer than its box.
- The ribbon hugs its words; an artwork background chosen but not yet uploaded draws the classic sheet.

## 1.1.0 — 2026-09-29

Additive: a 1.0 install keeps working unchanged (its templates become the application-wide ones).

- **Templates belong to owners.** `certificate_templates` gains `owner_type`/`owner_id` (`''`/`0` = application-wide,
  which every 1.0 template is) and a unique key on (owner, type) instead of type alone.
  `CertificateTemplate::forType($type, $owner)` and `::resolve($type, $chain)`; a Host may implement
  `Contracts\OwnsTemplates::templateOwners($context)` to give a chain (most specific first).
- **Backgrounds.** `background`: `plain` · `guilloche` · `watermark` · `motif` (drawn by `Support\Backgrounds` with GD, cached
  by `backgrounds_cache`) · `artwork` (FileStore slot `background`) · `blank` (pre-printed paper — honoured only when
  printing; the stored download falls back to `plain`). `print_offset_x/y` (mm, ±30) line blank paper up. `accent` per template.
- **Print vs download.** `CertificateService::printContents()` renders for the paper; `render($c, $purpose)`.
- **PNG rendering.** `Contracts\ImageRenderer` (implemented by `DompdfRenderer` through dompdf's GD backend):
  `imageContents($certificate)` and `preview(..., $imageDpi)` return PNG — for screens that cannot show a PDF inline.
- **Arabic out of the box.** `Support\TextShaper` joins and orders Arabic with `khaled.alshamaa/ar-php` (suggested) and the
  default view prints shaped lines without letting dompdf re-wrap them.
- **Fonts bundled** (SIL OFL, licences beside them): Cormorant Garamond, Inter, Amiri. `DompdfRenderer` keeps remote loading
  off and chroots dompdf to the font folder; `font_cache` must be writable.
- **Placed certificates.** `details.place` (1–3) draws a medal (`Support\Medal`) and a ribbon (`certificates.places.*`).
- **`Contracts\BrandsCertificates`** (optional): accent colour, watermark logo, top logos, seal word, preview details.
- **UUIDs** on certificates and templates, for hosts that address them in URLs.
- Serial numbers retry on a unique collision (two drafts made at the same moment).
- With `routes.enabled` off, the package no longer binds the `{certificate}` route parameter globally.
- The default view is rebuilt for dompdf (tables and absolute boxes; no flexbox/gradients).
