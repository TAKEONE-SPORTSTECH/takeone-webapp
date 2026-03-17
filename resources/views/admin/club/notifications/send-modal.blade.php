{{-- Send Notification Modal --}}
@php
    $clubMembers = \App\Models\Membership::where('tenant_id', $club->id)
        ->with('user')
        ->get()
        ->pluck('user')
        ->filter()
        ->values();
@endphp

<div x-show="showNotificationModal"
     x-cloak
     class="fixed inset-0 z-50 flex items-center justify-center"
     x-transition:enter="transition ease-out duration-200"
     x-transition:enter-start="opacity-0"
     x-transition:enter-end="opacity-100"
     x-transition:leave="transition ease-in duration-150"
     x-transition:leave-start="opacity-100"
     x-transition:leave-end="opacity-0">

    <div class="absolute inset-0 bg-black/50" @click="showNotificationModal = false"></div>

    <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-lg mx-4 overflow-hidden"
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0 scale-95"
         x-transition:enter-end="opacity-100 scale-100"
         x-data="notificationModal()">

        {{-- Header --}}
        <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100">
            <div class="flex items-center gap-3">
                <div class="w-9 h-9 rounded-lg bg-primary/10 flex items-center justify-center">
                    <i class="bi bi-send text-primary"></i>
                </div>
                <div>
                    <h3 class="font-semibold text-gray-800 text-base">Send Notification</h3>
                    <p class="text-xs text-gray-400">{{ $club->club_name }}</p>
                </div>
            </div>
            <button type="button" @click="showNotificationModal = false; resetForm()"
                    class="text-gray-400 hover:text-gray-600 transition-colors cursor-pointer">
                <i class="bi bi-x-lg text-lg"></i>
            </button>
        </div>

        {{-- Form --}}
        <form id="sendNotificationForm" action="{{ route('admin.club.notifications.store', $club->slug) }}" @submit.prevent="submitForm()">
            <div class="px-6 py-5 space-y-4 max-h-[70vh] overflow-y-auto">

                {{-- Subject --}}
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Subject</label>
                    <input type="text" name="subject" maxlength="255" required
                           placeholder="Notification subject..."
                           class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:border-primary/50 focus:ring-1 focus:ring-primary/20">
                </div>

                {{-- Message --}}
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1 flex justify-between">
                        <span>Message</span>
                        <span class="text-gray-400 font-normal" x-text="charCount + ' / ' + maxChars"></span>
                    </label>
                    <textarea name="message" rows="4" required maxlength="5000"
                              placeholder="Write your message here..."
                              @input="charCount = $event.target.value.length"
                              class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:border-primary/50 focus:ring-1 focus:ring-primary/20 resize-none"></textarea>
                </div>

                {{-- Recipients --}}
                <div>
                    <div class="flex items-center justify-between mb-2">
                        <label class="text-sm font-medium text-gray-700">
                            Recipients
                            <span class="text-gray-400 font-normal" x-text="'(' + selectedIds.length + ' of {{ $clubMembers->count() }} selected)'"></span>
                        </label>
                        <button type="button" @click="toggleAll()"
                                class="text-xs font-medium transition-colors cursor-pointer"
                                :class="allSelected ? 'text-red-400 hover:text-red-500' : 'text-primary hover:text-primary/80'">
                            <span x-text="allSelected ? 'Deselect All' : 'Select All'"></span>
                        </button>
                    </div>

                    {{-- Search --}}
                    <div class="relative mb-2">
                        <i class="bi bi-search absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-sm"></i>
                        <input type="text" x-model="search" placeholder="Search members..."
                               class="w-full pl-8 pr-3 py-2 text-sm border border-gray-200 rounded-lg focus:outline-none focus:border-primary/50 focus:ring-1 focus:ring-primary/20">
                    </div>

                    {{-- Member List --}}
                    <div class="border border-gray-200 rounded-xl overflow-hidden">
                        <div class="max-h-48 overflow-y-auto divide-y divide-gray-100">
                            <template x-for="member in filteredMembers" :key="member.id">
                                <div class="flex items-center gap-3 px-4 py-2.5 hover:bg-gray-50 cursor-pointer transition-colors"
                                     :class="selectedIds.includes(member.id) ? 'bg-primary/5' : ''"
                                     @click="toggleMember(member.id)">
                                    <div class="w-8 h-8 rounded-full bg-primary/15 flex items-center justify-center shrink-0 text-xs font-semibold text-primary"
                                         x-text="getInitials(member.name)"></div>
                                    <div class="flex-1 min-w-0">
                                        <p class="text-sm font-medium text-gray-800 truncate mb-0" x-text="member.name"></p>
                                        <p class="text-xs text-gray-400 truncate mb-0" x-text="member.email"></p>
                                    </div>
                                    <div class="w-5 h-5 rounded-md border-2 flex items-center justify-center shrink-0 transition-all"
                                         :class="selectedIds.includes(member.id) ? 'bg-primary border-primary' : 'border-gray-300'">
                                        <i x-show="selectedIds.includes(member.id)" class="bi bi-check text-white text-xs leading-none"></i>
                                    </div>
                                </div>
                            </template>

                            <div x-show="filteredMembers.length === 0" x-cloak class="px-4 py-6 text-center text-sm text-gray-400">
                                No members found matching "<span x-text="search"></span>"
                            </div>
                        </div>
                    </div>
                </div>

            </div>

            {{-- Footer --}}
            <div class="px-6 py-4 border-t border-gray-100 flex items-center justify-between gap-3">
                <a href="{{ route('admin.club.notifications', $club->slug) }}"
                   class="text-sm text-gray-400 hover:text-primary transition-colors no-underline">
                    <i class="bi bi-clock-history me-1"></i> View History
                </a>
                <div class="flex gap-2">
                    <button type="button" @click="showNotificationModal = false; resetForm()"
                            class="px-4 py-2 text-sm border border-gray-200 rounded-lg text-gray-600 hover:bg-gray-50 transition-all cursor-pointer">
                        Cancel
                    </button>
                    <button type="submit" :disabled="sending || selectedIds.length === 0"
                            class="px-5 py-2 text-sm bg-primary text-white rounded-lg hover:bg-primary/90 transition-all font-medium cursor-pointer disabled:opacity-60 disabled:cursor-not-allowed flex items-center gap-2">
                        <span x-show="sending" class="w-4 h-4 border-2 border-white/30 border-t-white rounded-full animate-spin"></span>
                        <span x-text="sending ? 'Sending...' : 'Send'"></span>
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

