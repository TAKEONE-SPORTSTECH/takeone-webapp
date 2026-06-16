{{-- Members results region — rendered on full page load and returned standalone for AJAX search. --}}
@if($members->count() > 0)
    <div class="flex flex-wrap gap-4 mb-4" id="membersGrid">
        @foreach($members as $member)
            <x-member-card :member="$member" :href="route('member.show', $member->uuid)" :guardian="$member->guardians->first()?->guardian ?? null"
                class="w-80 max-w-full"
                data-member-id="{{ $member->id }}"
                data-popup-url="{{ route('admin.platform.members.popup', $member->id) }}" />
        @endforeach
    </div>

    <!-- Pagination -->
    <div class="flex justify-center mb-4">
        {{ $members->withQueryString()->links() }}
    </div>
@else
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body text-center py-12">
            <i class="bi bi-people text-muted-foreground text-6xl"></i>
            <h5 class="mt-3 mb-2">No Members Found</h5>
            <p class="text-muted-foreground mb-0">
                @if($search)
                    No members match your search criteria.
                @else
                    No members registered on the platform yet.
                @endif
            </p>
        </div>
    </div>
@endif
