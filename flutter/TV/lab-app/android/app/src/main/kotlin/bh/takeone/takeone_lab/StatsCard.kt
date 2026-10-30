package bh.takeone.takeone_lab

import android.content.res.AssetManager
import android.graphics.Bitmap
import android.graphics.Canvas
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
import org.json.JSONObject

/**
 * The card that closes a sent bout: its statistics, after the footage has
 * shown the winner declared.
 *
 * The same sheet as the bout page's PDF (App\Media\BoutStats, sent in the plan
 * as `stats`) in the competition report's navy and gold — the match and round,
 * the two corners either side of the final score, one sentence that tells the
 * bout, how the score moved, four figures, and red against blue. Laid out on a
 * 1920×1080 canvas (1080×1920 upright) and scaled to the frame, as VsScreen is.
 * A part the plan does not carry is simply left out.
 */
class StatsCard(private val plan: JSONObject, assets: AssetManager, val portrait: Boolean = false) {
    val W = if (portrait) 1080f else 1920f
    val H = if (portrait) 1920f else 1080f

    private val s = plan.optJSONObject("stats") ?: JSONObject()
    private val labels = plan.optJSONObject("stat_labels") ?: JSONObject()
    private val cornerNames = plan.optJSONObject("corner_labels") ?: JSONObject()
    private val people = mapOf("red" to (plan.optJSONObject("red") ?: JSONObject()), "blue" to (plan.optJSONObject("blue") ?: JSONObject()))

    private val bold = font(assets, "BarlowCondensed-Bold.ttf", "Tajawal-Bold.ttf")
    private val black = font(assets, "BarlowCondensed-Black.ttf", "Tajawal-Black.ttf")
    private val regular = font(assets, "BarlowCondensed-Regular.ttf", "Tajawal-Regular.ttf")

    private val navy = 0xFF0F1730.toInt()
    private val panel = 0xFF172245.toInt()
    private val line = 0x26FFFFFF
    private val gold = 0xFFD8B25F.toInt()
    private val goldSoft = 0xFFEDCD85.toInt()
    private val white = 0xFFFFFFFF.toInt()
    private val muted = 0xFF9AA6C4.toInt()
    private val ink = mapOf("red" to 0xFFE0473F.toInt(), "blue" to 0xFF3D7DE6.toInt())

    /** Is there anything to show? A plan without stats gets no card. */
    val available: Boolean get() = plan.optJSONObject("stats") != null

    fun draw(width: Int, height: Int): Bitmap {
        val out = Bitmap.createBitmap(width, height, Bitmap.Config.ARGB_8888)
        val c = Canvas(out)
        c.drawColor(navy)
        val k = minOf(width / W, height / H)
        c.translate((width - W * k) / 2f, (height - H * k) / 2f)
        c.scale(k, k)
        paint(c)

        return out
    }

    private fun paint(c: Canvas) {
        background(c)

        if (portrait) {
            val m = 60f
            header(c, RectF(m, 80f, W - m, 300f))
            faceoff(c, RectF(m, 330f, W - m, 640f))
            story(c, RectF(m, 670f, W - m, 760f))
            card(c, RectF(m, 790f, W - m, 1170f)) { chart(c, it) }
            tiles(c, RectF(m, 1200f, W - m, 1480f), cols = 2)
            card(c, RectF(m, 1510f, W - m, H - 70f)) { compare(c, it, compact = true) }
        } else {
            val m = 80f
            header(c, RectF(m, 56f, W - m, 196f))
            faceoff(c, RectF(m, 220f, W - m, 400f))
            story(c, RectF(m, 420f, W - m, 486f))
            card(c, RectF(m, 510f, 960f, 790f)) { chart(c, it) }
            tiles(c, RectF(m, 812f, 960f, H - 64f), cols = 4)
            card(c, RectF(990f, 510f, W - m, H - 64f)) { compare(c, it, compact = false) }
        }
    }

    /* ── The pieces ─────────────────────────────────────────────────────── */

    private fun background(c: Canvas) {
        c.drawRect(0f, 0f, W, H, Paint().apply {
            shader = LinearGradient(0f, 0f, 0f, H, intArrayOf(0xFF16213D.toInt(), navy), null, Shader.TileMode.CLAMP)
        })
        // The report band's gold stripes, top corner, faint.
        val p = Paint(Paint.ANTI_ALIAS_FLAG).apply { color = gold; alpha = 22 }
        val band = H * 0.3f
        c.save()
        c.clipRect(W * 0.55f, 0f, W, band)
        var x = W * 0.45f
        while (x < W + band) {
            c.drawPath(Path().apply {
                moveTo(x, 0f); lineTo(x + 34f, 0f); lineTo(x + 34f - band * 0.47f, band); lineTo(x - band * 0.47f, band); close()
            }, p)
            x += 100f
        }
        c.restore()
    }

