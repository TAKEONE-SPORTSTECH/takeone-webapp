{{--
    The console Players tab — the pane: the entrant list on the left, one
    person's sheet on the right. Drop it wherever a console keeps its tab (a
    Bouts card pane, or a pop-up of its own); everything it does lives in
    scoreboard::shared.players.runtime, included once inside the console's
    #stage. ORGANISERS only — the console renders neither part otherwise.

    A port of the Karate console's Players tab (karate/scoreboard/partials/
    bouts-card), sport-neutral, with its own ids (`cp…`) and its own palette
    so it reads the same in every console. Docs: docs/events/console-players.md
--}}
<div class="cpl" id="cpl">
  <div class="cpl-split">
    <div class="cpl-index">
      <input id="cpFind" class="cpl-find" type="search" autocomplete="off" spellcheck="false"
             placeholder="{{ __('scoreboard::players.pl_find') }}"
             aria-label="{{ __('scoreboard::players.pl_find') }}">
      <div id="cpList" class="cpl-scroll"></div>
    </div>

    <div class="cpl-right">
      <div id="cpEmpty" class="cpl-empty">
        <span style="font-size:44px;color:var(--cp-gold);">◎</span>
        {{ __('scoreboard::players.pl_pick') }}
      </div>

      <div id="cpEdit" class="cpl-edit" hidden>
        <div id="cpTitle" class="cpl-title"></div>
        {{-- Their weight classes, as cards — add, move, remove (signed-in organisers). --}}
        <div id="cpClasses" class="cpl-wcstrip" hidden></div>

        <div class="cpl-card">
          <div class="cpl-ed">
            <div class="cpl-photocol">
              <button type="button" id="cpPhoto" class="cpl-photo" aria-label="{{ __('scoreboard::players.pl_photo') }}"></button>
              <button type="button" id="cpPhotoBtn" class="cpl-btn">{{ __('scoreboard::players.pl_photo') }}</button>
              <button type="button" id="cpVsBtn" class="cpl-btn cpl-btn-gold" hidden>{{ __('scoreboard::players.pl_vs_btn') }}</button>
              <div class="cpl-why" id="cpWhy-photo"></div>
            </div>

            <div class="cpl-form">
              <section class="cpl-sec">
                <h4 class="cpl-sech">{{ __('scoreboard::players.pl_sec_identity') }}</h4>
                <div class="cpl-row">
                  <span class="cpl-cap">{{ __('scoreboard::players.pl_name') }}</span>
                  <input id="cpName" class="cpl-in" type="text" maxlength="120" autocomplete="off">
                  <div class="cpl-why" id="cpWhy-name"></div>
                </div>
                <div class="cpl-row">
                  <span class="cpl-cap">{{ __('scoreboard::players.pl_club') }}</span>
                  <div id="cpClub" class="cpl-dd"></div>
                  <div class="cpl-why" id="cpWhy-club"></div>
                </div>
                <div class="cpl-row">
                  {{-- The platform's own country picker, bridged by two window events. --}}
                  <div id="cpNatWrap" class="cpl-nat" x-data="{ nat: '' }"
                       x-init="$watch('nat', v => window.dispatchEvent(new CustomEvent('cp-nat', { detail: v || '' })))"
                       @cp-nat-set.window="nat = $event.detail || ''">
                    <x-fields.country id="cpNationality" name="cp_nationality" model="nat" :inline="true"
                        :label="__('scoreboard::players.pl_nationality')"
                        wrapper-class="" label-class="cpl-cap" trigger-class="cpl-ddbtn cpl-country-trigger" />
                  </div>
                  <div class="cpl-why" id="cpNatNote"></div>
                  <div class="cpl-why" id="cpWhy-nationality"></div>
                </div>
              </section>

              <section class="cpl-sec">
                <h4 class="cpl-sech">{{ __('scoreboard::players.pl_sec_competition') }}</h4>
                {{-- Date of birth OR age: one or the other, never both. --}}
                <div class="cpl-row">
                  <div style="display:flex;align-items:center;gap:8px;">
                    <button type="button" class="cpl-seg" data-mode="dob">{{ __('scoreboard::players.pl_dob') }}</button>
                    <button type="button" class="cpl-seg" data-mode="age">{{ __('scoreboard::players.pl_age') }}</button>
                  </div>
                  <div id="cpDobBox" style="display:flex;gap:8px;align-items:center;">
                    <input id="cpDobD" class="cpl-in" style="width:74px;text-align:center;" inputmode="numeric" maxlength="2" placeholder="DD">
                    <input id="cpDobM" class="cpl-in" style="width:74px;text-align:center;" inputmode="numeric" maxlength="2" placeholder="MM">
                    <input id="cpDobY" class="cpl-in" style="width:110px;text-align:center;" inputmode="numeric" maxlength="4" placeholder="YYYY">
                  </div>
                  <div id="cpAgeBox" style="display:flex;gap:8px;align-items:center;" hidden>
                    <input id="cpAge" class="cpl-in" style="width:110px;text-align:center;" inputmode="numeric" maxlength="2">
                    <span style="font-size:20px;color:var(--cp-muted);">{{ __('scoreboard::players.pl_years') }}</span>
                  </div>
                  <div class="cpl-why" id="cpWhy-birthdate"></div>
                </div>
                <div class="cpl-pair">
                  <div class="cpl-row">
                    <span class="cpl-cap">{{ __('scoreboard::players.pl_gender') }}</span>
                    {{-- The platform's own gender toggle, bridged by two window events. --}}
                    <div id="cpGender" x-data="{ g: '' }"
                         x-init="$watch('g', v => window.dispatchEvent(new CustomEvent('cp-gender', { detail: v })))"
                         @cp-gender-set.window="g = $event.detail">
                      <x-fields.gender-toggle model="g"
                          :male-label="\Illuminate\Support\Js::from(__('scoreboard::players.male'))"
                          :female-label="\Illuminate\Support\Js::from(__('scoreboard::players.female'))" />
                    </div>
                    <div class="cpl-why" id="cpWhy-gender"></div>
                  </div>
                  <div class="cpl-row">
                    <span class="cpl-cap">{{ __('scoreboard::players.pl_weight') }}</span>
                    <div style="display:flex;gap:8px;align-items:center;">
                      <input id="cpWeight" class="cpl-in" style="width:130px;text-align:center;" inputmode="decimal" maxlength="6">
                      <span style="font-size:22px;color:var(--cp-muted);">kg</span>
                    </div>
                    <div class="cpl-why" id="cpWhy-weight"></div>
                  </div>
                </div>
                <div class="cpl-row">
                  <span class="cpl-cap">{{ __('scoreboard::players.pl_belt') }}</span>
                  <div id="cpBelt" class="cpl-dd"></div>
                  <div class="cpl-why" id="cpWhy-belt_colour"></div>
                </div>
              </section>
            </div>
          </div>
        </div>

        <div class="cpl-foot">
          <div id="cpSay" class="cpl-hint" style="flex:1;"></div>
          <button type="button" id="cpSave" class="cpl-save" disabled>{{ __('scoreboard::players.pl_save') }}</button>
        </div>
      </div>
      {{-- Choosing a weight class to add them to, or to move one to. --}}
      <div id="cpClassPick" class="cpl-wcpick" hidden></div>
    </div>
  </div>
</div>
