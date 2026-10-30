package bh.takeone.takeone_lab

import android.content.res.AssetManager
import android.graphics.Bitmap
import android.graphics.BitmapFactory
import android.graphics.Canvas
import android.graphics.Color
import android.graphics.LinearGradient
import android.graphics.Paint
import android.graphics.Path
import android.graphics.RectF
import android.graphics.Shader
import android.graphics.Typeface
import android.graphics.fonts.Font
import android.graphics.fonts.FontFamily
import android.os.Build
import android.text.TextPaint
import android.text.TextUtils
import android.util.Base64
import androidx.annotation.OptIn
import androidx.media3.common.util.UnstableApi
import androidx.media3.effect.BitmapOverlay
import org.json.JSONObject
import kotlin.math.floor
import kotlin.math.max
import kotlin.math.tan

/**
 * The bout's score bar, drawn on the phone and laid over the video by Media3.
 *
 * WHAT it shows is never decided here: the server sends the plan
 * (CameraController::plan → BoutShareRenderer::plan) — every state the bar
 * passes through, the rounds, the names, the corner words — computed by the
 * very methods that draw the server's own copy. This class only paints it, in
 * the server bar's geometry (media::share.scoreboard): a 1150-unit-wide design
 * scaled to the frame, the skewed red and blue panels, the clock box between
 * them, the live-scoring feed top right and the event line top left.
 *
 * One overlay per piece of video. `segStart` is where that piece begins in the
 * ORIGINAL recording, and the first frame the overlay is shown is taken as that
 * instant — so it reads the bout's own clock whether Media3 numbers a clipped
 * piece from zero or from its place in the source.
 */