    private fun header(c: Canvas, box: RectF) {
        c.drawText("BOUT STATISTICS", box.left, box.top + 28f, text(bold, 28f, gold, 0.28f))

        val tab = plan.optJSONObject("tab") ?: JSONObject()
        val match = tab.optString("match")
        val title = listOfNotNull(
            match.takeIf { it.isNotEmpty() }?.let { if (it.startsWith("EX")) "EXHIBITION ${it.removePrefix("EX")}" else "MATCH $it" },
            tab.optString("round").takeIf { it.isNotEmpty() }?.uppercase(),
        ).joinToString(" · ").ifEmpty { "BOUT" }
        val tp = text(black, if (portrait) 76f else 84f, white, 0.01f).apply { textSkewX = -0.18f }
        fit(tp, title, box.width())
        c.drawText(title, box.left, box.top + 40f - tp.fontMetrics.ascent, tp)

        val sub = listOf(plan.optString("division"), plan.optString("sub")).filter { it.isNotEmpty() }.joinToString(" · ")
        if (sub.isNotEmpty()) {
            val sp = text(regular, 30f, muted, 0.02f)
            arabicSafe(sp, sub)
            fit(sp, sub, box.width())
            c.drawText(sub, box.left, box.bottom - 6f, sp)
        }
    }

    private fun faceoff(c: Canvas, box: RectF) {
        val score = s.optJSONObject("score") ?: JSONObject()
        val winner = s.optString("winner")
        val mid = if (portrait) 300f else 340f
        val sx = box.centerX() - mid / 2f

        // The score, in the middle, on navy with a gold edge.
        val sbox = RectF(sx, box.top, sx + mid, box.bottom)
        c.drawRoundRect(sbox, 22f, 22f, Paint(Paint.ANTI_ALIAS_FLAG).apply { color = 0xFF0B1124.toInt() })
        c.drawRoundRect(sbox, 22f, 22f, Paint(Paint.ANTI_ALIAS_FLAG).apply { color = gold; style = Paint.Style.STROKE; strokeWidth = 4f })
        val big = "${score.optInt("red")}–${score.optInt("blue")}"
        val bp = text(black, if (portrait) 150f else 140f, white, 0f)
        fit(bp, big, mid - 30f)
        val bigY = sbox.centerY() + (-bp.fontMetrics.ascent - bp.fontMetrics.descent) / 2f - 16f
        c.drawText(big, sbox.centerX() - bp.measureText(big) / 2f, bigY, bp)
        val under = if (s.optBoolean("level_at_end")) "DECISION" else "FINAL"
        val up = text(bold, 26f, gold, 0.24f)
        c.drawText(under, sbox.centerX() - up.measureText(under) / 2f, sbox.bottom - 26f, up)

        // Each corner, on its own side, its colour along the outer edge.
        for (corner in listOf("red", "blue")) {
            val left = corner == "red"
            val cb = if (left) RectF(box.left, box.top, sx - 24f, box.bottom) else RectF(sx + mid + 24f, box.top, box.right, box.bottom)
            c.drawRoundRect(cb, 22f, 22f, Paint(Paint.ANTI_ALIAS_FLAG).apply { color = panel })
            val edge = if (left) RectF(cb.left, cb.top, cb.left + 12f, cb.bottom) else RectF(cb.right - 12f, cb.top, cb.right, cb.bottom)
            c.save()
            c.clipRect(edge)
            c.drawRoundRect(cb, 22f, 22f, Paint(Paint.ANTI_ALIAS_FLAG).apply { color = ink.getValue(corner) })
            c.restore()

            val pad = 40f
            val inner = cb.width() - pad * 2
            val who = people.getValue(corner)
            val tag = cornerNames.optString(corner, corner.uppercase()).uppercase()
            val tgp = text(bold, 28f, ink.getValue(corner), 0.2f)
            val nm = who.optString("name").uppercase()
            val np = text(black, if (portrait) 54f else 62f, white, 0.01f)
            arabicSafe(np, nm)
            fit(np, nm, inner)
            val club = who.optString("club")
            val cp = text(regular, 28f, muted, 0.04f)
            arabicSafe(cp, club)
            fit(cp, club, inner)

            fun x(p: TextPaint, t: String) = if (left) cb.left + pad else cb.right - pad - p.measureText(t)
            var y = cb.top + 54f
            c.drawText(tag, x(tgp, tag), y, tgp)
            y += 14f - np.fontMetrics.ascent
            c.drawText(nm, x(np, nm), y, np)
            if (club.isNotEmpty()) {
                y += 44f
                c.drawText(club, x(cp, club), y, cp)
            }
            if (winner == corner) {
                val chip = "WINNER"
                val chp = text(black, 26f, navy, 0.2f)
                val cw = chp.measureText(chip) + 36f
                val cx = if (left) cb.left + pad else cb.right - pad - cw
                val cy = cb.bottom - 62f
                c.drawRoundRect(RectF(cx, cy, cx + cw, cy + 40f), 8f, 8f, Paint(Paint.ANTI_ALIAS_FLAG).apply { color = gold })
                c.drawText(chip, cx + 18f, cy + 30f, chp)
            }
        }
    }

