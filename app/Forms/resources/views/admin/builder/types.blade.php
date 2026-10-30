{{-- Add a question: one tap per kind of answer. --}}
<div class="fb-types">
    <template x-for="t in types" :key="t.type">
        <button type="button" class="fb-type" @click="addField(t.type)"><i class="bi" :class="t.icon"></i><span x-text="t.label"></span></button>
    </template>
</div>
