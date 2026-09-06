{{--
    Empty state for a public-profile panel.

    Every panel on the athlete profile is always present, even when it has
    nothing in it: a profile that quietly drops a section reads as a different
    kind of profile rather than an empty one, and the reader cannot tell which
    they are looking at. So each panel says "nothing yet" in the same voice.
--}}
@props(['icon' => 'bi bi-inbox', 'title' => null, 'message' => null])

<div style="background:#fff;border-radius:18px;box-shadow:0 6px 20px rgba(28,16,72,.07);padding:26px 20px;text-align:center">
    <span style="display:grid;place-items:center;width:46px;height:46px;margin:0 auto;border-radius:14px;background:#f2effe;color:#8f70f6;font-size:20px">
        <i class="{{ $icon }}"></i>
    </span>
    @if($title)
        <p style="margin:12px 0 0;font-size:14px;font-weight:700;color:#1c1c28">{{ $title }}</p>
    @endif
    @if($message)
        <p style="margin:5px auto 0;max-width:230px;font-size:12px;line-height:1.5;color:#8a8fa3">{{ $message }}</p>
    @endif
</div>