    /** The one sentence: who won, how, and how the lead moved. */
    private fun story(c: Canvas, box: RectF) {
        val winner = s.optString("winner")
        if (winner.isEmpty()) return
        val score = s.optJSONObject("score") ?: JSONObject()
        val other = if (winner == "red") "blue" else "red"
        val left = if (s.isNull("decided_left")) null else s.optDouble("decided_left")
        var head = "${cornerNames.optString(winner, winner.uppercase()).uppercase()} WON ${score.optInt(winner)}–${score.optInt(other)}"
        head += when {
            s.optBoolean("level_at_end") -> " ON A LEVEL SCORE"
            left != null && left <= 1.0 -> " WITH THE WINNING SCORE ON THE BELL"
            left != null -> " · LED FOR GOOD WITH ${clock(left)} LEFT"
            else -> ""
        }
        val parts = arrayListOf(head)
        if (s.optBoolean("comeback")) parts.add("CAME BACK FROM BEHIND")
        val changes = s.optInt("lead_changes")
        if (changes > 0) parts.add("$changes LEAD ${if (changes == 1) "CHANGE" else "CHANGES"}")
        val t = parts.joinToString("  ·  ")

        c.drawRoundRect(box, 16f, 16f, Paint(Paint.ANTI_ALIAS_FLAG).apply { color = 0x24D8B25F })
        c.drawRect(RectF(box.left, box.top, box.left + 8f, box.bottom), Paint().apply { color = gold })
        val p = text(bold, if (portrait) 30f else 34f, goldSoft, 0.08f)
        fit(p, t, box.width() - 60f)
        c.drawText(t, box.left + 32f, box.centerY() + (-p.fontMetrics.ascent - p.fontMetrics.descent) / 2f, p)
    }

    private fun card(c: Canvas, box: RectF, body: (RectF) -> Unit) {
        c.drawRoundRect(box, 22f, 22f, Paint(Paint.ANTI_ALIAS_FLAG).apply { color = panel })
        body(RectF(box.left + 32f, box.top + 28f, box.right - 32f, box.bottom - 24f))
    }

    /** A section title with the report's slanted gold mark before it. */
    private fun section(c: Canvas, x: Float, y: Float, title: String) {
        c.drawPath(Path().apply {
            moveTo(x + 8f, y - 18f); lineTo(x + 32f, y - 18f); lineTo(x + 24f, y - 6f); lineTo(x, y - 6f); close()
        }, Paint(Paint.ANTI_ALIAS_FLAG).apply { color = gold })
        c.drawText(title, x + 44f, y, text(bold, 26f, white, 0.14f))
    }

