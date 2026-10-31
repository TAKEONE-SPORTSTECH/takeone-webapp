package bh.takeone.takeone_lab

import android.content.res.AssetManager
import android.graphics.Bitmap
import android.graphics.BitmapFactory
import android.graphics.Canvas
import android.graphics.Color
import android.graphics.LinearGradient
import android.graphics.Paint
import android.graphics.Path
import android.graphics.RadialGradient
import android.graphics.RectF
import android.graphics.Shader
import android.graphics.Typeface
import android.graphics.fonts.Font
import android.graphics.fonts.FontFamily
import android.os.Build
import android.text.TextPaint
import android.text.TextUtils
import android.util.Base64
import org.json.JSONObject

/**
 * The VS screen that opens a bout — the bout page's own intro (#vsScreen in
 * personal/{mobile,desktop}/bout-video), drawn on the phone as one still
 * frame: the two corners' panels with their photographs, the gold divider,
 * the event, round and division across the top, each fighter's corner, flag,
 * name, crest and club, the big VS, and the match and court along the bottom.
 *
 * Laid out on the page's fixed 1920×1080 canvas and scaled to the frame, so
 * the proportions are the page's. Everything it shows comes from the plan the
 * event sent (CameraController::plan); a missing photo, flag or crest leaves
 * its place quietly empty, as the page does.
 */
