# Claude Code Prompt — Rebuild a Canva-style Design Editor (exact layout, behaviour & features)

> Paste everything below into Claude Code. It is based on a hands-on study of a live Canva editor session (certificate design, A4 landscape): its screen layout, its rendered HTML/DOM, every transform tool, panels, menus and keyboard behaviour. My previous attempt looked and behaved poorly — this spec exists so the rebuild matches the reference precisely. Use our own product name and branding; do not copy Canva's logo, wordmark or assets.

---

## 0. Role, goal and ground rules

You are building a browser-based design editor that replicates the layout, interaction model and feature set described here. Fidelity of **behaviour** (how dragging, resizing, rotating, selecting, editing and panels feel) matters more than feature count. Where this spec gives numbers, use them. Where it says "not studied", implement the standard design-tool behaviour and flag it in your summary.

- First inspect the existing repository (my earlier attempt). Reuse what is sound, replace what contradicts this spec. Tell me what you kept and what you replaced.
- If no stack exists, use: **React 18 + TypeScript + Vite**, **Zustand** (with an immer-style history middleware) for state, plain CSS modules or Tailwind for styling, **no canvas library** — the page is rendered as DOM (see §2).
- Build in the phases of §13. After each phase, run the app, take screenshots with Playwright, and check them against the acceptance tests in §14 before moving on.
- Keep the document model serialisable to JSON (save/load/autosave).

---

## 1. Screen layout (exact geometry, CSS px, reference viewport 2668×1212 @ dpr 1)

```
┌──────────────────────────────── HEADER 57px (full width, gradient) ───────────────────────────────┐
├──────┬───────────────────────────────────────────────────────────────────────────────────────────┤
│ RAIL │  [side panel slot, ~360px, hidden by default]   MAIN EDITING AREA (grey #F2F3F5-ish)         │
│ 72px │                    ┌──── contextual toolbar (floating pill, y≈65, h40) ────┐               │
│      │                    └───────────────────────────────────────────────────────┘               │
│      │                         [page controls: 🔒 ⧉ ＋ at page top-right, above page]            │
│      │                ┌────────────────────── PAGE (white) ──────────────────────┐                │
│      │                │                                                          │                │
│      │                └──────────────────────────────────────────────────────────┘                │
│      │                [──────────────── + Add page ────────────────][▾]                           │
├──────┴───────────────────────────────────────────────────────────────────────────────────────────┤
│ FOOTER 40px: Notes · Timer ............................ zoom slider · 119% ▾ · Pages · 1/1 · ⊞ · ⛶ · ? │
└───────────────────────────────────────────────────────────────────────────────────────────────────┘
```