@once
@push('scripts')
<script>
    document.addEventListener('alpine:init', () => {
        Alpine.data('notificationModal', () => ({
            sending: false,
            charCount: 0,
            maxChars: 5000,
            search: '',
            selectedIds: [],
            members: @json($clubMembers->map(fn($m) => ['id' => $m->id, 'name' => $m->full_name, 'email' => $m->email])->values()),

            get filteredMembers() {
                if (!this.search) return this.members;
                const q = this.search.toLowerCase();
                return this.members.filter(m =>
                    m.name.toLowerCase().includes(q) || m.email.toLowerCase().includes(q)
                );
            },

            get allSelected() {
                return this.members.length > 0 && this.members.every(m => this.selectedIds.includes(m.id));
            },

            toggleMember(id) {
                if (this.selectedIds.includes(id)) {
                    this.selectedIds = this.selectedIds.filter(i => i !== id);
                } else {
                    this.selectedIds.push(id);
                }
            },

            toggleAll() {
                if (this.allSelected) {
                    this.selectedIds = [];
                } else {
                    this.selectedIds = this.members.map(m => m.id);
                }
            },

            getInitials(name) {
                return name.split(' ').slice(0, 2).map(w => w[0]).join('').toUpperCase();
            },

            resetForm() {
                this.selectedIds = [];
                this.search = '';
                this.charCount = 0;
                document.getElementById('sendNotificationForm').reset();
            },

            submitForm() {
                if (this.selectedIds.length === 0) {
                    showToast('Please select at least one member.', 'error');
                    return;
                }
                this.sending = true;
                const form = document.getElementById('sendNotificationForm');
                const data = new FormData(form);

                const isAll = this.allSelected;
                data.append('recipient_type', isAll ? 'all' : 'selected');
                if (!isAll) {
                    this.selectedIds.forEach(id => data.append('recipient_ids[]', id));
                }

                fetch(form.action, {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content },
                    body: data
                })
                .then(r => r.json())
                .then(res => {
                    this.sending = false;
                    if (res.success) {
                        showToast(res.message, 'success');
                        this.showNotificationModal = false;
                        this.resetForm();
                    } else {
                        showToast(res.message || 'Something went wrong.', 'error');
                    }
                })
                .catch(() => {
                    this.sending = false;
                    showToast('Failed to send notification.', 'error');
                });
            }
        }));
    });
</script>
@endpush
@endonce
