{{--
    The empty state for either tab of the people page.

    One partial because both tabs, and both KINDS of empty (nothing entered yet,
    and a search that matched nothing), should look identical — a list that
    changes shape when it has nothing in it reads as a different screen.

    Expects $icon (bi-*), $title, $body.
--}}
<div class="bg-white rounded-2xl border border-gray-100 shadow-sm px-6 py-10 text-center">
    <div class="w-14 h-14 rounded-2xl bg-muted grid place-items-center mx-auto">
        <i class="bi {{ $icon }} text-2xl text-muted-foreground"></i>
    </div>
    <p class="font-bold text-sm text-foreground mt-3">{{ $title }}</p>
    <p class="text-xs text-muted-foreground mt-1 max-w-[38ch] mx-auto leading-relaxed">{{ $body }}</p>
</div>