    /** How the score moved: both corners as steps over the bout's clock. */
    private fun chart(c: Canvas, box: RectF) {
        section(c, box.left, box.top + 18f, "HOW THE SCORE MOVED")
        val steps = s.optJSONArray("steps") ?: return
        if (steps.length() == 0) return
        val length = s.optDouble("length", 0.0).coerceAtLeast(1.0)
        val score = s.optJSONObject("score") ?: JSONObject()
        var top = maxOf(2, score.optInt("red"), score.optInt("blue"))
        top += top % 2
        val area = RectF(box.left + 40f, box.top + 50f, box.right - 6f, box.bottom - 40f)
        fun x(t: Double) = (area.left + (t / length) * area.width()).toFloat()
        fun y(v: Int) = area.bottom - v.toFloat() / top * area.height()

        // Who was leading, as soft shading.
        for (i in 0 until steps.length()) {
            val st = steps.getJSONObject(i)
            val end = if (i + 1 < steps.length()) steps.getJSONObject(i + 1).optDouble("at") else length
            val d = st.optInt("red") - st.optInt("blue")
            if (d == 0) continue
            c.drawRect(x(st.optDouble("at")), area.top, x(end), area.bottom,
                Paint().apply { color = if (d > 0) 0x33E0473F else 0x333D7DE6 })
        }
        val grid = Paint().apply { color = line; strokeWidth = 2f }
        val lab = text(bold, 22f, muted, 0f)
        val stepV = maxOf(2, (top / 4) / 2 * 2)
        var v = 0
        while (v <= top) {
            c.drawLine(area.left, y(v), area.right, y(v), grid)
            c.drawText("$v", area.left - 14f - lab.measureText("$v"), y(v) + 8f, lab)
            v += stepV
        }
        val tick = if (length > 150) 60.0 else 30.0
        var t = 0.0
        while (t <= length + 0.01) {
            val l = clock(t)
            c.drawText(l, (x(t) - lab.measureText(l) / 2f).coerceIn(area.left - 10f, area.right - lab.measureText(l)), box.bottom - 4f, lab)
            t += tick
        }
        for (corner in listOf("blue", "red")) {
            val path = Path().apply { moveTo(x(0.0), y(0)) }
            for (i in 1 until steps.length()) {
                val st = steps.getJSONObject(i)
                path.lineTo(x(st.optDouble("at")), y(steps.getJSONObject(i - 1).optInt(corner)))
                path.lineTo(x(st.optDouble("at")), y(st.optInt(corner)))
            }
            path.lineTo(x(length), y(steps.getJSONObject(steps.length() - 1).optInt(corner)))
            c.drawPath(path, Paint(Paint.ANTI_ALIAS_FLAG).apply {
                color = ink.getValue(corner); style = Paint.Style.STROKE; strokeWidth = 6f; strokeJoin = Paint.Join.ROUND
            })
        }
    }

    /** Four figures: first score, lead changes, how it was settled, late points. */
    private fun tiles(c: Canvas, box: RectF, cols: Int) {
        val first = s.optJSONObject("first")
        val left = if (s.isNull("decided_left")) null else s.optDouble("decided_left")
        val level = s.optBoolean("level_at_end")
        val items = listOf(
            Triple(first?.let { clock(it.optDouble("at")) } ?: "—",
                "FIRST SCORE" + (first?.let { " · " + cornerNames.optString(it.optString("corner"), "").uppercase() } ?: ""),
                first?.let { ink[it.optString("corner")] } ?: gold),
            Triple("${s.optInt("lead_changes")}", "LEAD CHANGES", 0xFF7C6BF5.toInt()),
            Triple(if (level) "LEVEL" else (left?.let { clock(it) } ?: "—"), if (level) "SETTLED BY DECISION" else "LEFT WHEN DECIDED", gold),
            Triple("${s.optInt("late_share")}%", "POINTS IN THE LAST THIRD", 0xFFEB6834.toInt()),
        )
        val rows = (items.size + cols - 1) / cols
        val gap = 20f
        val tw = (box.width() - gap * (cols - 1)) / cols
        val th = (box.height() - gap * (rows - 1)) / rows
        items.forEachIndexed { i, (value, label, colour) ->
            val x = box.left + (i % cols) * (tw + gap)
            val y = box.top + (i / cols) * (th + gap)
            val r = RectF(x, y, x + tw, y + th)
            c.drawRoundRect(r, 18f, 18f, Paint(Paint.ANTI_ALIAS_FLAG).apply { color = panel })
            c.save()
            c.clipRect(RectF(r.left, r.top, r.left + 10f, r.bottom))
            c.drawRoundRect(r, 18f, 18f, Paint(Paint.ANTI_ALIAS_FLAG).apply { color = colour })
            c.restore()
            val vp = text(black, if (portrait) 76f else 64f, white, 0f)
            fit(vp, value, tw - 50f)
            c.drawText(value, r.left + 30f, r.top + 22f - vp.fontMetrics.ascent, vp)
            val lp = text(bold, 21f, muted, 0.12f)
            fit(lp, label, tw - 50f)
            c.drawText(label, r.left + 30f, r.bottom - 24f, lp)
        }
    }

