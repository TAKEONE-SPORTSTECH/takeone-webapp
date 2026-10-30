{{-- One question, drawn from `f` in the surrounding Alpine scope. Every
     author-supplied string goes through x-text — never x-html — so a label
     can never become markup. The other language sits under the question in
     grey, so a bilingual interviewer can read both at once. --}}
<div :class="errors[f.key] ? 'is-err' : ''">
  <div class="privtag" x-show="f.private || pageOf(f.key)?.private" x-cloak>● <span x-text="u('visitor')"></span></div>

  <template x-if="f.type !== 'note' && f.type !== 'checkbox'">
    <div style="margin-top:6px">
      <h2 class="qlabel"><span x-text="t(f,'label')"></span><span class="req" x-show="f.required"> *</span></h2>
      <p class="qother" dir="auto" x-text="alt(f,'label')"></p>
      <p class="qhelp" x-show="t(f,'help')" x-text="t(f,'help')"></p>
    </div>
  </template>

  {{-- one choice --}}
  <template x-if="f.type === 'radio'">
    <div>
      <p class="hint" x-text="u('pick1')"></p>
      <div class="opts">
        <template x-for="o in f.options" :key="o.value">
          <button type="button" class="opt" :class="values[f.key] === o.value && 'on'" @click="pick(f.key, o.value)" :aria-pressed="values[f.key] === o.value">
            <span class="box round"><svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round"><path d="M3 8.5 6.5 12 13 4"/></svg></span>
            <span x-text="t(o,'label')"></span>
          </button>
        </template>
      </div>
    </div>
  </template>

  {{-- several choices --}}
  <template x-if="f.type === 'checkboxes'">
    <div>
      <p class="hint" x-text="u('pickMany')"></p>
      <div class="opts">
        <template x-for="o in f.options" :key="o.value">
          <button type="button" class="opt" :class="values[f.key].includes(o.value) && 'on'" @click="toggle(f.key, o.value)" :aria-pressed="values[f.key].includes(o.value)">
            <span class="box"><svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round"><path d="M3 8.5 6.5 12 13 4"/></svg></span>
            <span x-text="t(o,'label')"></span>
          </button>
        </template>
      </div>
      <template x-if="f.other">
        <div><label class="small" :for="'o-'+f.key" x-text="u('other')"></label>
          <input class="inp" :id="'o-'+f.key" maxlength="300" x-model="values[f.key+'__other']"></div>
      </template>
    </div>
  </template>

  {{-- 1–5 scale --}}
  <template x-if="f.type === 'scale'">
    <div>
      <div class="scale">
        <template x-for="n in [1,2,3,4,5]" :key="n">
          <button type="button" :class="Number(values[f.key]) === n && 'on'" @click="pick(f.key, n)" x-text="n" :aria-pressed="Number(values[f.key]) === n"></button>
        </template>
      </div>
      <div class="ends"><span x-text="t(f,'low')"></span><span x-text="t(f,'high')"></span></div>
    </div>
  </template>

  {{-- numbers --}}
  <template x-if="f.type === 'numbers'">
    <div class="nums">
      <template x-for="r in f.rows" :key="r.value">
        <label><span x-text="t(r,'label')"></span>
          <input type="text" inputmode="numeric" pattern="[0-9]*" maxlength="7" placeholder="—" class="num"
                 :value="values[f.key][r.value] ?? ''" @input="values[f.key][r.value] = $event.target.value.replace(/\D/g,'') === '' ? '' : Number($event.target.value.replace(/\D/g,''))"></label>
      </template>
    </div>
  </template>

  {{-- short / long answers --}}
  <template x-if="['text','phone','email','date','number'].includes(f.type)">
    <input class="inp" style="margin-top:14px" maxlength="300" :inputmode="f.type === 'phone' ? 'tel' : (f.type === 'email' ? 'email' : 'text')"
           :placeholder="f.type === 'date' ? (lang === 'ar' ? 'يوم / شهر / سنة' : 'day / month / year') : u('typeHere')" x-model="values[f.key]">
  </template>
  <template x-if="f.type === 'textarea'">
    <textarea class="inp" style="margin-top:14px" maxlength="5000" :rows="Math.max(3, f.rows || 3)" :placeholder="u('typeHere')" x-model="values[f.key]"></textarea>
  </template>

  {{-- consent --}}
  <template x-if="f.type === 'checkbox'">
    <button type="button" class="check" :class="values[f.key] && 'on'" @click="values[f.key] = !values[f.key]; errors[f.key] = false" :aria-pressed="!!values[f.key]">
      <span class="box"><svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round"><path d="M3 8.5 6.5 12 13 4"/></svg></span>
      <span><b x-text="u('agree')" style="display:block"></b><span x-text="t(f,'label')"></span></span>
    </button>
  </template>

  {{-- must / nice / no grid --}}
  <template x-if="f.type === 'grid'">
    <div>
      <div class="gcount"><span class="num" x-text="gridDone(f) + ' / ' + f.rows.length"></span><span class="bar"><i :style="'width:' + (gridDone(f) / f.rows.length * 100) + '%'"></i></span></div>
      <div class="grow">
        <template x-for="r in f.rows" :key="r.value">
          <div class="row" :class="errors[f.key] && !values[f.key][r.value] && 'miss'">
            <b x-text="t(r,'label')"></b>
            <div class="pills">
              <template x-for="(c, ci) in f.columns" :key="c.value">
                <button type="button" :class="[values[f.key][r.value] === c.value && 'on', 'c' + ci]" @click="cell(f.key, r.value, c.value)" x-text="t(c,'label')" :aria-pressed="values[f.key][r.value] === c.value"></button>
              </template>
            </div>
          </div>
        </template>
      </div>
    </div>
  </template>

  {{-- rank top N --}}
  <template x-if="f.type === 'rank'">
    <div>
      <p class="hint" x-text="u('rankFrom')" x-show="f.from"></p>
      <div class="rank">
        <template x-for="r in rankRows(f)" :key="r.value">
          <button type="button" :class="rankPos(f, r.value) && 'on'" @click="rankTap(f, r.value)"
                  :disabled="!rankPos(f, r.value) && values[f.key].length >= (f.max || 5)">
            <span class="pos num" x-text="rankPos(f, r.value) || '·'"></span><span x-text="t(r,'label')"></span>
          </button>
        </template>
      </div>
    </div>
  </template>

  {{-- the visitor's private notes --}}
  <template x-if="f.type === 'note'">
    <div class="notebox">
      <div class="who" x-text="t(f,'label')"></div>
      <p x-text="t(f,'help')"></p>
      <textarea class="inp" rows="4" maxlength="5000" :placeholder="u('typeHere')" x-model="values[f.key]"></textarea>
    </div>
  </template>

  <template x-if="f.note">
    <div><label class="small" :for="'n-'+f.key" x-text="u('note')"></label>
      <input class="inp" :id="'n-'+f.key" maxlength="1000" x-model="values[f.key+'__note']"></div>
  </template>

  <p class="err" x-show="errors[f.key]" x-cloak x-text="u('required')"></p>
</div>
