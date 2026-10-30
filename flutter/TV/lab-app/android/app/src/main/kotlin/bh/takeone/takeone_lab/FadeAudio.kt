package bh.takeone.takeone_lab

import androidx.annotation.OptIn
import androidx.media3.common.C
import androidx.media3.common.audio.AudioProcessor
import androidx.media3.common.audio.BaseAudioProcessor
import androidx.media3.common.util.UnstableApi
import java.nio.ByteBuffer
import java.nio.ByteOrder

/**
 * A short fade in and a fade out at the end, for a VS music track the operator
 * cut from their own song — so the cut points never click and the music falls
 * away under the bout instead of stopping.
 *
 * 16-bit PCM only (what the decoder hands Transformer); anything else is
 * refused and the caller keeps the song as it was.
 */
@OptIn(UnstableApi::class)
class FadeAudio(private val totalUs: Long, private val fadeInUs: Long, private val fadeOutUs: Long) : BaseAudioProcessor() {

    private var rate = 44100
    private var channels = 2
    private var frame = 0L

    override fun onConfigure(inputAudioFormat: AudioProcessor.AudioFormat): AudioProcessor.AudioFormat {
        if (inputAudioFormat.encoding != C.ENCODING_PCM_16BIT) {
            throw AudioProcessor.UnhandledAudioFormatException(inputAudioFormat)
        }
        rate = inputAudioFormat.sampleRate
        channels = inputAudioFormat.channelCount
        frame = 0

        return inputAudioFormat
    }

    override fun queueInput(inputBuffer: ByteBuffer) {
        val bytes = inputBuffer.remaining()
        val out = replaceOutputBuffer(bytes).order(ByteOrder.nativeOrder())
        val input = inputBuffer.order(ByteOrder.nativeOrder())

        while (input.remaining() >= 2 * channels) {
            val us = frame * 1_000_000L / rate
            val gain = when {
                us < fadeInUs -> us.toFloat() / fadeInUs
                us > totalUs - fadeOutUs -> ((totalUs - us).coerceAtLeast(0)).toFloat() / fadeOutUs
                else -> 1f
            }.coerceIn(0f, 1f)

            for (c in 0 until channels) {
                val s = input.short
                out.putShort((s * gain).toInt().coerceIn(Short.MIN_VALUE.toInt(), Short.MAX_VALUE.toInt()).toShort())
            }
            frame++
        }

        out.flip()
    }

    override fun onReset() {
        frame = 0
    }
}