    /** Red against blue: value · two-sided bar · value, one row per figure. */
    private fun compare(c: Canvas, box: RectF, compact: Boolean) {
        val red = s.optJSONObject("red") ?: JSONObject()
        val blue = s.optJSONObject("blue") ?: JSONObject()
        section(c, box.left, box.top + 18f,
            "${cornerNames.optString("red", "RED").uppercase()} AGAINST ${cornerNames.optString("blue", "BLUE").uppercase()}")

        val rows = ArrayList<Triple<String, Pair<Double, String>, Pair<Double, String>>>()
        fun num(o: JSONObject, k: String) = o.optDouble(k, 0.0).let { if (it.isNaN()) 0.0 else it }
        fun row(label: String, k: String) = rows.add(Triple(label, num(red, k) to fmt(num(red, k)), num(blue, k) to fmt(num(blue, k))))
        row("POINTS", "points")
        val rv = red.optJSONObject("by_value") ?: JSONObject()
        val bv = blue.optJSONObject("by_value") ?: JSONObject()
        for (n in 1..4) {
            val a = num(rv, "$n")
            val b = num(bv, "$n")
            if (a > 0 || b > 0) {
                val name = labels.optString("$n").ifEmpty { "$n PT" }.uppercase()
                rows.add(Triple("$name ($n)", a to fmt(a), b to fmt(b)))
            }
        }
        row("PENALTIES", "penalties")
        rows.add(Triple("TIME IN THE LEAD", num(red, "lead_seconds") to clock(num(red, "lead_seconds")), num(blue, "lead_seconds") to clock(num(blue, "lead_seconds"))))
        row("LONGEST RUN", "longest_run")
        if (!compact) row("POINTS A MINUTE", "per_minute")

        val area = RectF(box.left, box.top + 50f, box.right, box.bottom)
        val rh = minOf(84f, area.height() / rows.size)
        val vw = 110f
        rows.forEachIndexed { i, (label, a, b) ->
            val y = area.top + i * rh
            val ap = text(black, 46f, ink.getValue("red"), 0f)
            val bp = text(black, 46f, ink.getValue("blue"), 0f)
            val base = y + rh * 0.62f
            c.drawText(a.second, area.left, base, ap)
            c.drawText(b.second, area.right - bp.measureText(b.second), base, bp)
            val lp = text(bold, 21f, muted, 0.14f)
            c.drawText(label, area.centerX() - lp.measureText(label) / 2f, y + rh * 0.36f, lp)
            val track = RectF(area.left + vw, y + rh * 0.5f, area.right - vw, y + rh * 0.5f + 12f)
            c.drawRoundRect(track, 6f, 6f, Paint(Paint.ANTI_ALIAS_FLAG).apply { color = line })
            val sum = (a.first + b.first).coerceAtLeast(0.0001)
            val aw = (track.width() * (a.first / sum)).toFloat()
            val bw = (track.width() * (b.first / sum)).toFloat()
            c.save()
            c.clipRect(track)
            c.drawRoundRect(RectF(track.left, track.top, track.left + aw, track.bottom), 6f, 6f, Paint(Paint.ANTI_ALIAS_FLAG).apply { color = ink.getValue("red") })
            c.drawRoundRect(RectF(track.right - bw, track.top, track.right, track.bottom), 6f, 6f, Paint(Paint.ANTI_ALIAS_FLAG).apply { color = ink.getValue("blue") })
            c.restore()
        }
    }

    /* ── Small things ───────────────────────────────────────────────────── */

    private fun fmt(v: Double) = if (v == Math.floor(v)) v.toLong().toString() else String.format(java.util.Locale.US, "%.1f", v)

    private fun clock(seconds: Double): String {
        val t = Math.round(seconds).toInt().coerceAtLeast(0)
        return "${t / 60}:${(t % 60).toString().padStart(2, '0')}"
    }

    /** Never cut a word: shrink the text until it fits its width. */
    private fun fit(p: TextPaint, t: String, width: Float) {
        val w = p.measureText(t)
        if (w > width && w > 0f) p.textSize *= width / w
    }

    private fun text(face: Typeface, size: Float, colour: Int, spacing: Float) = TextPaint(
        Paint.ANTI_ALIAS_FLAG or Paint.LINEAR_TEXT_FLAG or Paint.SUBPIXEL_TEXT_FLAG,
    ).apply {
        typeface = face
        textSize = size
        color = colour
        letterSpacing = spacing
    }

    private fun arabicSafe(p: TextPaint, text: String) {
        if (text.any { it in '؀'..'ۿ' || it in 'ݐ'..'ݿ' || it in 'ﭐ'..'﻿' }) p.letterSpacing = 0f
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