| Region | Element | Geometry / notes |
|---|---|---|
| Header | `<header>` | x0 y0, full width × **57px**. Horizontal gradient teal (#00C4CC) → violet (#7D2AE8). White text/icons. |
| Left rail | `<aside>` containing `role="tablist" aria-label="Side Panel"` | x0 y57, **72px** wide, full height. 9 icon+label tabs, ~72px pitch, starting y≈65. |
| Main | `<main>` | x72 y57, fills the rest down to the footer. Light grey background. Holds side panel slot, toolbars, page, overlays. |
| Footer | `<footer>` | bottom, **40px** tall, starts at x72. |
| Contextual toolbar | `role="toolbar" aria-label="Selected element common controls"` | Floating white pill with shadow, centred horizontally over the **page** (not the viewport), top ≈ y65, height 40. Its contents and width change with the selection (§6). |
| Floating mini-toolbar | second `role="toolbar"` with the same label | White pill, height 36, centred above the selection box, ~10–14px gap above the box top. |
| Page | DOM node scaled by zoom | Centred in the available main area. At "Fit" zoom (≈119% here) the A4-landscape page is 1339×947 CSS px at origin (700,146). |
| Page controls | Lock page · Duplicate page · Add page | Small icon buttons right-aligned just above the page's top-right corner. |
| Add-page bar | "+ Add page" full page width, plus "▾" (Add page type) | Directly below the page. |

**Layout reflow rule (important):** opening a side panel does *not* overlay the canvas — it **shrinks the main editing area and the page re-centres** in the remaining space (standard panel ≈ 360px wide → the page shifts ~180px right; the two-column Brand panel is wider and shifts it more). Closing it re-centres again. The Tools strip and Notes are exceptions (see §8). Every consumer of screen coordinates must recompute after any layout change.

---

## 2. Document model & rendering (mirror the reference DOM)

The reference renders the page as **plain DOM, not `<canvas>`**. Replicate this structure:

```
main › … › div.page-viewport
          └─ div.page   style="transform: scale(<zoom>)"  (transform-origin: top left)
               ├─ [0] background layer        (page fill colour / background image)
               ├─ [1] safe-area / margin box  (invisible guide)
               ├─ [2…n] one wrapper per element, in z-order (later = in front)
               │     └─ div.element  position:absolute;
               │                     width:<w>px; height:<h>px;          (design px)
               │                     transform: translate(<x>px,<y>px) rotate(<deg>deg) [scale(-1,1) for flips];
               │                     transform-origin: center;           (rotation pivots on centre)
               │                     opacity: <0..1>
               │          └─ content: <p><span>… for text  |  <img> for graphics/photos  |  <svg> for shapes & lines
```

- **Design units:** pixels at **96 dpi**. A4 landscape = **1122.5 × 793.7 px** (297×210 mm). 1 mm = 3.7795 px. The UI may display mm for print formats; store px.
- **Zoom** is a single `scale()` on `.page`. Screen position of an element = pageOrigin + translate × zoom.
- **Z-order = array order** of `page.elements`. Layer operations reorder the array.
- **Groups** are a single wrapper containing child elements with coordinates relative to the group box (in the reference, a signature image + "Chief Executive Officer" text live inside one wrapper).
- **Text** is real HTML: `<p>` (text-align, line-height) with `<span>` runs (font-family, font-size in px, font-weight, colour, letter-spacing, decoration, case). Lines wrap inside the box width; box height is **auto** (derived from content) — the Height field is greyed out for text.
- **Shapes** are `<svg>` with paths + clipPath; they also host an optional centred text body (shape text).
- Fonts load as web fonts; keep a font registry (family name ↔ loaded face).

```ts
type Element = {
  id: string; type: 'text'|'image'|'shape'|'line'|'group';
  x: number; y: number; width: number; height: number;   // design px, top-left of unrotated box
  rotation: number;          // degrees, positive = clockwise
  flipX: boolean; flipY: boolean;
  opacity: number;           // UI "Transparency" 0–100, 100 = fully opaque
  locked: boolean;
  // text
  html?: TextRun[][]; fontFamily?: string; fontSize?: number; fontWeight?: number; color?: string;
  align?: 'left'|'center'|'right'|'justify'; lineHeight?: number; letterSpacing?: number;
  bold?, italic?, underline?, strike?, uppercase?, list?: 'none'|'bullet'|'number';
  // image / svg graphic
  src?: string; recolorMap?: Record<string,string>;   // original colour → replacement
  // shape
  shape?: 'rect'|'polygon'|'ellipse'|...; fill?: string; stroke?: {color,width,style};
  cornerRadius?: number; sides?: number; text?: TextBody;
  // group
  children?: Element[];
};
type Page = { id; width; height; background; elements: Element[]; locked: boolean; notes: string; title?: string };
type Doc  = { id; title; unit: 'mm'|'px'; pages: Page[] };
```

---

## 3. Selection state machine (get this exactly right)

States: `none` → `page` → `element(s)` → `editingText`.

| From | Input | Result |
|---|---|---|
| any | click empty grey area outside the page | `none` (no selection) |
| any | click an element | select the **front-most** element under the pointer (hit-test by z-order, respecting rotation) |
| element (text or shape) | click the **already-selected** element again | `editingText`, **all text highlighted** |
| element | double-click text | `editingText` with caret at click point |
| element | **Enter** | `editingText` on that element (works for shapes with shape-text) |
| editingText | **Escape** | back to `element` (box selected, handles visible) |
| element | **Escape** | `page` selected (page toolbar shown, page outline highlighted) |
| page | **Escape** | `none` |
| element | **Shift+click** another | multi-selection (dashed bounding box around all, each member outlined) |
| multi | Shift+click a member | remove it from selection |
| any | drag-marquee on empty canvas | marquee multi-select (standard; not studied) |

- While `editingText`, **all single-key and modifier shortcuts are disabled** and keystrokes go into the text. (The reference typed a literal "]" into a shape when a layer shortcut was pressed in edit mode — avoid ambiguity: shortcuts never fire in edit mode, Esc first.)
- Hover over an unselected element shows a thin violet outline. Hover over the page shows a violet page outline.
- A **selected element is previewed on top** of everything while selected (so the user sees it), but its true layer order is unchanged; on deselect it returns to its z-position.
- Selection colour: violet **#8B3DFF**; dashed variant for multi-selection.

---

## 4. Transform tools — exact behaviour

### 4.1 Handles (DOM overlay, separate from the page)
Render handles in an overlay layer above `.page`, in screen space, each with an accessible label:

- `Resize selection from top left corner` / `top right` / `bottom left` / `bottom right` — hit area **32×32**, visual white circle with violet border.
- `Resize selection from left edge` / `right edge` (and `top edge` / `bottom edge` for shapes, images, groups) — side hit areas **32 × (edge length − corners)**, visual small pill.
- **Text boxes show only corners + left/right edges** (no top/bottom).
- `Rotate selection` — 32×32 circular button with a rotate-arrows icon, centred horizontally **below** the box, ~30px below the bottom edge (it follows the box when rotated).
- Text boxes additionally show a **Move (✥) handle** next to the rotate handle.
- The overlay rotates with the element (handles sit on the rotated box corners).

### 4.2 Move
- Drag anywhere on a selected (or unselected → selects then drags) element. Movement is **1:1 with the pointer**, preserving the grab offset.
- During drag: show alignment guides and snap (to page centre/edges, other elements' edges/centres, equal spacing) — standard behaviour, not directly observed; implement with a ~5 screen-px snap threshold and magenta/violet guide lines.
- Elements may be dragged partially/fully off the page; **content outside the page bounds is clipped** (`overflow:hidden` on the page) but the selection box/handles remain fully visible outside it.

### 4.3 Resize
| Element | Corner handle | Side handle |
|---|---|---|
| Image / graphic | **Proportional** (aspect locked), opposite corner anchored | Not shown for graphics (images: crop-style stretch – standard) |
| Shape | Proportional | **Free one-axis stretch**, opposite edge anchored |
| Text | **Scales the text**: box AND font size scale together proportionally (e.g., 16 → 21.4). Fractional sizes allowed (1 decimal shown) | **Changes width only**; text rewraps; height auto-adjusts; the opposite edge is anchored (dragging the right edge left keeps the left edge fixed, box grows downward) |
| Group / multi | Proportional, children scale (text font sizes scale) | Standard |

Shift on corner = toggle free/proportional (standard). Alt = resize from centre (standard).

### 4.4 Rotate
- Drag the rotate handle: rotation angle = angle of the pointer around the element **centre**, relative to the starting angle. Dragging the handle from "below" toward the right turns the element **counter-clockwise** (negative degrees); toward the left turns it clockwise.
- Show a live angle tooltip; snap to 0/45/90… within ~5° (standard).
- Numeric rotation in the Position panel: positive = clockwise (e.g., −55° → 15°).

### 4.5 Keyboard nudge
- Arrow = move **1 unit** (1 mm in mm documents / 1 px in px docs). **Shift+Arrow = 10 units.**
- While nudging, the handles/selection box hide briefly and reappear after the keys stop.

### 4.6 Flip
`Flip ▾` → **Flip horizontal / Flip vertical**. Flips the content **inside the current (possibly rotated) frame**; frame rotation is unchanged.

---

## 5. Position panel (opens in the left side-panel slot)

Header "Position" + close ✕. Tabs: **Arrange | Layers**. Opening it reflows the canvas like any side panel. It remembers the last tab.

**Arrange tab**
- Layer order buttons (2×2): **Forward**, **Backward**, **To front**, **To back** — disabled when not applicable.
- **Align to page** (2×3): Top, Left, Middle, Center, Bottom, Right (with multi-select the label becomes "Align elements" + Distribute — standard).
- **Advanced**: `Width`, `Height`, `Ratio` (lock toggle icon), `X`, `Y`, `Rotate` — editable numeric fields with units ("39.47 mm", "-55°"). Select-all on focus, apply on **Enter** or blur.
  - Changing Width with ratio locked updates Height and keeps the element **centred on its previous centre**.
  - Height is disabled for text (auto).

**Layers tab**
- Filter pills: **All | Overlapping**.
- Vertical list of layer cards, **front-most at the top**. Each card shows a mini preview (text rendered in its font, image thumbnail) and a ⋮⋮ drag handle on the left. The selected element's card is highlighted with a violet border. Drag cards to reorder; click a card to select that element.

---

## 6. Contextual toolbar (top floating pill) — contents per selection

Re-render on every selection/mode change; its width and button positions change, so never cache coordinates.

| Selection | Buttons, left → right |
|---|---|
| **Multi-select (mixed)** | Ask AI · border colour · border style · corner · *font family ("Multiple fonts")* · size −/field/+ · text colour · B I U S aA · align · list · spacing · transparency · Animate · Position · copy-style (paint roller) |
| **Graphic (SVG/image)** | Edit · Replace · **one swatch per distinct colour in the graphic** · border style · corner rounding · Crop · Flip ▾ · Transparency · Animate · Position · (Style) · copy-style |
| **Text (box selected or editing)** | Font family dropdown · size **−** [field] **+** · text colour (A with colour bar) · **B** · *I* · U · S · aA (case) · align (cycles left/centre/right/justify) · list (bullets/numbers) · spacing (letter & line) · transparency · **Effects** · Animate · Position · copy-style |
| **Shape** | Ask AI · Edit · fill swatch · border style · **corner rounding** · *(+ text controls when its text is being edited: font, size, colour, B I U S aA, align, list, spacing)* · transparency · Effects · Animate · Position · copy-style |
| **Page** | Ask AI · Edit · Replace · background colour (rainbow swatch) · Animate · Position · (page timing/AI icons) |

Behaviours:
- Size **+/−** step = 1. The field accepts decimals.
- Text box **auto-widens** when content grows (single-line boxes) and centred text stays centred about the box centre.
- Applying a font to selected text when other text uses the old font shows a bottom bar in the font panel: "**OldFont → NewFont  [Change all]**".

### 6.1 Popovers (anchored under their toolbar button, ~8px gap)
- **Transparency**: label + slider (0–100, violet fill) + numeric field. 100 = opaque.
- **Corner rounding** (shapes): "Corner rounding" slider + field, and "**Sides**" slider + field (3+; 4 = rectangle → polygon).
- **Flip**: menu with two items.
- **Border style**: none/solid/dashed/dotted, weight slider, (standard).
- Popovers close on Escape or click outside; **the first click outside only dismisses** the popover (it is not passed to the canvas).

### 6.2 Floating mini-toolbar (above the selection)
- Single element: **Ask AI · Comment · Lock · Duplicate · Delete · ⋯ More**.
- Multi-select: **Group** · Lock · Duplicate · Delete · ⋯
- Group selected: **Ungroup** · …
- Page: lock / duplicate / add icons.

### 6.3 "⋯ More" menu (element context menu)
Copy (Ctrl+C) · Copy style (Ctrl+Alt+C) · Paste (Ctrl+V) · Duplicate (Ctrl+D) · Delete (DELETE) · ─ · Layer ▸ · Align to page ▸ · ─ · Group/Ungroup (Ctrl+G / Ctrl+Shift+G) · Resize canvas to selection · Create component · ─ · Comment (Ctrl+Alt+N) · Lock (Alt+Shift+L) · Show element timing · Download selection · Translate text. Each row: icon, label, right-aligned shortcut, submenu chevron where relevant. Also open this menu on right-click on an element.

---

## 7. Colour & font panels (left side-panel slot)

### 7.1 Colour panel
- Header "Color" + list-view icon + ✕. Search field: *Try "blue" or "#00c4cc"* (accepts names and hex).
- **Graphic colours / Document colours** row: first a rainbow "+" (custom picker: saturation/value square, hue slider, hex input), an eyedropper, then swatches of the colours in use. Current colour has a violet ring.
- "Colors in this design" swatches.
- **Brand Kit** section ("Add your brand colors").
- **Default solid colors**: grid of 7 columns × 4 rows (greys; reds/pinks/purples; teals/blues; greens/yellows/oranges) + "See all".
- Clicking a graphic's swatch in the toolbar and picking a colour **replaces every occurrence of that colour inside the graphic** (recolorMap).

### 7.2 Font panel
- Tabs **Font | Text styles**. Search *Try "Calligraphy" or "Open Sans"* + filter icon. Category chips row (horizontally scrollable).
- Sections: **Document fonts** (✓ on current), **Recommended fonts**, **Recently used**, **Brand Kit** ("Add your brand fonts"), **Popular fonts**. Families with weights have a ▸ expander. Each name is rendered in its own font.
- Footer: "Upload a font".
- **Fonts load lazily and the list re-flows as sections load** — clicks must target the item under the pointer at click time (avoid the reference's mis-click problem by not shifting items under an active pointer, or by debouncing reflow).

---

## 8. Left rail & side panels

Rail tabs (icon above label, active tab tinted violet): **AI · Templates · Elements · Text · Brand · Uploads · Tools · Projects · Apps**. Clicking an active tab closes its panel. Panels have a collapse tab "‹" on their right edge (vertically centred).

| Panel | Contents |
|---|---|
| AI | Empty-state headline "What shall we do with this design?", quick-action chips (Redesign this page · Add background · Change style), prompt box "Describe your idea" with + and mic. |
| Templates | Search (pre-filled with the design type), 2-column grid of template thumbnails, infinite scroll. |
| Elements | Search with mic, "Recommended for you" carousel + "See all", **Browse categories** grid (3 cols): Shapes, Graphics, Animations, Photos, Videos, Audio, Charts, Forms, Sheets, Tables, Frames, Grids, Mockups, 3D. |
| Text | Search fonts & combinations, **Add a text box** (primary violet button), Magic Write, Brand Kit fonts, **Default text styles**: "Add a heading" (big bold), "Add a subheading", "Add a little bit of body text", Dynamic text (Page numbers), Apps carousel, Font combinations grid. |
| Brand | Wider, two-column panel: left nav (Search, All Brand Templates, Brand Kit ▾, All assets, Guidelines, Brand templates, Logos, Colors, Fonts, Brand voice, Photos, Graphics, Icons, Charts); right content (upsell card, Manage Brand Kit: Rename/Delete). |
| Uploads | Search, **Upload files** + ⋯, Record yourself, tabs Images/Folders, masonry grid of uploads. |
| Tools | **Not a panel** — a floating vertical strip next to the rail (with ✕ above it) that does **not** reflow the canvas: Select · Draw · Shapes · Lines · Sticky notes · Text · Signature · Tables (tooltips on hover). |
| Projects | Search, "Your projects" dropdown, tabs All/Designs/Folders, designs grid, folders (Create folder, Starred). |
| Apps | Search, tabs Discover/Your apps, chips, collections, lists. |

**Adding elements:** clicking an item adds it **at the page centre**, or, if an element is selected, **directly below the selected element at its width** (observed for "Add a heading"). New text is created already in `editingText` with its placeholder fully selected so typing replaces it. New elements go to the **top** of z-order. Dragging an item from a panel onto the page drops it at the pointer (standard).

---

## 9. Header menus

- **Home** icon (link "/").
- **File** dropdown (anchored under the button, ~185px wide):
  header with design title + pencil (rename) and meta line ("Your projects • Certificate (A4 Landscape) • By <user> • 297mm × 210mm"); then
  Create new design · Upload files · Make a copy · ─ · Page view ▸ (Scroll) · Rulers and guides ▸ · ─ · Save ("All changes saved" right-aligned) · Make available offline · Star · Move · Download · Print (Ctrl+P) · Version history · Move to Trash · ─ · Settings ▸ · Accessibility ▸ · Find and replace text (Ctrl+F) · Open in desktop app · ─ · Help ▸.
- **Resize** dropdown (opens with a short delay): Resize (to another format) ▸ · Translate ▸ · Transform into Doc ▸, each with a sub-caption; upsell button.
- **Editing ▾** mode switcher: Editing (Make changes) ✓ · Commenting (Add feedback) · Viewing (Read-only). Viewing disables all editing UI.
- **Undo / Redo** buttons (disabled state when history is empty) and a **cloud save-status** icon (tooltip "All changes saved" / "Saving…").
- Right side: editable **design title** textbox (truncate with ellipsis), upsell pill, user avatar, Analytics, Comments, **Share** (white button).
- **Share** panel (dropdown anchored right): "Share design" + visitors count + settings; People with access (add people field, avatars); Access level dropdown ("Only you can access"); **Copy link** (primary); grid of actions: Download, Public view link, Template link, Present, Present and record, Copy to clipboard, Download selection, See all.

---

## 10. Footer & page controls

- Left: **Notes** (opens a notes editor in the side-panel slot *without* reflowing: "Page 1 – Add page title", textarea "Add notes to your design", counter "0/5000") · **Timer**.
- Right: zoom **slider** · **zoom % button** → menu: 300%, 200%, 125%, 100%, 75%, 50%, 25%, 10%, ─ Fit ✓, Fill · **Pages** (toggles a thumbnail strip above the footer; the page shrinks/moves up to make room; strip has page thumbs + "+" with ▾) · page counter "1 / 1" · **Grid view** (replaces the canvas with a grid of page thumbnails + an add tile; page-level toolbar above) · **Present full screen** · **Help (?)**.
- Ctrl/⌘ + wheel = zoom around the pointer; wheel = scroll pages (standard).
- Page controls above each page: **Lock page · Duplicate page · Add page**; page title on hover (standard).

---

## 11. Keyboard shortcuts (disabled while editing text)

| Keys | Action |
|---|---|
| R | Add rectangle at page centre (light blue fill, ~165×165 screen px at Fit) |
| T | Add text box |
| L | Add line |
| C | Add circle |
| Arrow / Shift+Arrow | Nudge 1 / 10 units |
| Delete / Backspace | Delete selection |
| Ctrl+D | Duplicate (small down-right offset) — the copy becomes the selection |
| Ctrl+C / Ctrl+V / Ctrl+X | Clipboard |
| Ctrl+Alt+C / Ctrl+Alt+V | Copy / paste style |
| Ctrl+G / Ctrl+Shift+G | Group / Ungroup |
| Ctrl+] / Ctrl+[ | Forward / Backward |
| Ctrl+Shift+] / Ctrl+Shift+[ | To front / To back |
| Alt+Shift+L | Lock |
| Ctrl+Z / Ctrl+Y (Ctrl+Shift+Z) | Undo / Redo |
| Ctrl+A | Select all elements (in edit mode: select all text) |
| Ctrl+B / I / U | Bold / Italic / Underline (text) |
| Ctrl+F | Find & replace text |
| Ctrl+P | Print |
| Enter | Edit text of selected element |
| Escape | Step out one selection level (§3) |
| Shift+R | Toggle rulers |

---

## 12. History, persistence, misc

- Every committed change (end of drag/resize/rotate, field Enter, colour pick, typing session) is **one undo step**. Continuous drags do not spam history. Undo restores selection too.
- Autosave (debounced ~1s) to local storage/back-end; header cloud icon reflects state.
- Lock: locked elements can be selected but not moved/resized/edited; show a lock badge.
- Comments, Animate, Effects, Crop, AI actions, Templates, Brand Kit, Apps, Share links: build the **UI shells** with working open/close and placeholder actions unless I say otherwise; transforms, text, colour, layers, panels, menus and history must be **fully functional**.

---

## 13. Build phases

1. **Shell & layout** — header, rail, main, footer, page centred & zoomed, side-panel slot with reflow, responsive re-centring. Seed the A4-landscape certificate in §15.
2. **Rendering** — DOM renderer exactly as §2; fonts; z-order; clipping.
3. **Selection & overlay** — state machine §3, hit-testing with rotation, hover outlines, handle overlay §4.1, multi-select box.
4. **Transforms** — move (+snapping guides), resize rules per type, rotate, nudge, flip; history integration.
5. **Text editing** — contenteditable inside the element, toolbar bindings, auto-height, corner-scale fonts, side-handle rewrap.
6. **Toolbars & popovers** — §6 per selection type, mini-toolbar, ⋯ menu & right-click.
7. **Panels** — Position (Arrange/Layers), Colour, Font, then rail panels §8 incl. Tools strip and Notes.
8. **Header menus, footer, shortcuts, persistence**.
9. **Polish** — animations (panels slide ~150ms, popovers fade), focus rings, tooltips, accessibility labels identical to those in this spec.

---

## 14. Acceptance tests (automate with Playwright; take screenshots)

1. Clicking the grey area deselects; clicking the medal shows 4 corner handles, a rotate handle below it, the mini-toolbar above, and the graphic toolbar on top.
2. Dragging the medal by (+296, −230) screen px moves it exactly that much.
3. Dragging its bottom-right corner enlarges it proportionally with the top-left fixed.
4. Dragging the rotate handle right-and-up produces a negative angle; the Position panel shows it; typing 15 + Enter sets +15° (clockwise).
5. Typing Width 30 with ratio locked updates Height (≈50.6 mm for the medal) and keeps the centre.
6. Flip vertical mirrors content within the rotated frame.
7. Transparency 45 renders the element at 45% opacity.
8. Picking red for the medal's yellow swatch recolours all yellow parts.
9. Click a text box → click again → whole text highlighted; type → replaced; Esc → box selected; Esc → page selected; Esc → nothing.
10. Text corner drag scales font (16 → ~21.4); right-edge drag inward rewraps and grows height downward with the left edge fixed.
11. + button increases font size by 1; font search "Poppins" → click → applied; "Change all" bar appears.
12. Arrow = +1 mm on X; Shift+Arrow = 10 mm.
13. Align → Center puts the box's centre on the page centre.
14. Shift-click two elements → dashed box + "Group"; Group → solid box + "Ungroup"; the group moves as one.
15. Ctrl+D duplicates with an offset and selects the copy; Delete removes it.
16. R adds a rectangle at page centre; side handles stretch one axis; corner rounding 40 rounds corners; Sides slider changes polygon sides.
17. Pressing a shortcut while editing text types nothing into the document and triggers nothing (or is ignored) — no stray characters.
18. Opening any rail panel re-centres the page into the remaining space; closing restores it; the Tools strip and Notes do not move the page.
19. Layers list shows front-most first; dragging a card changes stacking visibly.
20. Undo/Redo step through each action above one at a time; redo button disables when nothing to redo.
21. Zoom menu presets and Fit/Fill work; Pages strip and Grid view toggle; File/Resize/Editing/Share menus open anchored correctly and close on Esc/outside click.

---

## 15. Seed document (for testing parity)

A4 landscape 1122.5×793.7 px, white background, elements in this z-order (x, y, w, h in design px):

| # | Type | Content | x, y, w × h | Notes |
|---|---|---|---|---|
| 2 | image | light-blue wave lines (bottom-right) | −67, 659, 1047×290 | rotate 8.5° |
| 3 | image | light-blue wave lines (top) | −70, 38, 1207×249 | rotate −10.7° |
| 4 | line | name underline | 250, 437, 622×1 | |
| 5–6 | lines | signature underlines | 246/663, 666, 225×1 | |
| 7 | image | company logo mark | 379, 68, 54×53 | |
| 8 | image | medal/ribbon | ~ 503, 577, 52×85 | centred bottom |
| 9 | image | signature 2 | 725, 538, 89×82 | |
| 10 | text | "for successfully completing the Leadership Skill Course on November 10, 2026" | 269, 461, 585 wide | Montserrat 16, centred, black |
| 11 | group | signature 1 + "Chief Executive Officer" | 143, 538, 277×125 | Montserrat 12 |
| 13 | text | "Donna Stroupe" | 100, 354, 923 wide | Parisienne 80, #004AAD, centred |
| 14 | text | "Training Coordinator" | 651, 672, 237 wide | Montserrat 12 |
| 15–16 | text | "Aldo Sitorus" / "Aaron Loeb" | 246 / 663, 638, 213 wide | Montserrat 17 |
| 17 | text | "This certificate is proudly presented to" | 208, 301, 706 wide | Montserrat ~21 |
| 18 | text | "CERTIFICATE" | 79, 144, 964 wide | heavy display sans 93.3, #004AAD, centred |
| 19 | text | "OF COMPLETION" | 79, 230, 964 wide | spaced caps, #004AAD |
| 20 | text | "BORCELLE COMPANY" | 440, 82, 432 wide | Montserrat 800, 22.1, #003878, left |
| — | shape | dark-navy wave footer (bottom-left) | bottom band | gradient navy → royal blue, lighter blue edge |

---

## 16. Pitfalls observed in the reference — make sure our build handles them well

- Toolbar contents/positions change by mode (element vs. text-edit), so any automation or test must re-query positions; in our UI, keep the most common buttons at stable positions where possible.
- Overlapping elements: the front-most one wins the click; a large invisible text box can steal clicks from things beneath it. Provide **Alt+click to select through / cycle beneath** and keep text boxes tight to their content.
- Lazy-loaded lists (fonts) must not shift under the pointer.
- Escape stepping (text → element → page → none) must be predictable and visible.
- Right-click on an element should open the element menu (in the reference it did not reliably — ours must).
- Shortcuts must never insert characters into text or shapes.

Deliver: working app, a short README (how to run, architecture overview, what is stubbed), and the Playwright tests from §14 passing. Report any deviations from this spec.
