package bh.takeone.takeone_lab

import android.graphics.Bitmap
import android.graphics.Canvas
import android.graphics.Color
import android.graphics.Paint
import android.graphics.RectF
import androidx.annotation.OptIn
import androidx.media3.common.util.UnstableApi
import androidx.media3.effect.BitmapOverlay
import kotlin.math.PI
import kotlin.math.cos

/**
 * The VS screen's intro animation — the bout page's own (#vsScreen keyframes in
 * personal/{mobile,desktop}/bout-video): the panels slide in, the top lines drop
 * in, each fighter's details rise, the VS slams down, the hall flashes white,
 * and the VS keeps pulsing.
 *
 * Timings, in seconds (the page's CSS):
 *   red / blue panel   0.00 + 0.90   slide in from −105% / +105% of its width
 *   top lines          0.50 + 0.80   drop 32.4 px, fade in
 *   red details        0.70 + 0.80   rise 43.2 px, fade in
 *   blue details       0.85 + 0.80
 *   VS slam            1.10 + 0.70   ×3.4 −6° → ×0.92 +1° (60%) → ×1.06 (80%) → ×1
 *   chips              1.30 + 0.80   rise 43.2 px, fade in
 *   flash              1.50 + 0.90   white 0 → 0.85 (12%) → 0
 *   VS pulse           ×1 → ×1.045 → ×1 every 2.4 s, all along
 * Eased with the page's cubic-bezier(.22, 1, .36, 1).
 *
 * The player animates the SAME layers with the same table (lib/vs_intro.dart);
 * the two change together.
 */
object VsAnim {
    const val END = 2.4

    /** cubic-bezier(.22, 1, .36, 1), solved for y at x = p. */
    fun ease(p: Double): Double {
        val x = p.coerceIn(0.0, 1.0)
        var lo = 0.0
        var hi = 1.0
        var t = x
        repeat(24) {
            val bx = 3 * (1 - t) * (1 - t) * t * 0.22 + 3 * (1 - t) * t * t * 0.36 + t * t * t
            if (bx < x) lo = t else hi = t
            t = (lo + hi) / 2
        }

        return 3 * (1 - t) * (1 - t) * t * 1.0 + 3 * (1 - t) * t * t * 1.0 + t * t * t
    }

    private fun progress(t: Double, delay: Double, length: Double) = ((t - delay) / length).coerceIn(0.0, 1.0)

    /** Draw every layer as it stands at `t`, on a canvas in design units. */
    fun compose(c: Canvas, layers: List<VsScreen.Layer>, t: Double) {
        val paint = Paint(Paint.FILTER_BITMAP_FLAG or Paint.ANTI_ALIAS_FLAG)
        // Upright, the panels come in from the top and the bottom (vsPanelT/B).
        val canvas = layers.firstOrNull { it.name == "base" }?.rect ?: RectF(0f, 0f, 1920f, 1080f)
        val upright = canvas.height() > canvas.width()

        for (l in layers) {
            var dx = 0f
            var dy = 0f
            var alpha = 1f
            var scale = 1f
            var rotate = 0f

            when (l.name) {
                "red" -> {
                    val off = (1 - ease(progress(t, 0.0, 0.9))).toFloat()
                    if (upright) dy = -1.05f * l.rect.height() * off else dx = -1.05f * l.rect.width() * off
                }
                "blue" -> {
                    val off = (1 - ease(progress(t, 0.0, 0.9))).toFloat()
                    if (upright) dy = 1.05f * l.rect.height() * off else dx = 1.05f * l.rect.width() * off
                }
                "top" -> {
                    val e = ease(progress(t, 0.5, 0.8)).toFloat()
                    alpha = e; dy = -32.4f * (1 - e)
                }
                "infoRed", "infoBlue", "chips" -> {
                    val delay = when (l.name) { "infoRed" -> 0.7; "infoBlue" -> 0.85; else -> 1.3 }
                    val e = ease(progress(t, delay, 0.8)).toFloat()
                    alpha = e; dy = 43.2f * (1 - e)
                }
                "vs" -> {
                    val p = ease(progress(t, 1.1, 0.7))
                    // Piecewise between the slam's keyframes (0, .6, .8, 1).
                    val (s, r, a) = when {
                        p < 0.6 -> { val q = p / 0.6; Triple(3.4 + (0.92 - 3.4) * q, -6 + 7 * q, q) }
                        p < 0.8 -> { val q = (p - 0.6) / 0.2; Triple(0.92 + (1.06 - 0.92) * q, 1 - 0.5 * q, 1.0) }
                        else -> { val q = (p - 0.8) / 0.2; Triple(1.06 + (1 - 1.06) * q, 0.5 - 0.5 * q, 1.0) }
                    }
                    val pulse = 1 + 0.045 * (0.5 - 0.5 * cos(2 * PI * t / 2.4))
                    scale = (s * pulse).toFloat()
                    rotate = r.toFloat()
                    alpha = if (t < 1.1) 0f else a.toFloat()
                }
            }

            if (alpha <= 0.001f) continue

            c.save()
            if (scale != 1f || rotate != 0f) {
                val px = l.rect.centerX()
                val py = l.rect.centerY()
                c.rotate(rotate, px, py)
                c.scale(scale, scale, px, py)
            }
            paint.alpha = (alpha * 255).toInt().coerceIn(0, 255)
            c.drawBitmap(l.bitmap, null, RectF(l.rect.left + dx, l.rect.top + dy, l.rect.right + dx, l.rect.bottom + dy), paint)
            c.restore()
        }

        // The flash as the VS lands.
        val f = progress(t, 1.5, 0.9)
        if (f > 0 && f < 1) {
            val e = ease(f)
            val a = if (e < 0.12) e / 0.12 * 0.85 else 0.85 * (1 - (e - 0.12) / 0.88)
            c.drawRect(canvas, Paint().apply { color = Color.WHITE; this.alpha = (a * 255).toInt().coerceIn(0, 255) })
        }
    }
}

/**
 * The animated VS screen over the intro of a sent video: one frame per video
 * frame, composed from layers drawn once (VsScreen.layers) at the output size.
 */
@OptIn(UnstableApi::class)
class VsAnimOverlay(private val width: Int, private val height: Int, private val layers: List<VsScreen.Layer>) : BitmapOverlay() {

    private val buffers = arrayOf(
        Bitmap.createBitmap(width, height, Bitmap.Config.ARGB_8888),
        Bitmap.createBitmap(width, height, Bitmap.Config.ARGB_8888),
    )
    private var showing = 0
    private var firstUs = -1L

    override fun getBitmap(presentationTimeUs: Long): Bitmap {
        if (firstUs < 0) firstUs = presentationTimeUs
        val t = (presentationTimeUs - firstUs) / 1_000_000.0

        val next = 1 - showing
        val b = buffers[next]
        b.eraseColor(Color.BLACK)
        val c = Canvas(b)
        val canvas = layers.firstOrNull { it.name == "base" }?.rect ?: RectF(0f, 0f, 1920f, 1080f)
        val k = minOf(width / canvas.width(), height / canvas.height())
        c.translate((width - canvas.width() * k) / 2f, (height - canvas.height() * k) / 2f)
        c.scale(k, k)
        VsAnim.compose(c, layers, t)
        showing = next

        return b
    }
}
