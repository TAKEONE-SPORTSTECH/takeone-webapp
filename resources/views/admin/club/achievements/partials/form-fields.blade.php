<div class="space-y-5">

    {{-- ── Title & Short Title ── --}}
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div class="md:col-span-2">
            <label class="form-label">Title <span class="text-red-500">*</span> <span class="text-xs font-normal text-muted-foreground">(detail header)</span></label>
            <input type="text" name="title" class="form-control" required
                   placeholder="e.g. Thailand Open Championships 2023" x-model="formData.title">
        </div>
        <div>
            <label class="form-label">Short Title <span class="text-xs font-normal text-muted-foreground">(card title)</span></label>
            <input type="text" name="short_title" class="form-control"
                   placeholder="e.g. Thailand Open Championships" x-model="formData.short_title">
        </div>
        <div>
            <label class="form-label">Type Icon <span class="text-xs font-normal text-muted-foreground">(emoji, e.g. 🏆 🥋 ⭐)</span></label>
            <input type="text" name="type_icon" class="form-control" maxlength="4"
                   placeholder="🏆" x-model="formData.type_icon">
        </div>
    </div>

    {{-- ── Location & Date ── --}}
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <div>
            <label class="form-label">Location</label>
            <input type="text" name="location" class="form-control"
                   placeholder="Bangkok, Thailand" x-model="formData.location">
        </div>
        <div>
            <label class="form-label">Date Label <span class="text-xs font-normal text-muted-foreground">(card, e.g. "Nov 2023")</span></label>
            <input type="text" name="date_label" class="form-control"
                   placeholder="Nov 2023" x-model="formData.date_label">
        </div>
        <div>
            <label class="form-label">Date <span class="text-xs font-normal text-muted-foreground">(picker)</span></label>
            <input type="date" name="achievement_date" class="form-control" x-model="formData.achievement_date">
        </div>
    </div>

    {{-- ── Category ── --}}
    <div>
        <label class="form-label">Category <span class="text-xs font-normal text-muted-foreground">(detail meta, e.g. "World Taekwondo G2 Event")</span></label>
        <input type="text" name="category" class="form-control"
               placeholder="World Taekwondo G2 Event" x-model="formData.category">
    </div>

    {{-- ── Story / Description ── --}}
    <div>
        <label class="form-label">Story <span class="text-xs font-normal text-muted-foreground">(detail description)</span></label>
        <textarea name="description" class="form-control" rows="3"
                  placeholder="Under the leadership of..." x-model="formData.description"></textarea>
    </div>

    {{-- ── Medals ── --}}
    <div>
        <label class="form-label">Medals</label>
        <div class="grid grid-cols-3 gap-3">
            <div>
                <label class="form-label text-xs flex items-center gap-1.5"><span class="text-base">🥇</span> Gold</label>
                <input type="number" name="medals_gold" class="form-control" min="0"
                       placeholder="0" x-model="formData.medals_gold">
            </div>
            <div>
                <label class="form-label text-xs flex items-center gap-1.5"><span class="text-base">🥈</span> Silver</label>
                <input type="number" name="medals_silver" class="form-control" min="0"
                       placeholder="0" x-model="formData.medals_silver">
            </div>
            <div>
                <label class="form-label text-xs flex items-center gap-1.5"><span class="text-base">🥉</span> Bronze</label>
                <input type="number" name="medals_bronze" class="form-control" min="0"
                       placeholder="0" x-model="formData.medals_bronze">
            </div>
        </div>
    </div>

    {{-- ── Bouts & Wins ── --}}
    <div class="grid grid-cols-2 gap-4">
        <div>
            <label class="form-label">Total Bouts</label>
            <input type="number" name="bouts_count" class="form-control" min="0"
                   placeholder="6" x-model="formData.bouts_count">
        </div>
        <div>
            <label class="form-label">Wins</label>
            <input type="number" name="wins_count" class="form-control" min="0"
                   placeholder="4" x-model="formData.wins_count">
        </div>
    </div>

    {{-- ── Chips / Tags ── --}}
    <div x-data="{
        chips: [],
        chipInput: '',
        init() {
            try { this.chips = JSON.parse(formData.chips || '[]'); } catch(e) { this.chips = []; }
        },
        addChip() {
            const v = this.chipInput.trim().replace(/,$/, '');
            if (v && !this.chips.includes(v)) { this.chips.push(v); this.sync(); }
            this.chipInput = '';
        },
        removeChip(i) { this.chips.splice(i, 1); this.sync(); },
        sync() { formData.chips = JSON.stringify(this.chips); }
    }" x-init="init()">
        <label class="form-label">Chips / Tags <span class="text-xs font-normal text-muted-foreground">(press Enter or comma to add)</span></label>
        <input type="hidden" name="chips" :value="formData.chips">
        <div class="flex flex-wrap gap-2 p-2 border border-border rounded-lg bg-muted/30 min-h-[42px] cursor-text"
             @click="$refs.chipField.focus()">
            <template x-for="(chip, i) in chips" :key="i">
                <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full bg-primary/10 text-primary text-xs font-medium">
                    <span x-text="chip"></span>
                    <button type="button" @click.stop="removeChip(i)"
                            class="text-primary/60 hover:text-primary leading-none">&times;</button>
                </span>
            </template>
            <input x-ref="chipField" type="text" class="border-0 bg-transparent outline-none text-sm flex-1 min-w-[120px] p-1"
                   placeholder="World Ranking Points…"
                   x-model="chipInput"
                   @keydown.enter.prevent="addChip()"
                   @keydown.comma.prevent="addChip()">
        </div>
    </div>

    {{-- ── Athletes ── --}}
    <div x-data="{
        athletes: [{ name: '', role: '' }, { name: '', role: '' }],
        init() {
            try {
                const parsed = JSON.parse(formData.athletes || '[]');
                if (parsed.length) this.athletes = parsed;
            } catch(e) {}
        },
        add() { if (this.athletes.length < 4) { this.athletes.push({ name: '', role: '' }); this.sync(); } },
        remove(i) { this.athletes.splice(i, 1); this.sync(); },
        sync() { formData.athletes = JSON.stringify(this.athletes.filter(a => a.name || a.role)); }
    }" x-init="init()">
        <label class="form-label">Athletes <span class="text-xs font-normal text-muted-foreground">(up to 4 — initials appear on card)</span></label>
        <input type="hidden" name="athletes" :value="formData.athletes">
        <div class="space-y-2">
            <template x-for="(ath, i) in athletes" :key="i">
                <div class="grid grid-cols-[1fr_2fr_auto] gap-2 items-center">
                    <input type="text" class="form-control form-control-sm" placeholder="Name"
                           x-model="ath.name" @input="sync()">
                    <input type="text" class="form-control form-control-sm" placeholder="Role • Result (e.g. Senior Poomsae • Gold)"
                           x-model="ath.role" @input="sync()">
                    <button type="button" @click="remove(i)"
                            class="text-muted-foreground hover:text-destructive transition-colors text-lg leading-none">&times;</button>
                </div>
            </template>
        </div>
        <button type="button" @click="add()"
                x-show="athletes.length < 4"
                class="mt-2 text-xs text-primary hover:underline flex items-center gap-1">
            <i class="bi bi-plus-circle"></i> Add athlete
        </button>
    </div>

    <hr class="border-border">

    {{-- ── Tag & Status ── --}}
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div>
            <label class="form-label">Tag Text <span class="text-red-500">*</span></label>
            <input type="text" name="tag" class="form-control" required
                   placeholder="e.g. Tournament Medals" x-model="formData.tag">
        </div>
        <div>
            <label class="form-label">Status</label>
            <select name="status" class="form-control" x-model="formData.status">
                <option value="active">Active</option>
                <option value="inactive">Inactive</option>
            </select>
        </div>
        <div>
            <label class="form-label">Sort Order</label>
            <input type="number" name="sort_order" class="form-control" min="0"
                   placeholder="0" x-model="formData.sort_order">
        </div>
        <div>
            <label class="form-label">Tag Icon</label>
            <input type="hidden" name="tag_icon" :value="formData.tag_icon">
            <div class="relative">
                <button type="button"
                        @click="showIconPicker = !showIconPicker"
                        class="form-control flex items-center gap-2 cursor-pointer text-left w-full">
                    <i :class="'bi ' + formData.tag_icon" class="text-lg flex-shrink-0"></i>
                    <span class="flex-1 truncate text-sm"
                          x-text="icons.find(i => i.value === formData.tag_icon)?.label ?? formData.tag_icon"></span>
                    <i class="bi bi-chevron-down text-xs text-muted-foreground flex-shrink-0"
                       :class="showIconPicker ? 'rotate-180' : ''"
                       style="transition:transform .15s;"></i>
                </button>
                <div x-show="showIconPicker" x-cloak @click.outside="showIconPicker = false"
                     class="absolute left-0 right-0 z-50 mt-1 bg-white border border-border rounded-xl shadow-lg p-3"
                     style="max-height:260px;overflow-y:auto;">
                    <div class="grid grid-cols-5 gap-1">
                        <template x-for="icon in icons" :key="icon.value">
                            <button type="button"
                                    @click="formData.tag_icon = icon.value; showIconPicker = false"
                                    :class="formData.tag_icon === icon.value ? 'bg-primary text-white' : 'text-foreground hover:bg-muted'"
                                    class="flex flex-col items-center gap-1 p-2 rounded-lg transition-colors"
                                    :title="icon.label">
                                <i :class="'bi ' + icon.value" class="text-xl leading-none"></i>
                                <span class="text-xs leading-tight truncate w-full text-center" x-text="icon.label"></span>
                            </button>
                        </template>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <hr class="border-border">

    {{-- ── Images ── --}}
    <div>
        <label class="form-label font-semibold">Images</label>
        <p class="text-xs text-muted-foreground mb-2">First image is the card background. All images appear in the detail popup.</p>
        <div id="achievementExistingPreviews" class="flex flex-wrap gap-2 mb-2"></div>
        <input type="hidden" name="keep_extra_images" id="keepExtraImagesInput" value="[]">
        <div id="achievementNewPreviews" class="flex flex-wrap gap-2 mb-2"></div>
        <div id="achievementBase64Inputs"></div>
        <button type="button" onclick="openAchievementCropper()"
                class="px-4 py-2 border border-gray-300 rounded-lg text-sm font-medium text-gray-600 hover:bg-gray-50 transition-colors flex items-center gap-2 mt-1">
            <i class="bi bi-camera"></i> Add Image
        </button>
    </div>

    <input type="hidden" name="bg_from" value="#f59e0b">
    <input type="hidden" name="bg_to" value="#f97316">
</div>