class VsScreen(
    private val plan: JSONObject,
    assets: AssetManager,
    /**
     * How far above the frame's bottom edge the fighters' blocks (corner tag,
     * flag and country, name, club) sit, on the 1920×1080 canvas. Low by
     * default so they stay under the faces in the photographs; the operator
     * moves them with the VS slider (ClipVault.vsLift).
     */
    private val infoBottom: Float = INFO_DEFAULT,
    /**
     * An UPRIGHT frame: the bout page's portrait VS screen (#vsScreen
     * .vs-portrait) on a 1080×1920 canvas — red panel on top, blue below, the
     * divider across, red's details top-left and blue's bottom-right.
     */
    val portrait: Boolean = false,
) {
    /** The design canvas: the page's 1920×1080, or 1080×1920 upright. */
    val W = if (portrait) 1080f else 1920f
    val H = if (portrait) 1920f else 1080f

    companion object {
        const val INFO_DEFAULT = 40f
        const val INFO_MIN = 16f
        const val INFO_MAX = 520f
        const val CHIP_H = 57f
        val RED = intArrayOf(0xFF4B1A17.toInt(), 0x8CC0392F.toInt(), 0xFFC0392F.toInt(), 0xFFE9C3BD.toInt())
        val BLUE = intArrayOf(0xFF14284D.toInt(), 0x8C2457A6.toInt(), 0xFF2457A6.toInt(), 0xFFBCCDE6.toInt())
    }


    private val red = plan.optJSONObject("red") ?: JSONObject()
    private val blue = plan.optJSONObject("blue") ?: JSONObject()
    private val vs = plan.optJSONObject("vs") ?: JSONObject()
    private val division = plan.optString("division")

    // What every other VS screen shows (the mats, the bout page) — sent by the
    // server under vs.corners / vs.layout / vs.red_right / vs.referee
    // (App\Scoreboard\Support\VsCorner). Absent on an older server: every
    // read below falls back to what this screen drew before.
    private val redX = vs.optJSONObject("corners")?.optJSONObject("red") ?: JSONObject()
    private val blueX = vs.optJSONObject("corners")?.optJSONObject("blue") ?: JSONObject()
    private val redRight = vs.optBoolean("red_right", false)
    private val showStats = (vs.optJSONObject("layout")?.optInt("show_stats", 1) ?: 1) != 0
    private val referee = vs.optString("referee").takeIf { it.isNotEmpty() && it != "null" }

    private val regular = font(assets, "BarlowCondensed-Regular.ttf", "Tajawal-Regular.ttf")
    private val bold = font(assets, "BarlowCondensed-Bold.ttf", "Tajawal-Bold.ttf")
    private val black = font(assets, "BarlowCondensed-Black.ttf", "Tajawal-Black.ttf")

    private val gold = 0xFFF0C24A.toInt()

    /** One part of the screen, drawn once, placed on the 1920×1080 canvas at `rect`. */
    class Layer(val name: String, val rect: RectF, val bitmap: Bitmap)

    /**
     * The screen in its parts — what the animation moves (VsAnim): the
     * background with the divider, each corner's panel, the top lines, each
     * fighter's details, the match/court chips and the VS. Drawn at `k` pixels
     * per design unit, each only as big as its own area.
     */
    fun layers(k: Float): List<Layer> {
        fun layer(name: String, rect: RectF, block: (Canvas) -> Unit): Layer {
            val bmp = Bitmap.createBitmap(
                maxOf(1, Math.round(rect.width() * k)), maxOf(1, Math.round(rect.height() * k)), Bitmap.Config.ARGB_8888,
            )
            val c = Canvas(bmp)
            c.scale(k, k)
            c.translate(-rect.left, -rect.top)
            block(c)

            return Layer(name, rect, bmp)
        }

        // Both fighters share one grid: the name row is as tall as the taller
        // of the two names, so every row sits level on both sides.
        val nameRow = maxOf(nameInk(red), nameInk(blue)) + 12f
        val chipRow = if (chipsOf(redX).isNotEmpty() || chipsOf(blueX).isNotEmpty()) CHIP_H + 10f else 0f
        val block = 52f + 10f + 42f + 10f + nameRow + 4f + 86f + chipRow
        val lift = infoBottom.coerceIn(INFO_MIN, INFO_MAX)

        val base = layer("base", RectF(0f, 0f, W, H)) { c ->
            c.drawColor(0xFF050507.toInt())
            c.drawRect(0f, 0f, W, H, Paint().apply {
                shader = RadialGradient(W / 2f, H * 0.4f, maxOf(W, H) * 0.68f, intArrayOf(0xFF16161F.toInt(), 0xFF0A0A0E.toInt(), 0xFF050507.toInt()),
                    floatArrayOf(0f, 0.65f, 1f), Shader.TileMode.CLAMP)
            })
        }
        val redPhoto = picture(red.optString("photo"))
        val bluePhoto = picture(blue.optString("photo"))

        if (portrait) {
            // Upright: the panels stack, red on top, blue below, cut on a slant.
            val ph = H * 0.56f
            val by = H - ph
            val redBase = 240f + block          // red's details hang from the top, under the top lines
            val blueBase = H - (lift + 96f)     // blue's sit above the chips, at the operator's height

            return listOf(
                base,
                layer("red", RectF(0f, 0f, W, ph)) { c ->
                    panel(c, Path().apply { moveTo(0f, 0f); lineTo(W, 0f); lineTo(W, ph * 0.82f); lineTo(0f, ph * 0.96f); close() },
                        RectF(0f, 0f, W, ph), colour(redX, "panel", RED[0]), redPhoto, colour(redX, "glow", RED[1]), leftToRight = true,
                        frame = redX.optJSONObject("vs_frame"))
                },
                layer("blue", RectF(0f, by, W, H)) { c ->
                    panel(c, Path().apply { moveTo(0f, by + ph * 0.18f); lineTo(W, by + ph * 0.04f); lineTo(W, H); lineTo(0f, H); close() },
                        RectF(0f, by, W, H), colour(blueX, "panel", BLUE[0]), bluePhoto, colour(blueX, "glow", BLUE[1]), leftToRight = false,
                        frame = blueX.optJSONObject("vs_frame"))
                },
                layer("divider", RectF(0f, H / 2f - 120f, W, H / 2f + 120f)) { c ->
                    c.save()
                    c.rotate(-4.48f, W / 2f, H / 2f)
                    c.drawRect(-60f, H / 2f - 1.5f, W + 60f, H / 2f + 1.5f, Paint(Paint.ANTI_ALIAS_FLAG).apply {
                        shader = LinearGradient(-60f, 0f, W + 60f, 0f, intArrayOf(0, 0xE6F0C24A.toInt(), 0xE6F0C24A.toInt(), 0),
                            floatArrayOf(0f, 0.2f, 0.8f, 1f), Shader.TileMode.CLAMP)
                    })
                    c.restore()
                },
                layer("top", RectF(0f, 0f, W, 190f)) { c -> top(c) },
                layer("infoRed", RectF(0f, redBase - block - 20f, W, redBase + 20f)) { c ->
                    fighter(c, red, mirrored = false, tag = colour(redX, "badge_bg", RED[2]), country = colour(redX, "country_ink", RED[3]),
                        nameRow = nameRow, baseY = redBase, extra = redX, chipRow = chipRow)
                },
                layer("infoBlue", RectF(0f, blueBase - block - 20f, W, blueBase + 20f)) { c ->
                    fighter(c, blue, mirrored = true, tag = colour(blueX, "badge_bg", BLUE[2]), country = colour(blueX, "country_ink", BLUE[3]),
                        nameRow = nameRow, baseY = blueBase, extra = blueX, chipRow = chipRow)
                },
                layer("chips", RectF(0f, H - 120f, W, H)) { c -> chips(c, H - 32.4f - (34.6f + 21.6f)) },
                layer("vs", RectF(W / 2f - 400f, H / 2f - 290f, W / 2f + 400f, H / 2f + 250f)) { c -> word(c) },
            )
        }

        val w = W * 0.56f
        val bx = W - w
        val baseY = H - lift

        // Which corner stands in which slot — the sport's convention, as the mats.
        val leftWho = if (redRight) blue else red
        val rightWho = if (redRight) red else blue
        val leftX = if (redRight) blueX else redX
        val rightX = if (redRight) redX else blueX
        val leftPhoto = if (redRight) bluePhoto else redPhoto
        val rightPhoto = if (redRight) redPhoto else bluePhoto
        val leftDefault = if (redRight) BLUE else RED
        val rightDefault = if (redRight) RED else BLUE

        return listOf(
            base,
            // The two panels: red from the left, blue from the right, each cut
            // on the slant and carrying its fighter's photograph.
            layer(if (redRight) "blue" else "red", RectF(0f, 0f, w, H)) { c ->
                panel(c, Path().apply { moveTo(0f, 0f); lineTo(w, 0f); lineTo(w * 0.82f, H); lineTo(0f, H); close() },
                    RectF(0f, 0f, w, H), colour(leftX, "panel", leftDefault[0]), leftPhoto, colour(leftX, "glow", leftDefault[1]),
                    leftToRight = true, frame = leftX.optJSONObject("vs_frame"))
            },
            layer(if (redRight) "red" else "blue", RectF(bx, 0f, W, H)) { c ->
                panel(c, Path().apply { moveTo(bx + w * 0.18f, 0f); lineTo(W, 0f); lineTo(W, H); lineTo(bx, H); close() },
                    RectF(bx, 0f, W, H), colour(rightX, "panel", rightDefault[0]), rightPhoto, colour(rightX, "glow", rightDefault[1]),
                    leftToRight = false, frame = rightX.optJSONObject("vs_frame"))
            },
            // The gold divider, on the page's 10° lean, over both panels.
            layer("divider", RectF(700f, 0f, 1220f, H)) { c ->
                c.save()
                c.rotate(10.15f, W / 2f, H / 2f)
                c.drawRect(958.5f, -65f, 961.5f, H + 65f, Paint(Paint.ANTI_ALIAS_FLAG).apply {
                    shader = LinearGradient(0f, -65f, 0f, H + 65f, intArrayOf(0, 0xE6F0C24A.toInt(), 0xE6F0C24A.toInt(), 0),
                        floatArrayOf(0f, 0.2f, 0.8f, 1f), Shader.TileMode.CLAMP)
                })
                c.restore()
            },
            layer("top", RectF(0f, 0f, W, 170f)) { c -> top(c) },
            layer(if (redRight) "infoBlue" else "infoRed", RectF(0f, baseY - block - 20f, W / 2f, baseY + 20f)) { c ->
                fighter(c, leftWho, mirrored = false, tag = colour(leftX, "badge_bg", leftDefault[2]), country = colour(leftX, "country_ink", leftDefault[3]),
                    nameRow = nameRow, baseY = baseY, extra = leftX, chipRow = chipRow)
            },
            layer(if (redRight) "infoRed" else "infoBlue", RectF(W / 2f, baseY - block - 20f, W, baseY + 20f)) { c ->
                fighter(c, rightWho, mirrored = true, tag = colour(rightX, "badge_bg", rightDefault[2]), country = colour(rightX, "country_ink", rightDefault[3]),
                    nameRow = nameRow, baseY = baseY, extra = rightX, chipRow = chipRow)
            },
            // Match and mat along the bottom, centred between the two fighters.
            layer("chips", RectF(0f, H - 120f, W, H)) { c -> chips(c, H - 32.4f - (34.6f + 21.6f)) },
            layer("vs", RectF(560f, 250f, 1360f, 790f)) { c -> word(c) },
        )
    }

    /** The finished screen as one still (the end of the animation). */
    fun draw(width: Int, height: Int): Bitmap {
        val out = Bitmap.createBitmap(width, height, Bitmap.Config.ARGB_8888)
        val k = minOf(width / W, height / H)
        val c = Canvas(out)
        c.translate((width - W * k) / 2f, (height - H * k) / 2f)
        c.scale(k, k)
        VsAnim.compose(c, layers(k), VsAnim.END)

        return out
    }

    private fun panel(c: Canvas, shape: Path, box: RectF, colour: Int, photo: Bitmap?, scrim: Int, leftToRight: Boolean, frame: JSONObject? = null) {
        c.save()
        c.clipPath(shape)
        c.drawColor(colour)

        if (photo != null) {
            // How THIS photo is placed (PhotoVsFrame, set in the console's
            // Players tab): a zoom over the panel's width and a focal point, as
            // the mats' CSS reads it. None: cover, centred, 12% from the top.
            val z = frame?.optDouble("z", Double.NaN) ?: Double.NaN
            val fx = frame?.optDouble("x", Double.NaN) ?: Double.NaN
            val fy = frame?.optDouble("y", Double.NaN) ?: Double.NaN
            val framed = !z.isNaN() && !fx.isNaN() && !fy.isNaN()
            val pw: Float
            val ph: Float
            if (framed) {
                pw = box.width() * z.toFloat().coerceIn(1f, 3f)
                ph = pw * photo.height / photo.width
            } else {
                val fit = maxOf(box.width() / photo.width, box.height() / photo.height)
                pw = photo.width * fit
                ph = photo.height * fit
            }
            val px = if (framed) fx.toFloat().coerceIn(0f, 100f) / 100f else 0.5f
            val py = if (framed) fy.toFloat().coerceIn(0f, 100f) / 100f else 0.12f
            val left = box.left + (box.width() - pw) * px
            val top = box.top + (box.height() - ph) * py
            c.drawBitmap(photo, null, RectF(left, top, left + pw, top + ph), Paint(Paint.FILTER_BITMAP_FLAG))
        }

        // The corner's colour washing in from the outer edge, then the dark
        // fades top and bottom that let the words read.
        val (x0, x1) = if (leftToRight) box.left to box.left + box.width() * 0.55f else box.right to box.right - box.width() * 0.55f
        c.drawRect(box, Paint().apply { shader = LinearGradient(x0, box.top, x1, box.top + box.height() * 0.3f, scrim, 0, Shader.TileMode.CLAMP) })
        c.drawRect(box, Paint().apply {
            shader = LinearGradient(0f, box.bottom, 0f, box.top,
                intArrayOf(0xF2050507.toInt(), 0xB8050507.toInt(), 0x4D050507, 0, 0),
                floatArrayOf(0f, 0.22f, 0.42f, 0.62f, 1f), Shader.TileMode.CLAMP)
        })
        c.drawRect(box, Paint().apply {
            shader = LinearGradient(0f, box.top, 0f, box.top + box.height() * 0.32f, intArrayOf(0xD1050507.toInt(), 0x59050507, 0),
                floatArrayOf(0f, 0.56f, 1f), Shader.TileMode.CLAMP)
        })
        c.restore()
    }

    /** The top block; answers where it ends. */
    private fun top(c: Canvas): Float {
        var y = 34.6f

        val event = vs.optString("event").uppercase()
        if (event.isNotEmpty()) {
            val p = shadow(text(bold, 30.2f, 0xEBE8E6E0.toInt(), 0.42f), 14f)
            arabicSafe(p, event)
            val wide = p.measureText(event)
            if (wide > W - 160f) p.textSize *= (W - 160f) / wide
            val t = event
            c.drawText(t, (W / 2f) - p.measureText(t) / 2f, y - p.fontMetrics.ascent, p)
            y += 32f + 10.8f
        }

        // The round (gold) and the weight class (white) on ONE line, with the
        // page's gold rules either side of the pair.
        val stage = vs.optString("stage").uppercase()
        val d = division.uppercase()
        if (stage.isNotEmpty() || d.isNotEmpty()) {
            val sp = shadow(text(black, 40f, gold, 0.24f), 14f)
            val dp = shadow(text(black, 40f, Color.WHITE, 0.2f), 14f)
            arabicSafe(dp, d)
            val sep = if (stage.isNotEmpty() && d.isNotEmpty()) "  ·  " else ""
            val sw = sp.measureText(stage)
            val gw = dp.measureText(sep)
            // Never cut: the line shrinks, round and class together, to fit.
            val roomD = W - 320f - sw - gw
            if (dp.measureText(d) > roomD && roomD > 0) {
                val f = roomD / dp.measureText(d)
                dp.textSize *= f
            }
            val dText = d
            val tw = sw + gw + dp.measureText(dText)
            val mid = y + 24f
            val base = mid - (sp.fontMetrics.ascent + sp.fontMetrics.descent) / 2f
            var x = (W / 2f) - tw / 2f
            c.drawText(stage, x, base, sp); x += sw
            c.drawText(sep, x, base, dp); x += gw
            c.drawText(dText, x, base, dp)

            val l = Paint().apply { shader = LinearGradient((W / 2f) - tw / 2f - 82f, 0f, (W / 2f) - tw / 2f - 17f, 0f, 0, gold, Shader.TileMode.CLAMP) }
            c.drawRect((W / 2f) - tw / 2f - 82f, mid - 1f, (W / 2f) - tw / 2f - 17f, mid + 1f, l)
            val r = Paint().apply { shader = LinearGradient((W / 2f) + tw / 2f + 17f, 0f, (W / 2f) + tw / 2f + 82f, 0f, gold, 0, Shader.TileMode.CLAMP) }
            c.drawRect((W / 2f) + tw / 2f + 17f, mid - 1f, (W / 2f) + tw / 2f + 82f, mid + 1f, r)
            y += 48f
        }

        return y
    }

    private val maxW get() = if (portrait) W * 0.86f else W * 0.44f

    /** The name's paint: its face and size, shrunk (never cut) to fit its side. */
    private fun namePaint(name: String): TextPaint {
        val p = shadow(text(black, if (arabic(name)) 58f else 71.3f, Color.WHITE, 0f), 30f)
        val wide = p.measureText(name)
        if (wide > maxW) p.textSize *= maxW / wide

        return p
    }

    /** How tall a fighter's name really is, in ink (Arabic rises and falls far). */
    private fun nameInk(who: JSONObject): Float {
        val name = who.optString("name").uppercase()
        if (name.isEmpty()) return 60f
        val ink = android.graphics.Rect()
        namePaint(name).getTextBounds(name, 0, name.length, ink)

        return ink.height().toFloat()
    }

    /**
     * One fighter's details, from the bottom up, on the grid both sides share:
     * every row has the same height on the left and the right, and a row keeps
     * its place even when this fighter has nothing to put in it — so the two
     * sides always line up, whichever script a name is in or whatever is
     * missing.
     */
    private fun fighter(c: Canvas, who: JSONObject, mirrored: Boolean, tag: Int, country: Int, nameRow: Float, baseY: Float,
                        extra: JSONObject = JSONObject(), chipRow: Float = 0f) {
        val edge = if (mirrored) W - 43.2f else 43.2f
        fun x(width: Float) = if (mirrored) edge - width else edge

        var y = baseY

        // The chips under the club — record, belt (gold), age · height · weight —
        // as the mats draw them. The row keeps its height on both sides.
        if (chipRow > 0f) {
            var cx = edge
            for ((value, goldChip) in chipsOf(extra)) {
                val p = text(black, 34f, if (goldChip) 0xFFE8C96A.toInt() else Color.WHITE, 0.1f)
                val tw = p.measureText(value) + 40f
                val box = if (mirrored) RectF(cx - tw, y - CHIP_H, cx, y) else RectF(cx, y - CHIP_H, cx + tw, y)
                c.drawRect(box, Paint().apply { color = 0xD10A0A0E.toInt() })
                c.drawRect(box, Paint(Paint.ANTI_ALIAS_FLAG).apply {
                    style = Paint.Style.STROKE; strokeWidth = 2f
                    color = if (goldChip) 0xD9FDC436.toInt() else 0x8CFFFFFF.toInt()
                })
                c.drawText(value, box.left + 20f, box.centerY() - (p.fontMetrics.ascent + p.fontMetrics.descent) / 2f, p)
                cx = if (mirrored) box.left - 9.7f else box.right + 9.7f
            }
            y -= chipRow
        }

        // Club row: the crest, bare, and the club's name.
        val clubRow = 86f
        val clubName = who.optString("club").uppercase()
        val crest = picture(who.optString("club_logo"))
        var cx = edge
        if (crest != null) {
            val box = if (mirrored) RectF(edge - clubRow, y - clubRow, edge, y) else RectF(edge, y - clubRow, edge + clubRow, y)
            fitInto(c, crest, box)
        }
        // The club name starts beside the crest's place on BOTH sides, crest or not.
        cx = if (mirrored) edge - clubRow - 13f else edge + clubRow + 13f
        if (clubName.isNotEmpty()) {
            val p = text(bold, 30.2f, 0xE6E8E6E0.toInt(), 0.12f)
            arabicSafe(p, clubName)
            val room = maxW - clubRow - 13f
            val wide = p.measureText(clubName)
            if (wide > room) p.textSize *= room / wide
            val tw = p.measureText(clubName)
            c.drawText(clubName, if (mirrored) cx - tw else cx, y - clubRow / 2f - (p.fontMetrics.ascent + p.fontMetrics.descent) / 2f, p)
        }
        y -= clubRow + 4f

        // The name, centred in the shared name row.
        val name = who.optString("name").uppercase()
        if (name.isNotEmpty()) {
            val p = namePaint(name)
            val ink = android.graphics.Rect()
            p.getTextBounds(name, 0, name.length, ink)
            val centre = y - nameRow / 2f
            c.drawText(name, x(p.measureText(name)), centre - (ink.top + ink.bottom) / 2f, p)
        }
        y -= nameRow

        // Flag and country, in full.
        val flagRow = 42f
        val flag = picture(who.optString("flag_png"))
        val full = who.optString("country").takeIf { it.isNotEmpty() && it != "null" }
        val code = (full ?: who.optString("flag").takeIf { it != "null" }.orEmpty()).uppercase()
        val fw = 56.2f
        if (flag != null) {
            val box = if (mirrored) RectF(edge - fw, y - flagRow, edge, y) else RectF(edge, y - flagRow, edge + fw, y)
            c.save(); c.clipRect(box); fitInto(c, flag, box, cover = true); c.restore()
        }
        if (code.isNotEmpty()) {
            val p = text(bold, 32.4f, country, 0.28f)
            val tw = p.measureText(code)
            val tx = if (mirrored) edge - fw - 15f - tw else edge + fw + 15f
            c.drawText(code, tx, y - flagRow / 2f - (p.fontMetrics.ascent + p.fontMetrics.descent) / 2f, p)
        }
        y -= flagRow + 10f

        // The corner tag: the event's own badge ("AKA · RED"), else AKA / AO.
        val label = (extra.optString("badge").takeIf { it.isNotEmpty() && it != "null" } ?: who.optString("label")).uppercase()
        if (label.isNotEmpty()) {
            val p = text(black, 36f, Color.WHITE, 0.3f)
            val tw = p.measureText(label) + 24f + 30f
            val th = 36f + 16f
            val box = if (mirrored) RectF(edge - tw, y - th, edge, y) else RectF(edge, y - th, edge + tw, y)
            c.drawRect(box, Paint().apply { color = tag })
            c.drawText(label, box.left + 30f, box.centerY() - (p.fontMetrics.ascent + p.fontMetrics.descent) / 2f, p)
        }
    }

    private fun word(c: Canvas) {
        val p = text(black, 183.6f, 0xFFFFFDF5.toInt(), 0f).apply {
            textSkewX = -0.2f
            setShadowLayer(60f, 0f, 0f, 0xC8FFD778.toInt())
        }
        val tw = p.measureText("VS")
        val base = H / 2f - H * 0.02f - (p.fontMetrics.ascent + p.fontMetrics.descent) / 2f
        c.drawText("VS", (W / 2f) - tw / 2f, base, p)
        c.drawText("VS", (W / 2f) - tw / 2f, base, TextPaint(p).apply {
            style = Paint.Style.STROKE; strokeWidth = 2f; color = 0x99F0C24A.toInt(); clearShadowLayer()
        })
    }

    private fun chips(c: Canvas, topY: Float) {
        val chips = listOfNotNull(
            vs.optString("match").takeIf { it.isNotEmpty() }?.let { "MATCH" to it },
            vs.optString("court").takeIf { it.isNotEmpty() }?.let { "COURT" to it.uppercase() },
            referee?.let { "REFEREE" to it.uppercase() },
        )
        if (chips.isEmpty()) return

        val label = text(bold, 23.8f, 0xA6E8E6E0.toInt(), 0.30f)
        val value = text(black, 34.6f, Color.WHITE, 0f)
        val gap = 17.3f
        val widths = chips.map { (l, v) -> 23.8f * 2 + label.measureText(l) + 8.6f + value.measureText(v) }
        val total = widths.sum() + gap * 2 * (chips.size - 1) + 6f * (chips.size - 1)
        var x = (W / 2f) - total / 2f
        val h = 34.6f + 21.6f
        val top = topY

        chips.forEachIndexed { i, (l, v) ->
            val box = RectF(x, top, x + widths[i], top + h)
            c.drawRect(box, Paint().apply { color = 0xB80A0A0E.toInt() })
            c.drawRect(box, Paint(Paint.ANTI_ALIAS_FLAG).apply { style = Paint.Style.STROKE; strokeWidth = 1f; color = 0x73F0C24A })
            val base = box.centerY() - (value.fontMetrics.ascent + value.fontMetrics.descent) / 2f
            c.drawText(l, box.left + 23.8f, base, label)
            c.drawText(v, box.left + 23.8f + label.measureText(l) + 8.6f, base, value)
            x += widths[i]
            if (i < chips.size - 1) {
                val d = x + gap + 3f
                c.save(); c.rotate(45f, d, box.centerY())
                c.drawRect(d - 3f, box.centerY() - 3f, d + 3f, box.centerY() + 3f, Paint().apply { color = gold })
                c.restore()
                x += gap * 2 + 6f
            }
        }
    }

    /* ── Small things ──────────────────────────────────────────────────────── */

    /** The chips a corner carries, in the mats' order; stats only when the platform shows them. */
    private fun chipsOf(x: JSONObject): List<Pair<String, Boolean>> {
        fun s(k: String) = x.optString(k).takeIf { it.isNotEmpty() && it != "null" }
        val stats = if (!showStats) null else listOfNotNull(
            s("age")?.let { it + "Y" }, s("height")?.let { it + "cm" },
            s("weight")?.let { (it.toDoubleOrNull()?.let { w -> if (w % 1.0 == 0.0) w.toInt().toString() else w.toString() } ?: it) + "kg" },
        ).joinToString(" · ").takeIf { it.isNotEmpty() }

        return listOfNotNull(s("record")?.let { it.uppercase() to false }, s("belt")?.let { it.uppercase() to true }, stats?.let { it.uppercase() to false })
    }

    private val rgba = Regex("rgba?\\(\\s*([\\d.]+)\\s*,\\s*([\\d.]+)\\s*,\\s*([\\d.]+)\\s*(?:,\\s*([\\d.]+))?\\s*\\)")

    /** A colour the server sent (#rrggbb or rgba(r,g,b,a)), or this screen's own. */
    private fun colour(x: JSONObject, key: String, fallback: Int): Int {
        val v = x.optString(key).trim()
        try {
            if (v.startsWith("#")) return Color.parseColor(v)
            val m = rgba.find(v)
            if (m != null) {
                val a = m.groupValues[4].toFloatOrNull() ?: 1f
                return Color.argb((a * 255).toInt().coerceIn(0, 255), m.groupValues[1].toFloat().toInt(),
                    m.groupValues[2].toFloat().toInt(), m.groupValues[3].toFloat().toInt())
            }
        } catch (_: Throwable) {}

        return fallback
    }

    private fun fitInto(c: Canvas, bitmap: Bitmap, box: RectF, cover: Boolean = false) {
        val fit = if (cover) maxOf(box.width() / bitmap.width, box.height() / bitmap.height)
                  else minOf(box.width() / bitmap.width, box.height() / bitmap.height)
        val w = bitmap.width * fit
        val h = bitmap.height * fit
        c.drawBitmap(bitmap, null, RectF(box.centerX() - w / 2f, box.centerY() - h / 2f, box.centerX() + w / 2f, box.centerY() + h / 2f),
            Paint(Paint.FILTER_BITMAP_FLAG or Paint.ANTI_ALIAS_FLAG))
    }

    private fun text(face: Typeface, size: Float, colour: Int, spacing: Float) = TextPaint(
        Paint.ANTI_ALIAS_FLAG or Paint.LINEAR_TEXT_FLAG or Paint.SUBPIXEL_TEXT_FLAG,
    ).apply {
        typeface = face
        textSize = size
        color = colour
        letterSpacing = spacing
    }

    private fun shadow(p: TextPaint, blur: Float) = p.apply { setShadowLayer(blur, 0f, 3f, 0xE6000000.toInt()) }

    private fun arabic(text: String) =
        text.any { it in '؀'..'ۿ' || it in 'ݐ'..'ݿ' || it in 'ﭐ'..'﻿' }

    private fun arabicSafe(p: TextPaint, text: String) {
        if (arabic(text)) p.letterSpacing = 0f
    }

    private fun picture(dataUri: String?): Bitmap? = try {
        if (dataUri.isNullOrEmpty() || !dataUri.startsWith("data:image/")) null
        else {
            val bytes = Base64.decode(dataUri.substringAfter(","), Base64.DEFAULT)
            BitmapFactory.decodeByteArray(bytes, 0, bytes.size)
        }
    } catch (_: Throwable) {
        null
    }

    private fun font(assets: AssetManager, file: String, arabic: String): Typeface = try {
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.Q) {
            Typeface.CustomFallbackBuilder(FontFamily.Builder(Font.Builder(assets, "fonts/$file").build()).build())
                .addCustomFallback(FontFamily.Builder(Font.Builder(assets, "fonts/$arabic").build()).build())
                .setSystemFallback("sans-serif")
                .build()
        } else {
            Typeface.createFromAsset(assets, "fonts/$file")
        }
    } catch (_: Throwable) {
        try { Typeface.createFromAsset(assets, "fonts/$file") } catch (_: Throwable) { Typeface.DEFAULT_BOLD }
    }
}