@OptIn(UnstableApi::class)
class ScoreBar(
    private val plan: JSONObject,
    private val width: Int,
    private val height: Int,
    private val segStart: Double,
    assets: AssetManager,
    /**
     * Which parts are drawn — what the player's switches left on, so the video
     * sent is the video previewed: `scorebar` (the bar and its timeline strip),
     * `feed` (live scoring), `info` (the event line), `logos` (the event's and
     * clubs' crests), `flags`. Absent means everything.
     */
    private val parts: Set<String> = ALL,
    /**
     * How high the bottom group sits — the match strip, the bar and the score
     * line move together — as the distance from the frame's bottom edge to
     * the score line, in design units (the frame is 1150 wide). The operator
     * sets it with the player's slider; LIFT_DEFAULT sits lower than the
     * page's own bar, nearer the edge and off the fight.
     */
    private val lift: Float = LIFT_DEFAULT,
) : BitmapOverlay() {

    companion object {
        val ALL = setOf("intro", "music", "scorebar", "feed", "info", "logos", "flags")
        const val LIFT_DEFAULT = 18f
        const val LIFT_MIN = 4f
        const val LIFT_MAX = 240f
    }

    private fun on(part: String) = part in parts

    private class State(val at: Double, val red: Int, val blue: Int, val round: String,
                        val feed: List<Feed>, val segments: List<Int>)

    private class Feed(val ts: String, val name: String, val pts: Int, val colour: Int)

    private val states: List<State>
    private val rounds: List<Double>

    private val disc = plan.optString("disc").uppercase()
    private val sub = plan.optString("sub").uppercase()
    private val feedTitle = plan.optString("feed_title").uppercase()
    private val tab = plan.optJSONObject("tab") ?: JSONObject()
    private val red = plan.optJSONObject("red") ?: JSONObject()
    private val blue = plan.optJSONObject("blue") ?: JSONObject()

    // The pictures, as the event sent them (small PNG data URIs): the event's
    // crest, each corner's club and flag. Any of them may be missing — the bar
    // then draws as the bout page does with that slot empty.
    private val eventLogo = picture(plan.optString("event_logo"))
    private val redClub = picture(red.optString("club_logo"))
    private val blueClub = picture(blue.optString("club_logo"))
    private val redFlag = picture(red.optString("flag_png"))
    private val blueFlag = picture(blue.optString("flag_png"))

    // Barlow Condensed has no Arabic, and the phone's own fallback for it is a
    // different face at a different weight — so every weight carries Tajawal
    // (same licence, bundled beside it) as its Arabic.
    private val regular = font(assets, "BarlowCondensed-Regular.ttf", "Tajawal-Regular.ttf")
    private val bold = font(assets, "BarlowCondensed-Bold.ttf", "Tajawal-Bold.ttf")
    private val black = font(assets, "BarlowCondensed-Black.ttf", "Tajawal-Black.ttf")

    /** Design units → pixels: the server lays the bar out 1150 wide. */
    /**
     * The design's width in units: 1150 for a landscape frame, as the server's
     * bar is drawn; 900 for an UPRIGHT one, so the same bar fits the narrow
     * frame with its text still readable.
     */
    private val dw = if (height > width) 900f else 1150f
    private val k = width / dw
    private val designHeight = height / k

    /** Two buffers: a changed bar is drawn into the one not on screen. */
    private val buffers = arrayOf(
        Bitmap.createBitmap(width, height, Bitmap.Config.ARGB_8888),
        Bitmap.createBitmap(width, height, Bitmap.Config.ARGB_8888),
    )
    private var showing = 0
    private var shownKey: String? = null
    private var firstUs = -1L

    init {
        val list = ArrayList<State>()
        val raw = plan.optJSONArray("states")
        for (i in 0 until (raw?.length() ?: 0)) {
            val s = raw!!.getJSONObject(i)
            val feed = ArrayList<Feed>()
            val f = s.optJSONArray("feed")
            for (j in 0 until (f?.length() ?: 0)) {
                val e = f!!.getJSONObject(j)
                feed.add(Feed(e.optString("ts"), e.optString("name").uppercase(), e.optInt("pts"), colour(e.optString("colour"))))
            }
            val segs = ArrayList<Int>()
            val g = s.optJSONArray("segments")
            for (j in 0 until (g?.length() ?: 0)) segs.add(colour(g!!.optString(j)))
            list.add(State(s.optDouble("at"), s.optInt("score_red"), s.optInt("score_blue"), s.optString("round"), feed, segs))
        }
        states = list.sortedBy { it.at }

        val r = plan.optJSONArray("rounds")
        rounds = (0 until (r?.length() ?: 0)).map { r!!.getJSONObject(it).optDouble("start") }.sorted()
    }

    override fun getBitmap(presentationTimeUs: Long): Bitmap {
        if (firstUs < 0) firstUs = presentationTimeUs

        return frame(segStart + (presentationTimeUs - firstUs) / 1_000_000.0).first
    }

    /**
     * The bar at one instant of the recording, and whether it differs from the
     * last one asked for — the player's preview asks several times a second
     * and only repaints when something visible changed.
     */
    fun frame(at: Double): Pair<Bitmap, Boolean> {
        val index = states.indexOfLast { it.at <= at + 0.001 }.coerceAtLeast(0)
        val state = states.getOrNull(index)
        val clock = clock(at)
        val key = "$index|$clock"

        if (key == shownKey) return buffers[showing] to false

        val next = 1 - showing
        draw(buffers[next], state, clock)
        showing = next
        shownKey = key

        return buffers[showing] to true
    }

    /** The clock as the page reads it: from the current round's start, 0:00 before the first. */
    private fun clock(at: Double): String {
        val start = rounds.lastOrNull { it <= at } ?: return "0:00"
        val whole = max(0, floor(at - start).toInt())

        return "${whole / 60}:${(whole % 60).toString().padStart(2, '0')}"
    }

    /* ── Painting, in design units ─────────────────────────────────────────── */

    private val skew = tan(Math.toRadians(9.0)).toFloat()

    private fun draw(bitmap: Bitmap, state: State?, clock: String) {
        bitmap.eraseColor(Color.TRANSPARENT)
        val c = Canvas(bitmap)
        c.scale(k, k)

        info(c)
        if (on("feed") && state != null && state.feed.isNotEmpty()) feed(c, state.feed)
        if (on("scorebar")) {
            bar(c, state, clock)
            segments(c, state)
        }
    }

    /** A soft shadow under text laid straight on the picture, so it reads on any wall. */
    private fun shadowed(p: TextPaint): TextPaint = p.apply { setShadowLayer(5f, 0f, 1f, 0xE6000000.toInt()) }

    private fun info(c: Canvas) {
        val logo = if (on("logos")) eventLogo else null
        val words = on("info") && (disc.isNotEmpty() || sub.isNotEmpty())
        if (logo == null && !words) return

        val d = shadowed(text(bold, 15f, 0xFFEFE9E0.toInt(), 0.3f))
        val s = shadowed(text(regular, 13f, 0xFFE4DED6.toInt(), 0.22f))
        val maxW = minOf(700f, dw - 140f)
        joined(d, disc)
        joined(s, sub)
        // Never cut: a line too long is set smaller until it fits.
        fit(d, disc, maxW)
        fit(s, sub, maxW)
        val discText = if (words) disc else ""
        val subText = if (words) sub else ""

        var h = 0f
        if (discText.isNotEmpty()) h += 18f
        if (subText.isNotEmpty()) h += 16f + if (discText.isNotEmpty()) 2f else 0f

        // The crest and the event line share one rounded pill, lightly tinted
        // (20%) so the words read without hiding the hall behind them.
        val pillTop = 16f
        val pillH = 58f
        val logoBox = RectF(28f, pillTop + 5f, 28f + 48f, pillTop + 5f + 48f)
        val left = if (logo != null) logoBox.right + 10f else 34f
        val textW = maxOf(if (discText.isEmpty()) 0f else d.measureText(discText), if (subText.isEmpty()) 0f else s.measureText(subText))
        val right = if (words) left + 12f + textW + 16f else logoBox.right + 8f
        c.drawRoundRect(RectF(22f, pillTop, right, pillTop + pillH), pillH / 2f, pillH / 2f, fill(0x33080912))

        // The crest, bare (never on a tile of its own), left of the name.
        if (logo != null) image(c, logo, logoBox)

        if (!words) return

        val top = pillTop + (pillH - h) / 2f
        val rule = Paint(Paint.ANTI_ALIAS_FLAG).apply {
            shader = LinearGradient(0f, top, 0f, top + h, 0xFFE8534A.toInt(), 0xFF7C1D18.toInt(), Shader.TileMode.CLAMP)
        }
        c.drawRect(left, top, left + 3f, top + h, rule)

        var y = top
        if (discText.isNotEmpty()) {
            c.drawText(discText, left + 12f, baseline(d, y, 18f), d)
            y += 20f
        }
        if (subText.isNotEmpty()) c.drawText(subText, left + 12f, baseline(s, y, 16f), s)
    }

    private fun feed(c: Canvas, feed: List<Feed>) {
        // Smaller than the page's ticker and only lightly backed: it is a
        // reminder of what just happened, not a panel over the fight.
        val right = dw - 24f
        val left = right - 190f
        var y = 30f

        val hp = shadowed(text(bold, 10f, 0xFFE4DED6.toInt(), 0.28f))
        c.drawCircle(right - hp.measureText(feedTitle) - 9f, y + 7f, 2.8f, fill(0xFFE8534A.toInt()))
        c.drawText(feedTitle, right - hp.measureText(feedTitle), baseline(hp, y, 14f), hp)
        y += 14f + 6f

        val ts = shadowed(text(regular, 10f, 0xFFB9B2AA.toInt(), 0.12f))
        val name = shadowed(text(bold, 13f, 0xFFEFE9E0.toInt(), 0.08f))

        for (e in feed) {
            val h = 23f
            name.letterSpacing = 0.08f
            joined(name, e.name)
            c.drawRect(left, y, right, y + h, fill(0x380A0A0C))
            c.drawRect(right - 2.5f, y, right, y + h, fill(e.colour))

            // The points in the clock's own face, like the scores.
            val pts = shadowed(text(black, 15f, e.colour, 0f))
            val ptsText = "+${e.pts}"
            var x = right - 2.5f - 8f - pts.measureText(ptsText)
            c.drawText(ptsText, x, baseline(pts, y, h), pts)
            x -= 8f + name.measureText(e.name)
            c.drawText(e.name, x, baseline(name, y, h), name)
            x -= 8f + ts.measureText(e.ts)
            c.drawText(e.ts, x, baseline(ts, y, h), ts)

            y += h + 4f
        }
    }

    private fun bar(c: Canvas, state: State?, clock: String) {
        val bottom = designHeight - lift.coerceIn(LIFT_MIN, LIFT_MAX) - 13f
        val top = bottom - 84f
        val left = 26f
        val right = dw - 26f
        val clockW = 118f
        val panelW = (right - left - clockW) / 2f

        val redRect = RectF(left, top, left + panelW, bottom)
        val clockRect = RectF(redRect.right, top, redRect.right + clockW, bottom)
        val blueRect = RectF(clockRect.right, top, right, bottom)

        // Red.
        panel(c, redRect, 0xF07A1A16.toInt(), 0xE6B4342C.toInt(), 0xFFFF6A5E.toInt())
        corner(c, redRect, red, state?.red ?: 0, mirrored = false,
            clubColour = 0xD9FFE8E4.toInt(), labelColour = 0xB3FFECE8.toInt(), logo = redClub, flag = redFlag)

        // The clock box.
        panel(c, clockRect, 0xE609090B.toInt(), 0xE609090B.toInt(), 0xFF2C2A28.toInt())
        val cx = clockRect.centerX()
        val roundPaint = text(regular, 11f, 0xFF8F8A83.toInt(), 0.3f)
        val roundText = (state?.round ?: "").uppercase()
        val clockPaint = text(black, 34f, 0xFFEFE9E0.toInt(), 0f)
        val stack = 11f * 1.2f + 2f + 34f + 2f + 3f
        var y = clockRect.centerY() - stack / 2f
        c.drawText(roundText, cx - roundPaint.measureText(roundText) / 2f, baseline(roundPaint, y, 13.2f), roundPaint)
        y += 13.2f + 2f
        c.drawText(clock, cx - clockPaint.measureText(clock) / 2f, baseline(clockPaint, y, 34f), clockPaint)
        y += 34f + 2f
        val dash = listOf(0xFFE8534A.toInt(), 0xFFE8534A.toInt(), 0xFF3A3734.toInt())
        var dx = cx - (3 * 20f + 2 * 4f) / 2f
        for (col in dash) {
            c.drawRect(dx, y, dx + 20f, y + 3f, fill(col))
            dx += 24f
        }

        // The strip riding the bar: MATCH (red) | MAT (blue) | ROUND | the
        // division — white, a trapezoid sitting flush on the bar's top edge
        // so it reads as one piece with it.
        strip(c, cx, top)

        // Blue.
        panel(c, blueRect, 0xE61E488C.toInt(), 0xF0122C5C.toInt(), 0xFF6AA6FF.toInt())
        corner(c, blueRect, blue, state?.blue ?: 0, mirrored = true,
            clubColour = 0xD9E2EEFF.toInt(), labelColour = 0xB3E2EEFF.toInt(), logo = blueClub, flag = blueFlag)
    }

    private fun strip(c: Canvas, cx: Float, barTop: Float) {
        val parts = listOfNotNull(
            tab.optString("match").takeIf { it.isNotEmpty() }?.let { "MATCH ${it.uppercase()}" to (black to 0xFFC0392F.toInt()) },
            tab.optString("court").takeIf { it.isNotEmpty() }?.let { it.uppercase() to (black to 0xFF1F5FBF.toInt()) },
            tab.optString("round").takeIf { it.isNotEmpty() }?.let { it.uppercase() to (black to 0xFF111318.toInt()) },
            // The weight class, whole, set apart: heaviest face, its own colour.
            tab.optString("division").takeIf { it.isNotEmpty() }?.let { it.uppercase() to (black to 0xFF5A3EC8.toInt()) },
        )
        if (parts.isEmpty()) return

        val size = 16f
        val gap = 12f
        val paints = parts.map { (t, style) -> joined(text(style.first, size, style.second, 0.12f), t) }
        // The strip as a whole fits the frame: every part shrinks together.
        val full = parts.indices.sumOf { paints[it].measureText(parts[it].first).toDouble() }.toFloat() + 24f * (parts.size - 1)
        val room = dw - 120f
        if (full > room) paints.forEach { it.textSize *= room / full }
        val texts = parts.map { it.first }
        val widths = texts.mapIndexed { i, t -> paints[i].measureText(t) }
        val inner = widths.sum() + gap * 2 * (parts.size - 1)
        val w = inner + 60f
        val h = 28f
        val box = RectF(cx - w / 2f, barTop - h, cx + w / 2f, barTop)

        // White, cut as a trapezoid — both sides leaning in, wide where it
        // stands on the bar — with a soft shadow on its top so it holds
        // against a bright hall.
        val inset = 14f
        val path = Path().apply {
            moveTo(box.left + inset, box.top)
            lineTo(box.right - inset, box.top)
            lineTo(box.right, box.bottom)
            lineTo(box.left, box.bottom)
            close()
        }
        c.drawPath(path, Paint(Paint.ANTI_ALIAS_FLAG).apply { color = Color.WHITE; setShadowLayer(8f, 0f, -1f, 0x40000000) })

        var x = cx - inner / 2f
        val sep = fill(0x40111318)
        texts.forEachIndexed { i, t ->
            c.drawText(t, x, baseline(paints[i], box.top, h), paints[i])
            x += widths[i]
            if (i < texts.size - 1) {
                c.drawRect(x + gap - 0.6f, box.top + 8f, x + gap + 0.6f, box.bottom - 8f, sep)
                x += gap * 2
            }
        }
    }

    /** A skewed panel (CSS skewX(-9deg)) with its 3-unit bottom border. */
    private fun panel(c: Canvas, r: RectF, from: Int, to: Int, border: Int) {
        val lean = skew * r.height() / 2f
        val path = Path().apply {
            moveTo(r.left + lean, r.top)
            lineTo(r.right + lean, r.top)
            lineTo(r.right - lean, r.bottom)
            lineTo(r.left - lean, r.bottom)
            close()
        }
        val p = Paint(Paint.ANTI_ALIAS_FLAG).apply {
            shader = LinearGradient(r.left, 0f, r.right, 0f, from, to, Shader.TileMode.CLAMP)
        }
        c.drawPath(path, p)

        val b = 3f
        val edge = Path().apply {
            moveTo(r.left - lean + skew * b, r.bottom - b)
            lineTo(r.right - lean + skew * b, r.bottom - b)
            lineTo(r.right - lean, r.bottom)
            lineTo(r.left - lean, r.bottom)
            close()
        }
        c.drawPath(edge, fill(border))
    }

    private fun corner(c: Canvas, r: RectF, who: JSONObject, score: Int, mirrored: Boolean, clubColour: Int, labelColour: Int,
                       logo: Bitmap?, flag: Bitmap?) {
        val pad = 16f
        val scoreW = 60f
        val gap = 12f
        val box = 56f // the club's crest, as big as the bar allows

        val name = text(black, 25f, Color.WHITE, 0f)
        val club = text(regular, 12f, clubColour, 0.03f)
        val label = text(black, 15f, Color.WHITE, 0.2f)
        // The score in the clock's face (Barlow Condensed Black), as one board.
        val big = text(black, 48f, Color.WHITE, 0f).apply { setShadowLayer(10f, 0f, 3f, 0x80000000.toInt()) }

        // The club's crest at the panel's outer edge, BARE: logos are
        // transparent PNGs and are never put on a tile of any colour.
        var textLeft = r.left + pad
        var textRight = r.right - pad - scoreW - gap
        if (mirrored) {
            textLeft = r.left + pad + scoreW + gap
            textRight = r.right - pad
        }
        if (logo != null && on("logos")) {
            val slot = if (mirrored) RectF(r.right - pad - box, r.centerY() - box / 2f, r.right - pad, r.centerY() + box / 2f)
                       else RectF(r.left + pad, r.centerY() - box / 2f, r.left + pad + box, r.centerY() + box / 2f)
            image(c, logo, slot)
            if (mirrored) textRight -= box + gap else textLeft += box + gap
        }

        // The flag rides beside the name (before it on red, after it on blue).
        val flagW = 26f
        val flagH = 17f
        var nameLeft = textLeft
        var nameRight = textRight
        val flag = if (on("flags")) flag else null
        if (flag != null) {
            if (mirrored) nameRight -= flagW + 10f else nameLeft += flagW + 10f
        }

        val nameW = nameRight - nameLeft
        joined(club, who.optString("club"))

        // Arabic is drawn in Tajawal, whose letters stand far taller than
        // Barlow Condensed's at the same size — so an Arabic name is set
        // smaller, to the same visual height, and never reaches the club line.
        if (arabic(who.optString("name"))) name.textSize = 19f
        val nameText = who.optString("name").uppercase()
        val clubText = who.optString("club").uppercase()
        fit(name, nameText, nameW)
        fit(club, clubText, textRight - textLeft)

        val hasClub = clubText.isNotEmpty()
        val block = 25f + if (hasClub) 3f + 14f else 0f
        var y = r.centerY() - block / 2f

        if (flag != null) {
            val fx = if (mirrored) textRight - flagW else textLeft
            val fr = RectF(fx, y + (25f - flagH) / 2f, fx + flagW, y + (25f + flagH) / 2f)
            image(c, flag, fr, cover = true)
        }

        val nameX = if (mirrored) nameRight - name.measureText(nameText) else nameLeft
        c.drawText(nameText, nameX, baseline(name, y, 25f), name)
        if (hasClub) {
            y += 25f + 3f
            val clubX = if (mirrored) textRight - club.measureText(clubText) else textLeft
            c.drawText(clubText, clubX, baseline(club, y, 14f), club)
        }

        val labelText = who.optString("label").uppercase()
        val scoreText = score.toString()
        val stack = 16f + 3f + 37f
        var sy = r.centerY() - stack / 2f
        val colRight = if (mirrored) r.left + pad + scoreW else r.right - pad
        val colLeft = colRight - scoreW

        val lx = if (mirrored) colLeft else colRight - label.measureText(labelText)
        c.drawText(labelText, lx, baseline(label, sy, 16f), label)
        sy += 16f + 3f
        val sx = if (mirrored) colLeft else colRight - big.measureText(scoreText)
        c.drawText(scoreText, sx, baseline(big, sy, 37f), big)
    }

    /**
     * A picture in `box`: kept whole (contain, for crests) or filling it
     * (cover, for flags). Drawn from a copy already shrunk to the exact pixel
     * size it lands at — the canvas is in design units, and one big bilinear
     * shrink from the source smears a flag's stripes and a crest's lettering.
     */
    private fun image(c: Canvas, bitmap: Bitmap, box: RectF, cover: Boolean = false) {
        val fit = if (cover) maxOf(box.width() / bitmap.width, box.height() / bitmap.height)
                  else minOf(box.width() / bitmap.width, box.height() / bitmap.height)
        val w = bitmap.width * fit
        val h = bitmap.height * fit
        val dst = RectF(box.centerX() - w / 2f, box.centerY() - h / 2f, box.centerX() + w / 2f, box.centerY() + h / 2f)

        // On whole pixels, so the edges are crisp.
        val px = Math.round(dst.width() * k).coerceAtLeast(1)
        val py = Math.round(dst.height() * k).coerceAtLeast(1)
        val left = Math.round(dst.left * k) / k
        val top = Math.round(dst.top * k) / k
        val snapped = RectF(left, top, left + px / k, top + py / k)

        c.save()
        if (cover) c.clipRect(box)
        c.drawBitmap(shrunk(bitmap, px, py), null, snapped, Paint(Paint.FILTER_BITMAP_FLAG or Paint.ANTI_ALIAS_FLAG))
        c.restore()
    }

    private val shrunkCache = HashMap<String, Bitmap>()

    /** Halve until within 2x, then one last filtered step: a clean small copy. */
    private fun shrunk(source: Bitmap, w: Int, h: Int): Bitmap {
        val key = "${System.identityHashCode(source)}:$w:$h"
        shrunkCache[key]?.let { return it }

        var b = source
        while (b.width / 2 >= w && b.height / 2 >= h) {
            b = Bitmap.createScaledBitmap(b, b.width / 2, b.height / 2, true)
        }
        if (b.width != w || b.height != h) b = Bitmap.createScaledBitmap(b, w, h, true)

        shrunkCache[key] = b

        return b
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

    private fun segments(c: Canvas, state: State?) {
        val segs = state?.segments ?: return
        if (segs.isEmpty()) return

        val left = 26f
        val right = dw - 26f
        val top = designHeight - lift.coerceIn(LIFT_MIN, LIFT_MAX) - 5f
        val each = (right - left - 2f * (segs.size - 1)) / segs.size
        var x = left
        for (col in segs) {
            c.drawRect(x, top, x + each, top + 5f, fill(col))
            x += each + 2f
        }
    }

    /* ── Small things ──────────────────────────────────────────────────────── */

    // LINEAR + SUBPIXEL: the canvas is scaled from design units to pixels, and
    // without these the glyphs are hinted at the small design size and then
    // stretched — uneven spacing and soft, lumpy letters.
    private fun text(face: Typeface, size: Float, colour: Int, spacing: Float) = TextPaint(
        Paint.ANTI_ALIAS_FLAG or Paint.LINEAR_TEXT_FLAG or Paint.SUBPIXEL_TEXT_FLAG,
    ).apply {
        typeface = face
        textSize = size
        color = colour
        letterSpacing = spacing
    }

    private fun fill(colour: Int) = Paint(Paint.ANTI_ALIAS_FLAG).apply { color = colour }

    /** The baseline that centres one line of this paint in a box of height h. */
    private fun baseline(p: Paint, top: Float, h: Float): Float {
        val m = p.fontMetrics

        return top + h / 2f - (m.ascent + m.descent) / 2f
    }

    private fun colour(hex: String): Int = try {
        Color.parseColor(if (hex.startsWith("#")) hex else "#$hex")
    } catch (_: Throwable) {
        0xFF2A2724.toInt()
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

    private fun arabic(text: String) =
        text.any { it in '\u0600'..'\u06FF' || it in '\u0750'..'\u077F' || it in '\uFB50'..'\uFEFF' }

    /** Set a paint smaller until `text` fits `room` — never cut a name or a line. */
    private fun fit(p: TextPaint, text: String, room: Float) {
        val wide = p.measureText(text)
        if (wide > room && room > 0) p.textSize *= room / wide
    }

    /** Arabic letters join; spacing them apart breaks every word. */
    private fun joined(p: TextPaint, text: String): TextPaint {
        if (arabic(text)) p.letterSpacing = 0f

        return p
    }
}
