@extends('layouts.app')

@section('title', 'New design preview')

@section('content')
<div class="px-4 sm:px-6 lg:px-8 py-10">
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6" style="max-width:560px;margin:0 auto">
        <p class="text-xs font-bold uppercase tracking-wide text-primary"><i class="bi bi-stars me-1"></i>Preview</p>
        <h1 class="text-xl font-bold mt-2">The new TAKEONE design</h1>
        <p class="text-sm text-muted-foreground mt-2">
            As a super-admin you see the new design by default. This switch changes <b>your own session only</b>; everyone else keeps the classic design.
            Ready so far: the new top bar and phone tabs, and the new Explore.
        </p>
        <p class="text-sm mt-4">Now: <b>{{ $on ? 'New design ON' : 'Classic design' }}</b></p>
        <div class="flex gap-2 mt-4">
            <form method="POST" action="{{ route('me.new-ui.on') }}">@csrf
                <button type="submit" class="bg-primary text-white px-4 py-2 rounded-lg hover:bg-primary/90 transition-colors font-medium">Turn on</button>
            </form>
            <form method="POST" action="{{ route('me.new-ui.off') }}">@csrf
                <button type="submit" class="border border-primary text-primary bg-transparent px-4 py-2 rounded-md text-sm font-medium hover:bg-primary hover:text-white transition-colors">Back to classic</button>
            </form>
        </div>
    </div>
</div>
@endsection
