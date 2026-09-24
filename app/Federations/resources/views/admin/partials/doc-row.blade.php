{{-- One document slot: what is on file, and a picker to add or replace it. Posts as documents[{kind}]. --}}
<div class="flex items-center gap-3 p-3 rounded-xl border border-gray-100 bg-white">
    <span class="w-10 h-10 rounded-xl flex items-center justify-center flex-shrink-0"
          :class="documents['{{ $kind }}'] ? 'bg-accent text-primary' : 'bg-muted text-muted-foreground'">
        <i class="bi" :class="documents['{{ $kind }}'] ? 'bi-file-earmark-check-fill' : 'bi-file-earmark-arrow-up'"></i>
    </span>
    <div class="min-w-0 flex-1">
        <p class="text-sm font-semibold text-gray-900 mb-0">{{ __('federations::messages.doc_'.$kind) }}</p>
        <template x-if="pendingDocs['{{ $kind }}']">
            <p class="text-xs text-primary mb-0 truncate"><i class="bi bi-arrow-up-circle me-1"></i><span x-text="pendingDocs['{{ $kind }}']"></span></p>
        </template>
        <template x-if="!pendingDocs['{{ $kind }}'] && documents['{{ $kind }}']">
            <a :href="documents['{{ $kind }}'].url" class="text-xs text-muted-foreground hover:text-primary truncate block" data-no-shell x-text="documents['{{ $kind }}'].name"></a>
        </template>
        <template x-if="!pendingDocs['{{ $kind }}'] && !documents['{{ $kind }}']">
            <p class="text-xs text-amber-600 mb-0">{{ __('federations::messages.stage_missing') }}</p>
        </template>
        <p x-show="hasErr('documents.{{ $kind }}')" x-text="err('documents.{{ $kind }}')" class="text-xs text-red-600 mt-1 mb-0"></p>
    </div>
    <label class="px-3 py-2 rounded-lg text-xs font-medium border border-primary text-primary hover:bg-primary hover:text-white transition-colors cursor-pointer flex-shrink-0">
        <span x-text="documents['{{ $kind }}'] ? @js(__('federations::messages.doc_replace')) : @js(__('federations::messages.doc_upload'))"></span>
        <input type="file" name="documents[{{ $kind }}]" accept="application/pdf,image/jpeg,image/png" class="hidden" @change="pickDoc('{{ $kind }}', $event)">
    </label>
    <button type="button" x-show="documents['{{ $kind }}']" @click="removeDoc('{{ $kind }}')"
            class="w-9 h-9 rounded-lg flex items-center justify-center text-red-600 hover:bg-red-50 transition-colors flex-shrink-0" aria-label="{{ __('federations::messages.doc_remove') }}">
        <i class="bi bi-trash"></i>
    </button>
</div>
