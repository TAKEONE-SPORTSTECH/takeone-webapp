<?php

namespace Database\Seeders;

use Database\Seeders\Concerns\SeedsActivityContent;
use Illuminate\Database\Seeder;

/**
 * BATCH 2 — remaining combat arts (incl. Karate/Kung-Fu/Wrestling variant
 * splits) + fitness / studio disciplines. Bilingual EN+AR, image prompts.
 */
class ActivityContentSeederB extends Seeder
{
    use SeedsActivityContent;

    protected function entries(): array
    {
        return array_merge($this->combat(), $this->fitness());
    }

    private function combat(): array
    {
        return [
            /* ---- Karate — Goju-Ryu ---- */
            [
                'slug' => 'karate-goju-ryu', 'name' => 'Karate (Goju-Ryu)', 'name_ar' => 'الكاراتيه (غوجو-ريو)',
                'icon' => 'bi-person-arms-up', 'replaces' => 'karate',
                'en' => [
                    'intro' => '🥋 "Hard-soft style" — an Okinawan karate blending powerful closed-range strikes with circular, breathing-based soft techniques.',
                    'history' => 'Founded by <strong>Chojun Miyagi</strong> in the 1930s from Naha-te and Chinese white-crane kung fu, Goju-Ryu ("go" = hard, "ju" = soft) is one of the four main traditional Japanese karate styles.',
                    'focus' => 'Close-quarter fighting: grabbing, short powerful strikes, hard blocks alternating with soft, circular deflections, and breath-power via the signature <em>Sanchin</em> kata.',
                    'benefits' => ['💥 Powerful close-range striking and body conditioning.', '🌬️ Breathing control and core stability from Sanchin.', '🧘 Balance of tension and relaxation.', '🛡️ Practical short-distance self-defence.'],
                    'limitations' => ['🤼 Little ground fighting.', '🥇 Not an Olympic style (WKF sport-karate is the competitive branch).', '🧍 Close-range focus needs range management vs. kickers.'],
                    'rules' => ['🥋 Traditional grading via kihon, kata (esp. Sanchin/Tensho) and kumite.', '🎯 Sport bouts follow WKF-style controlled-contact scoring.', '🚫 Excessive contact penalised.'],
                ],
                'ar' => [
                    'intro' => '🥋 «الأسلوب الصلب-اللين» — كاراتيه أوكيناوي يمزج الضربات القوية القريبة بالتقنيات اللينة الدائرية القائمة على التنفّس.',
                    'history' => 'أسّسه <strong>تشوجون مياغي</strong> في ثلاثينيات القرن الماضي من ناها-تي وكونغ فو الكركي الأبيض الصيني، والغوجو-ريو («غو» صلب، «جو» لين) أحد أساليب الكاراتيه اليابانية التقليدية الأربعة الرئيسة.',
                    'focus' => 'القتال القريب: الإمساك والضربات القصيرة القوية والصدّات الصلبة المتناوبة مع الإبعاد اللين الدائري وقوة التنفّس عبر كاتا <em>سانتشين</em> المميّزة.',
                    'benefits' => ['💥 ضرب قريب قوي وإعداد للجسم.', '🌬️ تحكّم بالتنفّس وثبات للجذع من سانتشين.', '🧘 توازن بين الشدّ والاسترخاء.', '🛡️ دفاع عن النفس عملي في المدى القريب.'],
                    'limitations' => ['🤼 قتال أرضي محدود.', '🥇 ليس أسلوباً أولمبياً (الفرع التنافسي هو كاراتيه WKF).', '🧍 التركيز القريب يحتاج إدارة مسافة أمام الراكلين.'],
                    'rules' => ['🥋 تقييم تقليدي عبر الكيهون والكاتا (خصوصاً سانتشين/تينشو) والكوميتيه.', '🎯 نزالات رياضية باحتساب WKF المُتحكَّم فيه.', '🚫 يُعاقب على التلامس المفرط.'],
                ],
                'links' => [
                    ['label' => 'Wikipedia — Gōjū-ryū', 'url' => 'https://en.wikipedia.org/wiki/G%C5%8Dj%C5%AB-ry%C5%AB'],
                    ['label' => 'Wikipedia — Chōjun Miyagi', 'url' => 'https://en.wikipedia.org/wiki/Ch%C5%8Djun_Miyagi'],
                    ['label' => 'World Karate Federation', 'url' => 'https://www.wkf.net/'],
                ],
                'image_prompt' => "A cinematic, ultra-detailed hero poster in dramatic dark fantasy game-art style, 16:9 widescreen. Two Goju-Ryu karate warriors — a muscular male and a fierce female — mid-action performing a close-range hooking block and short power punch, wearing white gi with black belts and an Okinawan white-crane emblem, glowing half-fire half-water (hard-soft) energy swirling around them. Behind them looms a translucent white crane and coiled dragon amid stormy skies. Background: an Okinawan seaside castle with torii and kanji banners, sea-spray and embers in the wind. Bold 3D brush title 'KARATE — GOJU-RYU' at top with subtitle. Moody chiaroscuro, rim-lit silhouettes, teal-crimson-white palette, hyper-detailed 4K game cover art.",
            ],
            /* ---- Karate — Wado-Ryu ---- */
            [
                'slug' => 'karate-wado-ryu', 'name' => 'Karate (Wado-Ryu)', 'name_ar' => 'الكاراتيه (وادو-ريو)',
                'icon' => 'bi-person-arms-up', 'replaces' => 'karate',
                'en' => [
                    'intro' => '🥋 "The way of harmony/peace" — a karate style infused with jujitsu body-shifting, evasion and fluid, efficient movement over raw force.',
                    'history' => 'Founded by <strong>Hironori Ohtsuka</strong> in 1934, Wado-Ryu blends Shotokan karate with Shindō Yōshin-ryū jujitsu, prizing <em>tai sabaki</em> (body evasion) and softness over clashing power.',
                    'focus' => 'Evasion and redirection: shifting off the line of attack, minimal telegraphing, and quick, relaxed counters rather than force-on-force blocking.',
                    'benefits' => ['🌀 Superb footwork, timing and evasion.', '🧠 Efficient, relaxed movement and body awareness.', '🛡️ Practical avoidance-based self-defence.', '🤝 Lower-impact than hard styles.'],
                    'limitations' => ['💥 Less emphasis on raw power/conditioning than Kyokushin.', '🤼 Minimal ground work.', '🥇 Not an Olympic style.'],
                    'rules' => ['🥋 Grading via kihon, kata and kumite with jujitsu-influenced paired forms.', '🎯 Sport kumite uses WKF-style controlled contact.', '🚫 Excessive contact penalised.'],
                ],
                'ar' => [
                    'intro' => '🥋 «طريق الانسجام/السلام» — أسلوب كاراتيه مُشبع بتحريك الجسم والمراوغة من الجوجيتسو والحركة الانسيابية الفعّالة بدل القوة الغاشمة.',
                    'history' => 'أسّسه <strong>هيرونوري أوتسوكا</strong> عام 1934، ويمزج الوادو-ريو بين كاراتيه شوتوكان وجوجيتسو شيندو يوشين-ريو، مقدّراً <em>تاي ساباكي</em> (مراوغة الجسم) واللين على صدام القوة.',
                    'focus' => 'المراوغة وإعادة التوجيه: التحرّك خارج خط الهجوم، وتقليل الإشارات المُسبقة، وردود سريعة مسترخية بدل الصدّ بالقوة.',
                    'benefits' => ['🌀 حركة قدمين وتوقيت ومراوغة ممتازة.', '🧠 حركة فعّالة مسترخية ووعي جسدي.', '🛡️ دفاع عن النفس عملي قائم على التفادي.', '🤝 تأثير أقل من الأساليب الصلبة.'],
                    'limitations' => ['💥 تركيز أقل على القوة/الإعداد من الكيوكوشين.', '🤼 عمل أرضي محدود.', '🥇 ليس أسلوباً أولمبياً.'],
                    'rules' => ['🥋 تقييم عبر الكيهون والكاتا والكوميتيه مع أنماط ثنائية متأثرة بالجوجيتسو.', '🎯 كوميتيه رياضي باحتساب WKF المُتحكَّم فيه.', '🚫 يُعاقب على التلامس المفرط.'],
                ],
                'links' => [
                    ['label' => 'Wikipedia — Wadō-ryū', 'url' => 'https://en.wikipedia.org/wiki/Wad%C5%8D-ry%C5%AB'],
                    ['label' => 'Wikipedia — Hironori Ōtsuka', 'url' => 'https://en.wikipedia.org/wiki/Hironori_%C5%8Ctsuka'],
                    ['label' => 'World Karate Federation', 'url' => 'https://www.wkf.net/'],
                ],
                'image_prompt' => "A cinematic, ultra-detailed hero poster in dramatic dark fantasy game-art style, 16:9 widescreen. Two Wado-Ryu karateka — a lean male and a swift female — mid-action evading and countering with a fluid body-shift and reverse punch, wearing white gi and black belts with a peaceful-dove/harmony emblem, glowing soft white wind-spirals showing evasion arcs. Behind them a translucent white dove and koi spirit amid calm-then-stormy skies. Background: a serene Japanese garden dojo with torii and banners, drifting petals in the wind. Bold 3D brush title 'KARATE — WADO-RYU' with subtitle. Moody chiaroscuro, rim-lit silhouettes, white-slate-blue palette, hyper-detailed 4K game cover art.",
            ],
            /* ---- Karate — WKF sport ---- */
            [
                'slug' => 'karate-wkf', 'name' => 'Karate (WKF Sport)', 'name_ar' => 'الكاراتيه (WKF الرياضي)',
                'icon' => 'bi-person-arms-up', 'replaces' => 'karate',
                'en' => [
                    'intro' => '🥋 Modern competitive karate under the <strong>World Karate Federation</strong> — fast, controlled point-fighting (kumite) and athletic forms (kata), as seen at the Tokyo 2020 Olympics.',
                    'history' => 'The WKF (founded 1990) unified sport karate across styles into a single ruleset. Karate made its Olympic debut at Tokyo 2020 with kata and kumite events.',
                    'focus' => 'Explosive, controlled scoring techniques with strict etiquette. Kumite rewards speed, distance and control; kata rewards power, precision and athleticism of solo forms.',
                    'benefits' => ['⚡ Elite speed, reactions and distance control.', '🎯 Precision and body control under pressure.', '🏆 Clear pathway to national/Olympic competition.', '🧠 Discipline and sportsmanship.'],
                    'limitations' => ['🎛️ Control-based rules reward touches over power.', '🤼 No grappling or ground work.', '🥋 Sport focus differs from traditional self-defence styles.'],
                    'rules' => ['🥋 Two events: kumite (sparring) and kata (judged forms).', '🎯 Kumite scores: yuko (1) punch, waza-ari (2) body kick, ippon (3) head kick/takedown-strike.', '⏱️ Senior kumite bouts run 3 minutes; win by 8-point gap, most points, or senshu.', '🚫 Excessive contact, exits and passivity are penalised.'],
                ],
                'ar' => [
                    'intro' => '🥋 الكاراتيه التنافسي الحديث تحت <strong>الاتحاد العالمي للكاراتيه (WKF)</strong> — قتال نقاط سريع مُتحكَّم فيه (كوميتيه) وأنماط رياضية (كاتا)، كما ظهر في أولمبياد طوكيو 2020.',
                    'history' => 'وحّد الـWKF (تأسّس 1990) الكاراتيه الرياضي عبر الأساليب في قانون واحد. وظهر الكاراتيه أولمبياً لأول مرة في طوكيو 2020 بفعاليتي الكاتا والكوميتيه.',
                    'focus' => 'تقنيات تسجيل انفجارية مُتحكَّم فيها مع آداب صارمة. يكافئ الكوميتيه السرعة والمسافة والتحكم، وتكافئ الكاتا قوة ودقة ورياضية الأنماط الفردية.',
                    'benefits' => ['⚡ سرعة وردود فعل وتحكّم بالمسافة رفيعة.', '🎯 دقة وتحكّم بالجسم تحت الضغط.', '🏆 مسار واضح للمنافسة الوطنية/الأولمبية.', '🧠 انضباط وروح رياضية.'],
                    'limitations' => ['🎛️ قوانين التحكّم تكافئ اللمسة على القوة.', '🤼 لا مصارعة ولا عمل أرضي.', '🥋 تركيزه الرياضي يختلف عن أساليب الدفاع التقليدية.'],
                    'rules' => ['🥋 فعاليتان: كوميتيه (قتال) وكاتا (أنماط تُقيَّم).', '🎯 نقاط الكوميتيه: يوكو (1) لكمة، وازا-آري (2) ركلة جسم، إيبون (3) ركلة رأس/إسقاط وضرب.', '⏱️ نزالات الكبار 3 دقائق؛ والفوز بفارق 8 نقاط أو بأكثر النقاط أو بالـسِنشو.', '🚫 يُعاقب على التلامس المفرط والخروج والسلبية.'],
                ],
                'links' => [
                    ['label' => 'World Karate Federation (WKF)', 'url' => 'https://www.wkf.net/'],
                    ['label' => 'Olympics — Karate', 'url' => 'https://www.olympics.com/en/sports/karate/'],
                    ['label' => 'Wikipedia — Karate at the Summer Olympics', 'url' => 'https://en.wikipedia.org/wiki/Karate_at_the_Summer_Olympics'],
                ],
                'image_prompt' => "A cinematic, ultra-detailed hero poster in dramatic dark fantasy game-art style, 16:9 widescreen. Two WKF sport-karate athletes — a male and a female — mid-action with a snapping scoring reverse punch and a high controlled kick, wearing pristine competition gi with red and blue belts and WKF-style emblems, glowing crisp white speed-lines and spark bursts. Behind them a translucent tiger and eagle spirit amid an arena storm. Background: a modern Olympic karate tatami arena with flags and banners, confetti and sparks in the wind. Bold 3D title 'KARATE — WKF SPORT' with subtitle. Moody chiaroscuro, rim-lit silhouettes, red-blue-white palette, hyper-detailed 4K game cover art.",
            ],
            /* ---- BJJ — No-Gi ---- */
            [
                'slug' => 'brazilian-jiu-jitsu-nogi', 'name' => 'Brazilian Jiu-Jitsu (No-Gi)', 'name_ar' => 'الجوجيتسو البرازيلي (بدون البدلة)',
                'icon' => 'bi-person-arms-up', 'replaces' => 'brazilian-jiu-jitsu',
                'en' => [
                    'intro' => '🥋 Grappling without the kimono — a faster, more slippery, MMA-relevant form of BJJ fought in rash-guard and shorts, relying on body control rather than cloth grips.',
                    'history' => 'No-Gi grew from gi BJJ as grapplers adapted to MMA and submission-wrestling events; modern submission grappling (ADCC, EBI) made it a sport in its own right, blending BJJ with wrestling and catch wrestling.',
                    'focus' => 'Faster pace with grips on the body, wrists and neck instead of cloth. Heavy on scrambles, wrestling entries, leg-locks and body-lock control.',
                    'benefits' => ['⚡ Fast, athletic scrambles and cardio.', '🥋 Directly transferable to MMA and self-defence.', '🦵 Rich modern leg-lock game.', '💪 Functional strength and grip endurance.'],
                    'limitations' => ['🧼 Fewer control points without cloth grips — harder to slow the game.', '👊 No striking.', '⏳ Fast pace can be demanding for beginners.'],
                    'rules' => ['🩳 Rash-guard and shorts; no gi grips.', '🏆 Win by submission or points (ADCC/IBJJF no-gi rules).', '🦵 Many leg-locks legal (varies by ruleset/level).', '🚫 Slams and illegal locks penalised.'],
                ],
                'ar' => [
                    'intro' => '🥋 مصارعة بلا كيمونو — شكل أسرع وأكثر انزلاقاً وأقرب للفنون المختلطة من الجوجيتسو، يُخاض بقميص مطّاطي وشورت ويعتمد على التحكّم بالجسم بدل قبضات القماش.',
                    'history' => 'نشأ «بدون البدلة» من الجوجيتسو بالبدلة حين تكيّف المصارعون مع الفنون المختلطة وبطولات الإخضاع؛ وجعلته بطولات مثل ADCC وEBI رياضةً مستقلة تمزج الجوجيتسو بالمصارعة والكاتش.',
                    'focus' => 'إيقاع أسرع بقبضات على الجسم والمعصمين والرقبة بدل القماش. اعتماد كبير على التشابكات ومداخل المصارعة وأقفال الساق والتحكّم بقفل الجسم.',
                    'benefits' => ['⚡ تشابكات سريعة رياضية ولياقة قلبية.', '🥋 ينتقل مباشرةً للفنون المختلطة والدفاع عن النفس.', '🦵 لعبة أقفال ساق حديثة غنية.', '💪 قوة وظيفية وتحمّل قبضة.'],
                    'limitations' => ['🧼 نقاط تحكّم أقل بلا قماش — يصعب إبطاء اللعب.', '👊 لا ضرب.', '⏳ الإيقاع السريع قد يكون شاقاً للمبتدئين.'],
                    'rules' => ['🩳 قميص مطّاطي وشورت؛ لا قبضات بدلة.', '🏆 الفوز بالإخضاع أو بالنقاط (قوانين ADCC/IBJJF بلا بدلة).', '🦵 كثير من أقفال الساق مسموح (يختلف حسب القانون/المستوى).', '🚫 يُعاقب على الطرح العنيف والأقفال الممنوعة.'],
                ],
                'links' => [
                    ['label' => 'ADCC Submission Fighting', 'url' => 'https://adcombat.com/'],
                    ['label' => 'IBJJF', 'url' => 'https://ibjjf.com/'],
                    ['label' => 'Wikipedia — Submission wrestling', 'url' => 'https://en.wikipedia.org/wiki/Submission_wrestling'],
                ],
                'image_prompt' => "A cinematic, ultra-detailed hero poster in dramatic dark fantasy game-art style, 16:9 widescreen. Two no-gi grapplers — a muscular male and a fierce female — locked mid-scramble into a heel-hook and back-take, wearing tight rash-guards and fight shorts with subtle grappling emblems, sweat and motion-blur, glowing electric-green energy tracing fast scramble arcs. Behind them a translucent anaconda and panther spirit amid stormy skies. Background: a modern submission-grappling arena with mats and banners, sparks in the wind. Bold 3D title 'BJJ — NO-GI' with subtitle. Moody chiaroscuro, rim-lit silhouettes, electric-green-charcoal palette, hyper-detailed 4K game cover art.",
            ],
            /* ---- Wrestling — Freestyle ---- */
            [
                'slug' => 'wrestling-freestyle', 'name' => 'Wrestling (Freestyle)', 'name_ar' => 'المصارعة (الحرة)',
                'icon' => 'bi-person-arms-up', 'replaces' => 'wrestling',
                'en' => [
                    'intro' => '🤼 An Olympic wrestling style where the whole body — including the legs — can be used to attack and defend, prizing takedowns, exposure and control.',
                    'history' => 'Rooted in catch-as-catch-can wrestling, freestyle became an Olympic sport for men in 1904 and for women in 2004, governed by <strong>United World Wrestling</strong>.',
                    'focus' => 'Leg attacks, takedowns, turns and pins. Scores for taking an opponent down and exposing their back to the mat; explosive, high-tempo and physical.',
                    'benefits' => ['💪 Full-body explosive strength and conditioning.', '🤼 Elite takedown and control skills (great MMA base).', '❤️ Intense cardio and mental toughness.', '⚖️ Balance, leverage and body awareness.'],
                    'limitations' => ['👊 No striking.', '🤕 High physical demand and injury risk.', '📏 Sport rules restrict submissions/locks.'],
                    'rules' => ['⏱️ Two 3-minute periods.', '🎯 Points for takedowns, exposure, and control; pin (fall) ends the match.', '🦵 Leg attacks are legal (unlike Greco-Roman).', '🚫 No joint locks, no strikes, no illegal holds.'],
                ],
                'ar' => [
                    'intro' => '🤼 أسلوب مصارعة أولمبي يُسمح فيه باستخدام الجسم كله — بما فيه الساقان — للهجوم والدفاع، ويقدّر الإسقاط وكشف الظهر والتحكّم.',
                    'history' => 'متجذّرة في مصارعة «الإمساك بأي وسيلة»، صارت الحرة أولمبية للرجال عام 1904 وللنساء عام 2004، وتشرف عليها <strong>اتحاد المصارعة العالمي الموحّد</strong>.',
                    'focus' => 'هجمات الساقين والإسقاط والقلب والتثبيت. نقاط لإسقاط الخصم وكشف ظهره للبساط؛ انفجارية وسريعة وبدنية.',
                    'benefits' => ['💪 قوة انفجارية وإعداد للجسم كله.', '🤼 مهارات إسقاط وتحكّم رفيعة (أساس ممتاز للفنون المختلطة).', '❤️ لياقة قلبية عالية وصلابة ذهنية.', '⚖️ اتزان ورافعة ووعي جسدي.'],
                    'limitations' => ['👊 لا ضرب.', '🤕 متطلبات بدنية عالية وخطر إصابة.', '📏 تقيّد القوانين الأقفال والإخضاعات.'],
                    'rules' => ['⏱️ فترتان مدة كل منهما 3 دقائق.', '🎯 نقاط للإسقاط والكشف والتحكّم؛ والتثبيت (الفول) ينهي النزال.', '🦵 هجمات الساقين مسموحة (خلافاً للرومانية).', '🚫 لا أقفال مفاصل ولا ضرب ولا إمساكات ممنوعة.'],
                ],
                'links' => [
                    ['label' => 'United World Wrestling', 'url' => 'https://uww.org/'],
                    ['label' => 'Olympics — Wrestling', 'url' => 'https://www.olympics.com/en/sports/wrestling/'],
                    ['label' => 'Wikipedia — Freestyle wrestling', 'url' => 'https://en.wikipedia.org/wiki/Freestyle_wrestling'],
                ],
                'image_prompt' => "A cinematic, ultra-detailed hero poster in dramatic dark fantasy game-art style, 16:9 widescreen. Two freestyle wrestlers — a muscular male and a powerful female — frozen at the apex of a double-leg takedown lift, wearing singlets in red and blue with UWW-style emblems, sweat and chalk dust, glowing white power-arcs around the slam. Behind them a translucent bear and bull spirit amid stormy skies. Background: an Olympic wrestling arena with mat circle, flags and banners, dust and sparks in the wind. Bold 3D title 'WRESTLING — FREESTYLE' with subtitle. Moody chiaroscuro, rim-lit silhouettes, red-blue-bronze palette, hyper-detailed 4K game cover art.",
            ],
            /* ---- Wrestling — Greco-Roman ---- */
            [
                'slug' => 'wrestling-greco-roman', 'name' => 'Wrestling (Greco-Roman)', 'name_ar' => 'المصارعة (الرومانية)',
                'icon' => 'bi-person-arms-up', 'replaces' => 'wrestling',
                'en' => [
                    'intro' => '🤼 The classical Olympic wrestling style that forbids holds below the waist — all attacks use the upper body, favouring huge throws and upper-body strength.',
                    'history' => 'Named for classical antiquity, Greco-Roman was the first wrestling style in the modern Olympics (Athens 1896) and is governed by <strong>United World Wrestling</strong>.',
                    'focus' => 'Upper-body clinch, throws, lifts and arm-drags — no leg attacks and no using the legs to trip or hold. Rewards explosive throws and grip/upper-body dominance.',
                    'benefits' => ['💪 Tremendous upper-body and core power.', '🌀 Spectacular throwing technique.', '❤️ Intense conditioning and grit.', '⚖️ Balance and body control in the clinch.'],
                    'limitations' => ['🦵 No leg attacks — narrower than freestyle.', '👊 No striking.', '🤕 High-impact throws carry injury risk.'],
                    'rules' => ['⏱️ Two 3-minute periods.', '🚫 No holds below the waist; no leg use.', '🎯 Big points for throws with amplitude; pin ends the match.', '⚖️ Passivity leads to par-terre (ground) positions.'],
                ],
                'ar' => [
                    'intro' => '🤼 أسلوب المصارعة الأولمبي الكلاسيكي الذي يمنع الإمساك تحت الخصر — كل الهجمات بالجزء العلوي من الجسم، مع تفضيل الرميات الكبيرة وقوة الجذع العلوي.',
                    'history' => 'سُمّيت نسبةً إلى العصور الكلاسيكية، وكانت الرومانية أول أسلوب مصارعة في الأولمبياد الحديث (أثينا 1896)، ويشرف عليها <strong>اتحاد المصارعة العالمي الموحّد</strong>.',
                    'focus' => 'اشتباك علوي ورميات ورفع وسحب للذراع — لا هجمات ساقين ولا استخدام الرجلين للتعثير أو الإمساك. ويكافئ الرميات الانفجارية وهيمنة القبضة والجذع العلوي.',
                    'benefits' => ['💪 قوة هائلة للجذع العلوي والوسط.', '🌀 تقنية رمي مذهلة.', '❤️ إعداد مكثّف وصلابة.', '⚖️ اتزان وتحكّم بالجسم في الاشتباك.'],
                    'limitations' => ['🦵 لا هجمات ساقين — أضيق من الحرة.', '👊 لا ضرب.', '🤕 الرميات عالية التأثير تحمل خطر إصابة.'],
                    'rules' => ['⏱️ فترتان مدة كل منهما 3 دقائق.', '🚫 لا إمساك تحت الخصر ولا استخدام للساقين.', '🎯 نقاط كبيرة للرميات ذات السعة؛ والتثبيت ينهي النزال.', '⚖️ تؤدي السلبية إلى وضعيات أرضية (بار-تير).'],
                ],
                'links' => [
                    ['label' => 'United World Wrestling', 'url' => 'https://uww.org/'],
                    ['label' => 'Olympics — Wrestling', 'url' => 'https://www.olympics.com/en/sports/wrestling/'],
                    ['label' => 'Wikipedia — Greco-Roman wrestling', 'url' => 'https://en.wikipedia.org/wiki/Greco-Roman_wrestling'],
                ],
                'image_prompt' => "A cinematic, ultra-detailed hero poster in dramatic dark fantasy game-art style, 16:9 widescreen. Two Greco-Roman wrestlers — a massive male and a powerful female — frozen at the peak of a suplex arch-throw using only upper-body grips, wearing red and blue singlets with UWW emblems, glowing bronze classical-laurel energy around the throw. Behind them a translucent Greek titan and eagle amid stormy skies. Background: an ancient-Greek colonnade fused with an Olympic arena, banners and dust in the wind. Bold 3D title 'WRESTLING — GRECO-ROMAN' with subtitle. Moody chiaroscuro, rim-lit silhouettes, bronze-red-blue palette, hyper-detailed 4K game cover art.",
            ],
            /* ---- MMA ---- */
            [
                'slug' => 'mixed-martial-arts', 'name' => 'Mixed Martial Arts (MMA)', 'name_ar' => 'الفنون القتالية المختلطة (MMA)',
                'icon' => 'bi-person-arms-up',
                'en' => [
                    'intro' => '🥊 The ultimate full-contact combat sport combining striking and grappling from many disciplines — boxing, Muay Thai, wrestling and BJJ — in the cage.',
                    'history' => 'Modern MMA was crystallised by the <strong>UFC</strong> (1993), which pitted styles against each other; the <strong>Unified Rules of MMA</strong> (2000s) turned it into a regulated global sport.',
                    'focus' => 'Blending stand-up striking, clinch, takedowns and ground submissions. Rewards well-rounded fighters who can win everywhere the fight goes.',
                    'benefits' => ['🔥 Complete, realistic combat skill set.', '❤️ Elite all-round conditioning.', '🧠 Adaptability and fight IQ.', '💪 Full-body strength and resilience.'],
                    'limitations' => ['🤕 High injury risk; demands strong supervision.', '⏳ Requires cross-training several arts — long road to competence.', '🚫 Not for casual, low-contact learners without a proper gym.'],
                    'rules' => ['🥊 Fought in a cage over rounds (3×5 min, or 5×5 for title bouts).', '🎯 Strikes, takedowns, clinch and submissions all legal.', '🏆 Win by KO/TKO, submission, or judges\' decision.', '🚫 No eye-pokes, groin strikes, biting, small-joint manipulation or strikes to the back of the head.'],
                ],
                'ar' => [
                    'intro' => '🥊 الرياضة القتالية الشاملة بالتلامس الكامل التي تجمع الضرب والمصارعة من فنون عدّة — الملاكمة والمواي تاي والمصارعة والجوجيتسو — داخل القفص.',
                    'history' => 'تبلورت الفنون المختلطة الحديثة عبر <strong>UFC</strong> (1993) التي واجهت الأساليب ببعضها؛ ثم حوّلتها <strong>القوانين الموحّدة للفنون المختلطة</strong> (منتصف العقد الأول من الألفية) إلى رياضة عالمية منظّمة.',
                    'focus' => 'مزج الضرب واقفاً والاشتباك والإسقاط والإخضاع أرضاً. ويكافئ المقاتل الشامل القادر على الفوز أينما ذهب النزال.',
                    'benefits' => ['🔥 منظومة مهارات قتالية متكاملة وواقعية.', '❤️ إعداد شامل رفيع.', '🧠 قدرة على التكيّف وذكاء قتالي.', '💪 قوة للجسم كله وصلابة.'],
                    'limitations' => ['🤕 خطر إصابة عالٍ؛ ويتطلب إشرافاً قوياً.', '⏳ يتطلب تدريباً متقاطعاً لعدة فنون — طريق طويل للكفاءة.', '🚫 غير مناسب للمتعلّم العابر بلا صالة مناسبة.'],
                    'rules' => ['🥊 يُخاض في قفص عبر جولات (3×5 دقائق، أو 5×5 لنزالات اللقب).', '🎯 الضرب والإسقاط والاشتباك والإخضاع كلها مسموحة.', '🏆 الفوز بالقاضية أو الإخضاع أو قرار الحكام.', '🚫 يُمنع نخز العين وضرب المغبن والعضّ ولوي المفاصل الصغيرة وضرب مؤخرة الرأس.'],
                ],
                'links' => [
                    ['label' => 'UFC (official)', 'url' => 'https://www.ufc.com/'],
                    ['label' => 'Wikipedia — Mixed martial arts', 'url' => 'https://en.wikipedia.org/wiki/Mixed_martial_arts'],
                    ['label' => 'Wikipedia — Unified Rules of MMA', 'url' => 'https://en.wikipedia.org/wiki/Unified_Rules_of_Mixed_Martial_Arts'],
                ],
                'image_prompt' => "A cinematic, ultra-detailed hero poster in dramatic dark fantasy game-art style, 16:9 widescreen. Two MMA fighters — a muscular male and a fierce female — mid-action, one landing a flying knee while the other shoots a takedown, wearing MMA gloves and fight shorts with subtle emblems, sweat and cage-light flares, glowing red-orange energy shockwaves. Behind them a translucent hydra and phoenix spirit amid a stormy neon sky. Background: a packed octagon cage arena with spotlights and banners, sparks in the wind. Bold 3D title 'MIXED MARTIAL ARTS' with subtitle. Moody chiaroscuro, rim-lit silhouettes, crimson-orange-black palette, hyper-detailed 4K game cover art.",
            ],
            /* ---- Kickboxing ---- */
            [
                'slug' => 'kickboxing', 'name' => 'Kickboxing', 'name_ar' => 'الكيك بوكسينغ',
                'icon' => 'bi-person-arms-up',
                'en' => [
                    'intro' => '🥊 A dynamic stand-up striking sport blending boxing punches with kicks, fought under several rule-sets (K-1, full-contact/American, and others).',
                    'history' => 'Kickboxing emerged in the 1960s–70s as Japanese and American promoters fused karate kicks with boxing; the K-1 promotion (1993) popularised a fast, spectacular ruleset worldwide.',
                    'focus' => 'Combining crisp boxing with powerful kicks (and, in K-1, knees). Rewards footwork, combinations, timing and conditioning at range and in the pocket.',
                    'benefits' => ['❤️ Superb full-body cardio and fat-burning.', '🦵 Powerful, coordinated punch-kick combinations.', '🧠 Stress relief, focus and confidence.', '🛡️ Effective stand-up self-defence.'],
                    'limitations' => ['🤼 No grappling or ground work.', '🤕 Contact sparring carries injury risk.', '📏 Rules vary by promotion (elbows/knees allowed or not).'],
                    'rules' => ['🥊 Gloved bouts over timed rounds; ring-based.', '🎯 Legal weapons: punches and kicks (knees in K-1); low kicks allowed in most rulesets.', '🏆 Win by KO/TKO or judges\' scorecards.', '🚫 No strikes to the back/groin, no grappling beyond a brief clinch.'],
                ],
                'ar' => [
                    'intro' => '🥊 رياضة ضرب واقفة ديناميكية تمزج لكمات الملاكمة بالركلات، وتُخاض بعدة قوانين (K-1، والتلامس الكامل/الأمريكي، وغيرها).',
                    'history' => 'ظهر الكيك بوكسينغ في الستينيات والسبعينيات حين مزج المروّجون اليابانيون والأمريكيون ركلات الكاراتيه بالملاكمة؛ وشعّبت بطولة K-1 (1993) قانوناً سريعاً مبهراً حول العالم.',
                    'focus' => 'الجمع بين ملاكمة دقيقة وركلات قوية (وركب في K-1). ويكافئ حركة القدمين والتوليفات والتوقيت والإعداد من بعيد وفي المدى القريب.',
                    'benefits' => ['❤️ لياقة قلبية ممتازة للجسم كله وحرق للدهون.', '🦵 توليفات لكم وركل قوية ومنسّقة.', '🧠 تفريغ للتوتر وتركيز وثقة.', '🛡️ دفاع عن النفس فعّال واقفاً.'],
                    'limitations' => ['🤼 لا مصارعة ولا عمل أرضي.', '🤕 القتال بالتلامس يحمل خطر إصابة.', '📏 تختلف القوانين حسب البطولة (سماح الأكواع/الركب من عدمه).'],
                    'rules' => ['🥊 نزالات بالقفازات عبر جولات مؤقتة داخل الحلبة.', '🎯 الأسلحة المسموحة: اللكمات والركلات (والركب في K-1)؛ والركل المنخفض مسموح في معظم القوانين.', '🏆 الفوز بالقاضية أو ببطاقات الحكام.', '🚫 لا ضرب للظهر/المغبن، ولا مصارعة إلا اشتباكاً قصيراً.'],
                ],
                'links' => [
                    ['label' => 'WAKO (World Association of Kickboxing Organizations)', 'url' => 'https://wako.sport/'],
                    ['label' => 'Wikipedia — Kickboxing', 'url' => 'https://en.wikipedia.org/wiki/Kickboxing'],
                    ['label' => 'Wikipedia — K-1', 'url' => 'https://en.wikipedia.org/wiki/K-1'],
                ],
                'image_prompt' => "A cinematic, ultra-detailed hero poster in dramatic dark fantasy game-art style, 16:9 widescreen. Two kickboxers — a muscular male and a fierce female — mid-action landing a high roundhouse kick and a counter cross, wearing gloves, shin-guards and satin shorts with subtle emblems, sweat spraying, glowing electric-orange impact arcs. Behind them a translucent falcon and tiger spirit amid a stormy arena sky. Background: a neon-lit ring arena with banners and lights, sparks in the wind. Bold 3D title 'KICKBOXING' with subtitle. Moody chiaroscuro, rim-lit silhouettes, orange-black-silver palette, hyper-detailed 4K game cover art.",
            ],
            /* ---- Kung Fu — Wing Chun ---- */
            [
                'slug' => 'kung-fu-wing-chun', 'name' => 'Kung Fu (Wing Chun)', 'name_ar' => 'الكونغ فو (وينغ تشون)',
                'icon' => 'bi-person-arms-up', 'replaces' => 'kung-fu',
                'en' => [
                    'intro' => '🐉 A close-range southern Chinese kung fu built on centreline theory, rapid chain-punches and sensitivity drills — famously taught to Bruce Lee.',
                    'history' => 'Legend credits the nun Ng Mui and the woman Yim Wing-Chun; the style was popularised in the 20th century by <strong>Ip Man</strong> in Hong Kong, and worldwide through his student Bruce Lee.',
                    'focus' => 'Efficient close-range combat: protecting and attacking the centreline, simultaneous defence-and-attack, and <em>chi sao</em> ("sticky hands") sensitivity training.',
                    'benefits' => ['⚡ Fast reflexes, sensitivity and close-range reactions.', '🧠 Economy of motion and structural efficiency.', '🛡️ Practical self-defence in tight spaces.', '🧘 Relaxation, focus and body structure.'],
                    'limitations' => ['📏 Specialised for close range; less developed at long range/kicking.', '🤼 Little ground fighting.', '🌐 Many lineages with differing methods.'],
                    'rules' => ['🥋 Primarily a self-defence art — training via forms, chi sao and drills.', '👐 Emphasises structure and centreline control over sport scoring.', '🤝 Sparring is typically light/controlled in most schools.'],
                ],
                'ar' => [
                    'intro' => '🐉 كونغ فو صيني جنوبي قريب المدى مبنيّ على نظرية الخط المركزي واللكمات المتسلسلة السريعة وتمارين الإحساس — واشتُهر بتدريسه لبروس لي.',
                    'history' => 'تنسب الأسطورة الأسلوب إلى الراهبة نغ موي والمرأة يِم وينغ-تشون؛ وشُعّب في القرن العشرين على يد <strong>إيب مان</strong> في هونغ كونغ، وعالمياً عبر تلميذه بروس لي.',
                    'focus' => 'قتال قريب فعّال: حماية الخط المركزي ومهاجمته، والدفاع والهجوم في آنٍ واحد، وتدريب الإحساس <em>تشي ساو</em> («الأيدي اللاصقة»).',
                    'benefits' => ['⚡ ردود فعل سريعة وإحساس وتفاعل قريب المدى.', '🧠 اقتصاد في الحركة وكفاءة بنيوية.', '🛡️ دفاع عن النفس عملي في الأماكن الضيّقة.', '🧘 استرخاء وتركيز وبنية جسدية.'],
                    'limitations' => ['📏 متخصّص في المدى القريب؛ وأقل تطوّراً في المدى البعيد/الركل.', '🤼 قتال أرضي محدود.', '🌐 سلاسل تعليمية عديدة بأساليب مختلفة.'],
                    'rules' => ['🥋 فن دفاع عن النفس أساساً — التدريب عبر الأنماط والتشي ساو والتمارين.', '👐 يركّز على البنية والتحكّم بالخط المركزي بدل التسجيل الرياضي.', '🤝 القتال غالباً خفيف/مُتحكَّم فيه في معظم المدارس.'],
                ],
                'links' => [
                    ['label' => 'Wikipedia — Wing Chun', 'url' => 'https://en.wikipedia.org/wiki/Wing_Chun'],
                    ['label' => 'Wikipedia — Ip Man', 'url' => 'https://en.wikipedia.org/wiki/Ip_Man'],
                    ['label' => 'Wikipedia — Chinese martial arts', 'url' => 'https://en.wikipedia.org/wiki/Chinese_martial_arts'],
                ],
                'image_prompt' => "A cinematic, ultra-detailed hero poster in dramatic dark fantasy game-art style, 16:9 widescreen. Two Wing Chun practitioners — a lean male and a swift female — mid-action in a blur of centreline chain-punches and sticky-hands trapping, wearing black frog-button kung fu tunics, glowing jade-green chi energy spiralling down the centreline. Behind them a translucent Chinese dragon coiled amid stormy skies. Background: a Hong Kong rooftop with neon signs and a wooden dummy silhouette, lanterns and embers in the wind. Bold 3D calligraphic title 'KUNG FU — WING CHUN' with subtitle. Moody chiaroscuro, rim-lit silhouettes, jade-crimson-black palette, hyper-detailed 4K game cover art.",
            ],
            /* ---- Kung Fu — Shaolin ---- */
            [
                'slug' => 'kung-fu-shaolin', 'name' => 'Kung Fu (Shaolin)', 'name_ar' => 'الكونغ فو (شاولين)',
                'icon' => 'bi-person-arms-up', 'replaces' => 'kung-fu',
                'en' => [
                    'intro' => '🐉 The legendary external kung fu of the Shaolin Temple — athletic forms, powerful kicks, acrobatics and deep-rooted Chan Buddhist discipline.',
                    'history' => 'Traced to the <strong>Shaolin Monastery</strong> in Henan, China (from ~5th–6th century), Shaolin kung fu developed over centuries into a vast system of forms, weapons and conditioning intertwined with Chan Buddhism.',
                    'focus' => 'Athletic long-range striking, dynamic kicks, stances, acrobatic forms (taolu) and rigorous body conditioning — power, flexibility and spirit.',
                    'benefits' => ['🤸 Exceptional flexibility, athleticism and coordination.', '💪 Full-body strength and endurance.', '🧘 Discipline, focus and meditative calm.', '🎭 Rich cultural forms and weapons training.'],
                    'limitations' => ['🥋 Traditional/performance focus can differ from live fighting.', '🤼 Limited ground grappling.', '⏳ Deep system; mastery takes years.'],
                    'rules' => ['🥋 Trained via taolu (forms), conditioning and applications.', '🏆 Modern Wushu competition scores forms on difficulty and execution.', '🧘 Philosophy and etiquette are integral.'],
                ],
                'ar' => [
                    'intro' => '🐉 الكونغ فو الخارجي الأسطوري لمعبد شاولين — أنماط رياضية وركلات قوية وحركات بهلوانية وانضباط بوذي تشان عريق.',
                    'history' => 'يعود إلى <strong>معبد شاولين</strong> في خنان بالصين (منذ القرنين الخامس والسادس تقريباً)، وتطوّر كونغ فو شاولين عبر قرون إلى نظام واسع من الأنماط والأسلحة والإعداد ممتزجاً بالبوذية التشان.',
                    'focus' => 'ضرب رياضي بعيد المدى وركلات ديناميكية ووقفات وأنماط بهلوانية (تاولو) وإعداد بدني صارم — قوة ومرونة وروح.',
                    'benefits' => ['🤸 مرونة ورياضية وتناسق استثنائية.', '💪 قوة وتحمّل للجسم كله.', '🧘 انضباط وتركيز وهدوء تأمّلي.', '🎭 أنماط ثقافية غنية وتدريب على الأسلحة.'],
                    'limitations' => ['🥋 قد يختلف التركيز التقليدي/الاستعراضي عن القتال الحي.', '🤼 مصارعة أرضية محدودة.', '⏳ نظام عميق؛ ويحتاج الإتقان سنوات.'],
                    'rules' => ['🥋 يُدرّب عبر التاولو (الأنماط) والإعداد والتطبيقات.', '🏆 تُقيَّم منافسات الووشو الحديثة الأنماط حسب الصعوبة والأداء.', '🧘 الفلسفة والآداب جزء أصيل.'],
                ],
                'links' => [
                    ['label' => 'Wikipedia — Shaolin Kung Fu', 'url' => 'https://en.wikipedia.org/wiki/Shaolin_Kung_Fu'],
                    ['label' => 'Wikipedia — Shaolin Monastery', 'url' => 'https://en.wikipedia.org/wiki/Shaolin_Monastery'],
                    ['label' => 'International Wushu Federation', 'url' => 'https://www.iwuf.org/'],
                ],
                'image_prompt' => "A cinematic, ultra-detailed hero poster in dramatic dark fantasy game-art style, 16:9 widescreen. Two Shaolin monks — a powerful male and an agile female — mid-action in a soaring tornado kick and a low crane stance, wearing orange-and-grey monk robes with prayer beads, shaved heads, glowing golden chi and lotus energy swirling. Behind them a translucent golden dragon and phoenix amid misty mountains and storm. Background: the Shaolin Temple pagoda forest on a Henan mountainside, incense smoke and autumn leaves in the wind. Bold 3D calligraphic title 'KUNG FU — SHAOLIN' with subtitle. Moody chiaroscuro, rim-lit silhouettes, saffron-gold-jade palette, hyper-detailed 4K game cover art.",
            ],
            /* ---- Kung Fu — Tai Chi ---- */
            [
                'slug' => 'kung-fu-tai-chi', 'name' => 'Tai Chi (Taijiquan)', 'name_ar' => 'تاي تشي (تايجي تشوان)',
                'icon' => 'bi-person-arms-up', 'replaces' => 'kung-fu',
                'en' => [
                    'intro' => '☯️ An internal Chinese martial art of slow, flowing movement — a moving meditation that also trains soft-power self-defence via yielding and redirection.',
                    'history' => 'Rooted in Daoist philosophy and credited in legend to Zhang Sanfeng, <strong>Taijiquan</strong> crystallised through family styles (Chen, Yang, Wu, Sun) and is now practised worldwide for health and martial skill.',
                    'focus' => 'Slow, continuous, relaxed forms training balance, alignment and breath; martial application uses <em>push hands</em> to yield to and redirect force rather than clash.',
                    'benefits' => ['🧘 Reduces stress; improves balance and joint health (well studied in older adults).', '🌬️ Better breathing, posture and body awareness.', '❤️ Gentle cardiovascular and mobility benefits.', '☯️ Calm, focus and internal-energy cultivation.'],
                    'limitations' => ['🥊 Slow to translate into practical fighting; needs push-hands/sparring for combat.', '⏳ Subtle internal skills take long to develop.', '💪 Low-impact — not primarily strength/cardio conditioning.'],
                    'rules' => ['🥋 Practised as forms and push-hands; no striking in typical classes.', '🏆 Modern competition judges forms and push-hands.', '🧘 Emphasis on relaxation, alignment and continuity.'],
                ],
                'ar' => [
                    'intro' => '☯️ فن قتالي صيني داخلي بحركة بطيئة انسيابية — تأمّل متحرّك يدرّب أيضاً الدفاع بالقوة اللينة عبر الليونة وإعادة التوجيه.',
                    'history' => 'متجذّر في الفلسفة الطاوية وتنسبه الأسطورة إلى تشانغ سانفنغ، وتبلور <strong>تايجي تشوان</strong> عبر أساليب عائلية (تشِن، يانغ، وو، سون)، ويُمارَس اليوم عالمياً للصحة والمهارة القتالية.',
                    'focus' => 'أنماط بطيئة متّصلة مسترخية تدرّب الاتزان والاصطفاف والتنفّس؛ ويستخدم التطبيق القتالي <em>دفع الأيدي</em> لليونة وإعادة توجيه القوة بدل صدامها.',
                    'benefits' => ['🧘 يقلّل التوتر ويحسّن الاتزان وصحة المفاصل (مدروس جيداً لدى كبار السن).', '🌬️ تنفّس ووضعية ووعي جسدي أفضل.', '❤️ فوائد قلبية لطيفة وحركية.', '☯️ هدوء وتركيز وتنمية للطاقة الداخلية.'],
                    'limitations' => ['🥊 بطيء التحوّل إلى قتال عملي؛ ويحتاج دفع الأيدي/القتال للتطبيق.', '⏳ المهارات الداخلية الدقيقة تحتاج وقتاً طويلاً.', '💪 منخفض التأثير — ليس إعداداً للقوة/اللياقة أساساً.'],
                    'rules' => ['🥋 يُمارَس أنماطاً ودفع أيدٍ؛ ولا ضرب في الحصص المعتادة.', '🏆 تُقيّم المنافسات الحديثة الأنماط ودفع الأيدي.', '🧘 التركيز على الاسترخاء والاصطفاف والاتصال.'],
                ],
                'links' => [
                    ['label' => 'Wikipedia — Tai chi', 'url' => 'https://en.wikipedia.org/wiki/Tai_chi'],
                    ['label' => 'Harvard Health — Tai chi', 'url' => 'https://www.health.harvard.edu/topics/tai-chi'],
                    ['label' => 'International Wushu Federation', 'url' => 'https://www.iwuf.org/'],
                ],
                'image_prompt' => "A cinematic, ultra-detailed hero poster in dramatic dark fantasy game-art style, 16:9 widescreen. Two Tai Chi practitioners — a serene older male and a graceful female — mid-flow in a slow single-whip and cloud-hands posture, wearing flowing silk tai-chi uniforms in white and slate, glowing yin-yang swirl of soft blue-and-white chi energy encircling them. Behind them a translucent crane and turtle-dragon amid misty mountains at dawn. Background: a tranquil Chinese mountain lake and pavilion, mist and petals drifting in gentle wind. Bold 3D calligraphic title 'TAI CHI — TAIJIQUAN' with subtitle. Moody soft chiaroscuro, rim-lit silhouettes, slate-blue-white palette, hyper-detailed 4K game cover art.",
            ],
            /* ---- Aikido ---- */
            [
                'slug' => 'aikido', 'name' => 'Aikido', 'name_ar' => 'الأيكيدو',
                'icon' => 'bi-person-arms-up',
                'en' => [
                    'intro' => '🥋 "The way of harmonising energy" — a Japanese art that neutralises attacks with circular throws and joint locks, blending with force rather than opposing it.',
                    'history' => 'Founded by <strong>Morihei Ueshiba</strong> ("O-Sensei") in the early 20th century from Daitō-ryū aiki-jūjutsu, Aikido fuses effective technique with a philosophy of peace and non-destruction.',
                    'focus' => 'Redirecting an attacker\'s momentum into throws (nage-waza) and joint locks (kansetsu-waza), using entering and turning (irimi/tenkan) and constant blending with the attack.',
                    'benefits' => ['🌀 Excellent balance, posture and body awareness.', '🧘 Calm under pressure and conflict de-escalation mindset.', '🤝 Safe break-falling and partner practice.', '🛡️ Control-and-restrain self-defence.'],
                    'limitations' => ['🥊 Little live sparring in many schools; effectiveness debated.', '🤼 No striking sport or ground grappling focus.', '⏳ Subtle timing takes long to master.'],
                    'rules' => ['🥋 Practised cooperatively via paired techniques and forms; most styles have no competition.', '🤝 Emphasis on control without injuring the attacker.', '🏅 (Tomiki/Shodokan style does hold limited competition.)'],
                ],
                'ar' => [
                    'intro' => '🥋 «طريق مواءمة الطاقة» — فن ياباني يُبطل الهجمات برميات دائرية وأقفال مفاصل، منسجماً مع القوة بدل معارضتها.',
                    'history' => 'أسّسه <strong>موريهي أويشيبا</strong> («أو-سينسي») مطلع القرن العشرين من دايتو-ريو أيكي-جوجيتسو، ويمزج الأيكيدو التقنية الفعّالة بفلسفة السلام وعدم التدمير.',
                    'focus' => 'إعادة توجيه زخم المهاجم إلى رميات (ناغيه-وازا) وأقفال مفاصل (كانسيتسو-وازا)، باستخدام الدخول والدوران (إيريمي/تينكان) والانسجام الدائم مع الهجوم.',
                    'benefits' => ['🌀 اتزان ووضعية ووعي جسدي ممتاز.', '🧘 هدوء تحت الضغط وعقلية تهدئة النزاع.', '🤝 سقوط آمن وتدريب مع شريك.', '🛡️ دفاع عن النفس قائم على التحكّم والتقييد.'],
                    'limitations' => ['🥊 قتال حي قليل في كثير من المدارس؛ وفعاليته محلّ نقاش.', '🤼 لا تركيز على رياضة الضرب أو المصارعة الأرضية.', '⏳ التوقيت الدقيق يحتاج وقتاً طويلاً لإتقانه.'],
                    'rules' => ['🥋 يُمارَس تعاونياً عبر تقنيات ثنائية وأنماط؛ ومعظم الأساليب بلا منافسة.', '🤝 التركيز على التحكّم دون إيذاء المهاجم.', '🏅 (أسلوب توميكي/شودوكان يقيم منافسة محدودة.)'],
                ],
                'links' => [
                    ['label' => 'Aikikai Foundation', 'url' => 'https://www.aikikai.or.jp/eng/'],
                    ['label' => 'Wikipedia — Aikido', 'url' => 'https://en.wikipedia.org/wiki/Aikido'],
                    ['label' => 'Wikipedia — Morihei Ueshiba', 'url' => 'https://en.wikipedia.org/wiki/Morihei_Ueshiba'],
                ],
                'image_prompt' => "A cinematic, ultra-detailed hero poster in dramatic dark fantasy game-art style, 16:9 widescreen. Two Aikido practitioners — a calm male and a graceful female — mid-action in a spiralling kokyu-nage throw, the attacker arcing through the air, wearing white keikogi with black hakama trousers, glowing soft blue spiral wind-energy tracing the circular throw. Behind them a translucent white crane and swirling dragon amid serene stormy skies. Background: a traditional Japanese dojo with shoji screens and a shrine, petals swirling in the wind. Bold 3D brush title 'AIKIDO' with subtitle. Moody chiaroscuro, rim-lit silhouettes, indigo-white-slate palette, hyper-detailed 4K game cover art.",
            ],
            /* ---- Capoeira ---- */
            [
                'slug' => 'capoeira', 'name' => 'Capoeira', 'name_ar' => 'الكابويرا',
                'icon' => 'bi-person-arms-up',
                'en' => [
                    'intro' => '🎶 An Afro-Brazilian art blending martial technique, acrobatics, dance and music — fluid, playful and unmistakable, played in a circle (roda) to live rhythm.',
                    'history' => 'Developed by enslaved Africans in Brazil from the 16th century, disguised as dance to evade prohibition, Capoeira was legalised and formalised in the 1930s (notably by Mestre Bimba and Mestre Pastinha) and is now UNESCO Intangible Cultural Heritage.',
                    'focus' => 'Continuous, swinging movement (ginga) linking sweeps, spinning kicks, dodges and acrobatic escapes, all timed to berimbau-led music within the roda.',
                    'benefits' => ['🤸 Outstanding agility, flexibility and body control.', '❤️ Great cardio and full-body coordination.', '🎵 Musicality, rhythm and cultural connection.', '🤝 Community, expression and confidence.'],
                    'limitations' => ['🥊 Playful/ritual emphasis differs from direct combat sport.', '🤼 Little grappling or ground control.', '⏳ Acrobatic elements take time and mobility to build.'],
                    'rules' => ['🎶 "Played" in the roda between two people to live music, not scored like a bout.', '🤸 Emphasises flow, dodging and controlled (often non-contact) kicks.', '🥁 Musicians and singers set the game\'s speed and mood.'],
                ],
                'ar' => [
                    'intro' => '🎶 فن أفرو-برازيلي يمزج التقنية القتالية بالبهلوانات والرقص والموسيقى — انسيابي ومرح ومميّز، يُلعب في حلقة (رودا) على إيقاع حيّ.',
                    'history' => 'طوّره الأفارقة المستعبَدون في البرازيل منذ القرن السادس عشر متخفّياً كرقص للتحايل على الحظر، وقُنّن في الثلاثينيات (خصوصاً على يد مِستري بيمبا ومِستري باستينيا)، وهو اليوم تراث ثقافي غير مادي لليونسكو.',
                    'focus' => 'حركة متمايلة متّصلة (جينغا) تربط الكسحات والركلات الدورانية والمراوغات والهروب البهلواني، كلها على إيقاع تقوده آلة البيريمباو داخل الرودا.',
                    'benefits' => ['🤸 رشاقة ومرونة وتحكّم بالجسم استثنائية.', '❤️ لياقة قلبية جيدة وتناسق للجسم كله.', '🎵 موسيقية وإيقاع وصلة ثقافية.', '🤝 مجتمع وتعبير وثقة.'],
                    'limitations' => ['🥊 التركيز المرح/الطقسي يختلف عن الرياضة القتالية المباشرة.', '🤼 مصارعة وتحكّم أرضي قليل.', '⏳ العناصر البهلوانية تحتاج وقتاً ومرونة لبنائها.'],
                    'rules' => ['🎶 «يُلعب» في الرودا بين شخصين على موسيقى حيّة، لا يُسجَّل كنزال.', '🤸 يركّز على الانسياب والمراوغة والركلات المُتحكَّم فيها (غالباً بلا تلامس).', '🥁 يحدّد العازفون والمغنّون سرعة اللعبة ومزاجها.'],
                ],
                'links' => [
                    ['label' => 'UNESCO — Capoeira', 'url' => 'https://ich.unesco.org/en/RL/capoeira-circle-00892'],
                    ['label' => 'Wikipedia — Capoeira', 'url' => 'https://en.wikipedia.org/wiki/Capoeira'],
                ],
                'image_prompt' => "A cinematic, ultra-detailed hero poster in dramatic dark fantasy game-art style, 16:9 widescreen. Two capoeiristas — a muscular male and an agile female — mid-action in an inverted spinning kick (meia-lua) and a low ginga dodge, wearing white abadá trousers, bare-chested/white tops with cordão belts, glowing golden-green rhythmic energy ribbons swirling. Behind them a translucent Orixá spirit and jaguar amid a warm stormy sky. Background: a Salvador da Bahia street with colourful colonial facades, a berimbau silhouette and drummers, sparks and leaves in the wind. Bold 3D title 'CAPOEIRA' with subtitle. Moody chiaroscuro, rim-lit silhouettes, gold-green-white palette, hyper-detailed 4K game cover art.",
            ],
            /* ---- Fencing ---- */
            [
                'slug' => 'fencing', 'name' => 'Fencing', 'name_ar' => 'المبارزة',
                'icon' => 'bi-person-arms-up',
                'en' => [
                    'intro' => '🤺 "Physical chess" — the Olympic sword sport of speed, timing and tactics, fought with foil, épée or sabre on a narrow strip (piste).',
                    'history' => 'Descended from European duelling and swordsmanship schools, modern sport fencing formed in the 19th century and has appeared at every modern Olympics since 1896, governed by the <strong>FIE</strong>.',
                    'focus' => 'Explosive footwork, blade-work and split-second decision-making. Three weapons differ in target area and rules (right-of-way in foil/sabre; hit-anywhere in épée).',
                    'benefits' => ['⚡ Lightning reflexes, footwork and precision.', '🧠 Tactical thinking and rapid decision-making.', '❤️ Lower-limb power and cardiovascular fitness.', '🎯 Focus, discipline and composure.'],
                    'limitations' => ['🤺 Highly specialised sport skill; not general self-defence.', '💰 Requires specific equipment and a piste.', '🦵 Asymmetric loading needs balanced conditioning.'],
                    'rules' => ['⚔️ Three weapons: foil, épée, sabre — each with its own target and rules.', '🎯 Electronic scoring registers valid touches on a 14m piste.', '🏆 Bouts to 5 (pools) or 15 (direct elimination) touches.', '🚦 Foil/sabre use "right-of-way"; épée awards the first hit (double hits count for both).'],
                ],
                'ar' => [
                    'intro' => '🤺 «شطرنج بدني» — رياضة السيف الأولمبية القائمة على السرعة والتوقيت والتكتيك، تُخاض بسلاح الفويل أو الإيبيه أو السيبر على شريط ضيّق (البيست).',
                    'history' => 'منحدرة من المبارزة الأوروبية ومدارس السيف، تشكّلت المبارزة الرياضية الحديثة في القرن التاسع عشر وظهرت في كل أولمبياد حديث منذ 1896، ويشرف عليها <strong>الاتحاد الدولي FIE</strong>.',
                    'focus' => 'حركة قدمين انفجارية وعمل بالنصل واتخاذ قرار في أجزاء الثانية. تختلف الأسلحة الثلاثة في منطقة الهدف والقوانين (أولوية الهجوم في الفويل/السيبر؛ والإصابة في أي مكان في الإيبيه).',
                    'benefits' => ['⚡ ردود فعل وحركة قدمين ودقة فائقة.', '🧠 تفكير تكتيكي واتخاذ قرار سريع.', '❤️ قوة للأطراف السفلى ولياقة قلبية.', '🎯 تركيز وانضباط ورباطة جأش.'],
                    'limitations' => ['🤺 مهارة رياضية عالية التخصّص؛ وليست دفاعاً عاماً عن النفس.', '💰 تتطلب معدّات خاصة وشريطاً.', '🦵 التحميل غير المتماثل يحتاج إعداداً متوازناً.'],
                    'rules' => ['⚔️ ثلاثة أسلحة: الفويل والإيبيه والسيبر — لكل منها هدفه وقوانينه.', '🎯 يسجّل الاحتساب الإلكتروني اللمسات الصحيحة على شريط طوله 14 م.', '🏆 نزالات حتى 5 (المجموعات) أو 15 (الإقصاء المباشر) لمسة.', '🚦 الفويل/السيبر بأولوية الهجوم؛ والإيبيه يمنح أول إصابة (والإصابة المزدوجة تُحسب للطرفين).'],
                ],
                'links' => [
                    ['label' => 'International Fencing Federation (FIE)', 'url' => 'https://fie.org/'],
                    ['label' => 'Olympics — Fencing', 'url' => 'https://www.olympics.com/en/sports/fencing/'],
                    ['label' => 'Wikipedia — Fencing', 'url' => 'https://en.wikipedia.org/wiki/Fencing'],
                ],
                'image_prompt' => "A cinematic, ultra-detailed hero poster in dramatic dark fantasy game-art style, 16:9 widescreen. Two fencers — a lean male and a swift female — frozen mid-lunge in a perfect attack-and-parry, wearing white fencing whites, wire-mesh masks pushed up or on, holding épée/sabre blades, glowing silver-blue spark-trails streaking off the clashing steel. Behind them a translucent silver phoenix and winged-lion spirit amid a stormy sky. Background: an elegant European hall fused with an Olympic piste, banners and light-beams, sparks in the wind. Bold 3D title 'FENCING' with subtitle. Moody chiaroscuro, rim-lit silhouettes, silver-white-sapphire palette, hyper-detailed 4K game cover art.",
            ],
        ];
    }

    private function fitness(): array
    {
        return [
            /* ---- Fitness Training ---- */
            [
                'slug' => 'fitness-training', 'name' => 'Fitness Training', 'name_ar' => 'اللياقة البدنية',
                'icon' => 'bi-heart-pulse',
                'en' => [
                    'intro' => '💪 General strength-and-conditioning training to build health, strength, endurance and body composition using weights, machines and bodyweight.',
                    'history' => 'Modern fitness training grew from early-20th-century physical culture into today\'s evidence-based blend of resistance training, cardio and mobility, guided by sports-science bodies like the <strong>ACSM</strong>.',
                    'focus' => 'Progressive overload across strength, cardiovascular fitness, mobility and body composition — programmed to personal goals (fat loss, muscle, health).',
                    'benefits' => ['❤️ Improves heart health, strength and metabolism.', '🦴 Builds muscle and bone density.', '🧠 Reduces stress and boosts mood and sleep.', '🎯 Adaptable to any age, level or goal.'],
                    'limitations' => ['🥋 No self-defence or sport-specific skill.', '📋 Needs sensible programming to avoid overuse injury.', '🔁 Motivation/consistency is the main challenge.'],
                    'rules' => ['🏋️ Not a competitive sport — success is measured by personal progress.', '📈 Built on progressive overload, recovery and consistency.', '🩺 Warm-up, correct form and rest days are essential.'],
                ],
                'ar' => [
                    'intro' => '💪 تدريب عام للقوة واللياقة لبناء الصحة والقوة والتحمّل وتكوين الجسم باستخدام الأوزان والأجهزة ووزن الجسم.',
                    'history' => 'نما تدريب اللياقة الحديث من ثقافة الجسد مطلع القرن العشرين إلى مزيج اليوم القائم على الأدلة من تدريب المقاومة والكارديو والحركية، بإرشاد جهات علوم الرياضة مثل <strong>ACSM</strong>.',
                    'focus' => 'الحمل المتدرّج عبر القوة واللياقة القلبية والحركية وتكوين الجسم — مُبرمَج حسب الأهداف الشخصية (خسارة دهون، عضلات، صحة).',
                    'benefits' => ['❤️ يحسّن صحة القلب والقوة والأيض.', '🦴 يبني العضلات وكثافة العظام.', '🧠 يقلّل التوتر ويحسّن المزاج والنوم.', '🎯 قابل للتكيّف لأي عمر أو مستوى أو هدف.'],
                    'limitations' => ['🥋 لا يقدّم دفاعاً عن النفس ولا مهارة رياضية محدّدة.', '📋 يحتاج برمجة سليمة لتجنّب إصابات الإفراط.', '🔁 التحفيز/الاستمرارية هو التحدّي الأكبر.'],
                    'rules' => ['🏋️ ليست رياضة تنافسية — النجاح يُقاس بالتقدّم الشخصي.', '📈 قائم على الحمل المتدرّج والتعافي والاستمرارية.', '🩺 الإحماء والأداء الصحيح وأيام الراحة ضرورية.'],
                ],
                'links' => [
                    ['label' => 'American College of Sports Medicine (ACSM)', 'url' => 'https://www.acsm.org/'],
                    ['label' => 'WHO — Physical activity', 'url' => 'https://www.who.int/news-room/fact-sheets/detail/physical-activity'],
                    ['label' => 'Wikipedia — Physical fitness', 'url' => 'https://en.wikipedia.org/wiki/Physical_fitness'],
                ],
                'image_prompt' => "A cinematic, ultra-detailed hero poster in dramatic dark fantasy game-art style, 16:9 widescreen. Two athletes — a muscular male and a strong female — mid-action, one mid-deadlift lockout and one sprinting, wearing modern athletic gear, sweat and chalk, glowing energetic red-orange power-lines radiating strength. Behind them a translucent titan and lioness spirit amid a stormy sky. Background: a sleek modern gym fused with a stormy horizon, dust and sparks in the wind. Bold 3D title 'FITNESS TRAINING' with subtitle. Moody chiaroscuro, rim-lit silhouettes, orange-charcoal-steel palette, hyper-detailed 4K game cover art.",
            ],
            /* ---- CrossFit ---- */
            [
                'slug' => 'crossfit', 'name' => 'CrossFit', 'name_ar' => 'الكروسفيت',
                'icon' => 'bi-heart-pulse',
                'en' => [
                    'intro' => '🏋️ High-intensity functional training mixing weightlifting, gymnastics and cardio into constantly-varied "WODs" (workouts of the day), done in a community setting.',
                    'history' => 'Founded by <strong>Greg Glassman</strong> in 2000, CrossFit spread through affiliated "boxes" worldwide and its CrossFit Games crown "the Fittest on Earth".',
                    'focus' => 'Constantly varied, high-intensity functional movements — Olympic lifts, gymnastics, rowing, running — scored for time or rounds, building broad general fitness.',
                    'benefits' => ['🔥 Powerful all-round conditioning (strength + cardio).', '🤝 Strong community and coaching accountability.', '📈 Measurable, scalable, competitive progress.', '⏱️ Efficient, time-effective workouts.'],
                    'limitations' => ['🤕 High intensity raises injury risk without good form/coaching.', '🎯 Jack-of-all-trades — less specialised than dedicated strength or endurance training.', '💰 Affiliate membership can be pricey.'],
                    'rules' => ['⏱️ WODs are scored by time, rounds or load; every movement is scalable.', '🏆 Competitions (e.g. the Open, the Games) rank athletes on standardised workouts.', '🩺 Warm-up, scaling and mechanics-before-intensity are emphasised.'],
                ],
                'ar' => [
                    'intro' => '🏋️ تدريب وظيفي عالي الشدّة يمزج رفع الأثقال والجمباز والكارديو في تمارين يومية متغيّرة باستمرار (WODs) ضمن أجواء جماعية.',
                    'history' => 'أسّسه <strong>غريغ غلاسمان</strong> عام 2000، وانتشر الكروسفيت عبر صالات مُنتسِبة حول العالم، وتتوّج بطولته «الألعاب» «ألياق أهل الأرض».',
                    'focus' => 'حركات وظيفية عالية الشدّة متغيّرة باستمرار — رفعات أولمبية وجمباز وتجديف وجري — تُسجَّل بالوقت أو الجولات لبناء لياقة عامة واسعة.',
                    'benefits' => ['🔥 إعداد شامل قوي (قوة + كارديو).', '🤝 مجتمع قوي ومساءلة تدريبية.', '📈 تقدّم قابل للقياس والتدرّج والتنافس.', '⏱️ تمارين فعّالة وموفّرة للوقت.'],
                    'limitations' => ['🤕 الشدّة العالية ترفع خطر الإصابة بلا أداء/تدريب جيد.', '🎯 «يجيد كل شيء» — أقل تخصّصاً من تدريب القوة أو التحمّل المخصّص.', '💰 اشتراك الصالات المُنتسِبة قد يكون مكلفاً.'],
                    'rules' => ['⏱️ تُسجَّل الـWODs بالوقت أو الجولات أو الحِمل؛ وكل حركة قابلة للتدرّج.', '🏆 ترتّب المنافسات (مثل الأوبن والألعاب) الرياضيين على تمارين موحّدة.', '🩺 يُشدَّد على الإحماء والتدرّج و«الميكانيكا قبل الشدّة».'],
                ],
                'links' => [
                    ['label' => 'CrossFit (official)', 'url' => 'https://www.crossfit.com/'],
                    ['label' => 'Wikipedia — CrossFit', 'url' => 'https://en.wikipedia.org/wiki/CrossFit'],
                ],
                'image_prompt' => "A cinematic, ultra-detailed hero poster in dramatic dark fantasy game-art style, 16:9 widescreen. Two CrossFit athletes — a muscular male and a powerful female — mid-action, one mid clean-and-jerk overhead and one on rings mid muscle-up, wearing performance gear with knee sleeves and chalk, glowing fiery-orange effort energy exploding around them. Behind them a translucent bull and eagle spirit amid a stormy industrial sky. Background: an industrial box gym with barbells, rigs and rowing machines, dust and sparks in the wind. Bold 3D title 'CROSSFIT' with subtitle. Moody chiaroscuro, rim-lit silhouettes, orange-black-steel palette, hyper-detailed 4K game cover art.",
            ],
            /* ---- Weightlifting ---- */
            [
                'slug' => 'weightlifting', 'name' => 'Weightlifting', 'name_ar' => 'رفع الأثقال',
                'icon' => 'bi-heart-pulse',
                'en' => [
                    'intro' => '🏋️ Olympic weightlifting — the explosive sport of lifting maximal barbells overhead in two lifts: the snatch and the clean & jerk.',
                    'history' => 'One of the original 1896 modern Olympic sports, weightlifting is governed by the <strong>IWF</strong> and remains a benchmark of raw explosive human power.',
                    'focus' => 'Maximal power, speed and technique in the snatch (ground to overhead in one motion) and clean & jerk (to shoulders, then overhead). Precision under heavy load.',
                    'benefits' => ['⚡ Elite explosive power and full-body strength.', '🦴 Builds bone density, posture and athleticism.', '🎯 Transfers to many sports\' speed and power.', '🧠 Discipline, focus and technical mastery.'],
                    'limitations' => ['🤕 Technical lifts need coaching to lift safely.', '🩹 Wrist/shoulder/back mobility demands.', '🎯 Very specialised — not general conditioning by itself.'],
                    'rules' => ['🏋️ Two lifts: snatch and clean & jerk; best of three attempts each.', '🏆 Total = best snatch + best clean & jerk; highest total wins the bodyweight category.', '✅ Three referees judge a "good lift"; the bar must be held overhead under control.', '🚫 Pressing out, elbow touch, or dropping early = no lift.'],
                ],
                'ar' => [
                    'intro' => '🏋️ رفع الأثقال الأولمبي — رياضة انفجارية لرفع أقصى ثِقل فوق الرأس عبر رفعتين: الخطف والنتر.',
                    'history' => 'من رياضات أولمبياد 1896 الأصلية، ويشرف على رفع الأثقال <strong>الاتحاد الدولي IWF</strong>، ويبقى معياراً للقوة البشرية الانفجارية الخام.',
                    'focus' => 'أقصى قوة وسرعة وتقنية في الخطف (من الأرض إلى فوق الرأس بحركة واحدة) والنتر (إلى الكتفين ثم فوق الرأس). دقة تحت حِمل ثقيل.',
                    'benefits' => ['⚡ قوة انفجارية رفيعة وقوة للجسم كله.', '🦴 يبني كثافة العظام والوضعية والرياضية.', '🎯 ينتقل إلى سرعة وقوة كثير من الرياضات.', '🧠 انضباط وتركيز وإتقان تقني.'],
                    'limitations' => ['🤕 الرفعات التقنية تحتاج تدريباً للرفع بأمان.', '🩹 متطلبات مرونة للمعصم/الكتف/الظهر.', '🎯 عالي التخصّص — ليس إعداداً عاماً بمفرده.'],
                    'rules' => ['🏋️ رفعتان: الخطف والنتر؛ وأفضل ثلاث محاولات لكل منهما.', '🏆 المجموع = أفضل خطف + أفضل نتر؛ وأعلى مجموع يفوز في فئة الوزن.', '✅ ثلاثة حكّام يقرّرون «الرفعة الصحيحة»؛ ويجب تثبيت البار فوق الرأس بتحكّم.', '🚫 الضغط أو لمس المرفق أو الإسقاط المبكر = رفعة ملغاة.'],
                ],
                'links' => [
                    ['label' => 'International Weightlifting Federation (IWF)', 'url' => 'https://iwf.sport/'],
                    ['label' => 'Olympics — Weightlifting', 'url' => 'https://www.olympics.com/en/sports/weightlifting/'],
                    ['label' => 'Wikipedia — Olympic weightlifting', 'url' => 'https://en.wikipedia.org/wiki/Olympic_weightlifting'],
                ],
                'image_prompt' => "A cinematic, ultra-detailed hero poster in dramatic dark fantasy game-art style, 16:9 widescreen. Two weightlifters — a massive male and a powerful female — frozen at the top of an explosive snatch and jerk with loaded barbells overhead, wearing singlets, lifting belts and wrist wraps, chalk clouds bursting, glowing golden power-shockwaves radiating from the bar. Behind them a translucent atlas-titan and lioness spirit amid a stormy sky. Background: an Olympic weightlifting platform with bumper plates and banners, chalk dust and sparks in the wind. Bold 3D title 'WEIGHTLIFTING' with subtitle. Moody chiaroscuro, rim-lit silhouettes, gold-black-steel palette, hyper-detailed 4K game cover art.",
            ],
            /* ---- Bodybuilding ---- */
            [
                'slug' => 'bodybuilding', 'name' => 'Bodybuilding', 'name_ar' => 'كمال الأجسام',
                'icon' => 'bi-heart-pulse',
                'en' => [
                    'intro' => '💪 The art and sport of sculpting muscle size, symmetry and definition through resistance training, nutrition and conditioning, judged on stage by physique.',
                    'history' => 'Popularised by pioneers like Eugen Sandow (late 1800s) and the golden-era icons of the 1960s–70s (Arnold Schwarzenegger), bodybuilding is now a global sport under federations like the <strong>IFBB</strong>.',
                    'focus' => 'Hypertrophy training (muscle growth) plus disciplined nutrition and "cutting" to reveal muscularity, symmetry and conditioning for the stage.',
                    'benefits' => ['🦾 Dramatic muscle growth, strength and body composition.', '🍽️ Deep knowledge of nutrition and discipline.', '🧠 Goal-setting, consistency and self-image confidence.', '🦴 Improved metabolism and bone health.'],
                    'limitations' => ['⏳ Extremely demanding diet/training discipline.', '🎯 Aesthetics-focused — not sport-specific performance or self-defence.', '⚖️ Contest prep extremes require careful, healthy management.'],
                    'rules' => ['🏆 Judged on stage for muscularity, symmetry, proportion and conditioning.', '🕺 Athletes perform mandatory poses and routines by category (e.g. Men\'s Physique, Classic).', '📋 Federations set category and presentation standards.'],
                ],
                'ar' => [
                    'intro' => '💪 فنّ ورياضة نحت حجم العضلات وتناسقها وتحديدها عبر تدريب المقاومة والتغذية والإعداد، ويُحكَّم على المسرح حسب البنية الجسدية.',
                    'history' => 'شُعّب على يد روّاد مثل يوجين ساندو (أواخر القرن التاسع عشر) وأيقونات العصر الذهبي في الستينيات والسبعينيات (أرنولد شوارزنيغر)، وهو اليوم رياضة عالمية تحت اتحادات مثل <strong>IFBB</strong>.',
                    'focus' => 'تدريب التضخّم (نمو العضلات) مع تغذية منضبطة و«تنشيف» لإظهار الكتلة والتناسق والإعداد للمسرح.',
                    'benefits' => ['🦾 نمو عضلي وقوة وتكوين جسم لافت.', '🍽️ معرفة عميقة بالتغذية والانضباط.', '🧠 وضع أهداف واستمرارية وثقة بصورة الذات.', '🦴 تحسّن الأيض وصحة العظام.'],
                    'limitations' => ['⏳ انضباط غذائي/تدريبي شديد الصعوبة.', '🎯 يركّز على الجماليات — لا أداء رياضياً محدّداً ولا دفاعاً عن النفس.', '⚖️ تحضير المسابقات المتطرّف يتطلب إدارة صحية دقيقة.'],
                    'rules' => ['🏆 يُحكَّم على المسرح حسب الكتلة والتناسق والتناسب والإعداد.', '🕺 يؤدّي الرياضيون وقفات وروتينات إلزامية حسب الفئة (مثل فيزيك الرجال، الكلاسيك).', '📋 تضع الاتحادات معايير الفئات والتقديم.'],
                ],
                'links' => [
                    ['label' => 'IFBB (International Federation of Bodybuilding)', 'url' => 'https://www.ifbb.com/'],
                    ['label' => 'Wikipedia — Bodybuilding', 'url' => 'https://en.wikipedia.org/wiki/Bodybuilding'],
                ],
                'image_prompt' => "A cinematic, ultra-detailed hero poster in dramatic dark fantasy game-art style, 16:9 widescreen. Two bodybuilders — a massively muscular male and a shredded female — mid-action hitting a dramatic front double-biceps and side-chest pose, oiled physiques, competition posing trunks, glowing gold spotlight and molten-gold energy tracing muscle contours. Behind them a translucent Greek Atlas and lioness spirit amid a stormy sky. Background: a grand competition stage with spotlights and banners, gold sparks in the wind. Bold 3D title 'BODYBUILDING' with subtitle. Moody chiaroscuro, rim-lit silhouettes, gold-bronze-black palette, hyper-detailed 4K game cover art.",
            ],
            /* ---- Functional Training ---- */
            [
                'slug' => 'functional-training', 'name' => 'Functional Training', 'name_ar' => 'التدريب الوظيفي',
                'icon' => 'bi-heart-pulse',
                'en' => [
                    'intro' => '🤸 Training that mimics real-life movement patterns — pushing, pulling, squatting, hinging, rotating and carrying — to build practical, everyday strength and resilience.',
                    'history' => 'Rooted in rehabilitation and athletic conditioning, functional training moved into the mainstream in the 2000s as coaches emphasised multi-joint, multi-plane movement over isolated machines.',
                    'focus' => 'Compound, multi-plane movements using kettlebells, bands, sleds, medicine balls and bodyweight to train core stability, balance and transferable strength.',
                    'benefits' => ['🏃 Improves everyday movement, posture and injury resilience.', '⚖️ Builds core stability, balance and coordination.', '🎯 Transfers well to sport and daily life.', '🔧 Highly scalable for any age or level.'],
                    'limitations' => ['🏋️ Less optimal for pure maximal strength or hypertrophy than dedicated lifting.', '📋 Needs good coaching to program effectively.', '🎯 Not a competitive sport in itself.'],
                    'rules' => ['🤸 No competition format — programmed around movement quality and goals.', '📈 Progresses load, complexity and stability demand over time.', '🩺 Emphasises technique, core control and balanced programming.'],
                ],
                'ar' => [
                    'intro' => '🤸 تدريب يحاكي أنماط حركة الحياة الواقعية — الدفع والسحب والقرفصاء والمفصلة والدوران والحمل — لبناء قوة عملية يومية ومتانة.',
                    'history' => 'متجذّر في التأهيل والإعداد الرياضي، انتقل التدريب الوظيفي إلى السائد في العقد الأول من الألفية حين شدّد المدرّبون على الحركة متعددة المفاصل والمستويات بدل الأجهزة المعزولة.',
                    'focus' => 'حركات مركّبة متعددة المستويات باستخدام الكيتل بيل والأشرطة والزلّاجات وكرات الطبّ ووزن الجسم لتدريب ثبات الجذع والاتزان والقوة القابلة للانتقال.',
                    'benefits' => ['🏃 يحسّن الحركة اليومية والوضعية ومقاومة الإصابات.', '⚖️ يبني ثبات الجذع والاتزان والتناسق.', '🎯 ينتقل جيداً إلى الرياضة والحياة اليومية.', '🔧 قابل للتدرّج لأي عمر أو مستوى.'],
                    'limitations' => ['🏋️ أقل مثالية للقوة القصوى أو التضخّم من رفع الأثقال المخصّص.', '📋 يحتاج تدريباً جيداً لبرمجته بفعالية.', '🎯 ليس رياضة تنافسية بحدّ ذاته.'],
                    'rules' => ['🤸 لا صيغة تنافسية — مُبرمَج حول جودة الحركة والأهداف.', '📈 يتدرّج في الحِمل والتعقيد ومتطلّب الثبات مع الوقت.', '🩺 يشدّد على التقنية والتحكّم بالجذع والبرمجة المتوازنة.'],
                ],
                'links' => [
                    ['label' => 'ACE — Functional training', 'url' => 'https://www.acefitness.org/'],
                    ['label' => 'Wikipedia — Functional training', 'url' => 'https://en.wikipedia.org/wiki/Functional_training'],
                ],
                'image_prompt' => "A cinematic, ultra-detailed hero poster in dramatic dark fantasy game-art style, 16:9 widescreen. Two athletes — a fit male and a fit female — mid-action swinging a kettlebell and pushing a heavy sled, wearing functional training gear, sweat and dynamic motion, glowing teal-and-orange movement-arc energy. Behind them a translucent panther and stag spirit symbolising agility amid a stormy sky. Background: an open-turf functional gym with ropes, tyres and rigs, dust and sparks in the wind. Bold 3D title 'FUNCTIONAL TRAINING' with subtitle. Moody chiaroscuro, rim-lit silhouettes, teal-orange-charcoal palette, hyper-detailed 4K game cover art.",
            ],
            /* ---- Pilates ---- */
            [
                'slug' => 'pilates', 'name' => 'Pilates', 'name_ar' => 'البيلاتس',
                'icon' => 'bi-heart-pulse',
                'en' => [
                    'intro' => '🧘 A low-impact method building core strength, control, flexibility and posture through precise, controlled movements — on the mat or specialised reformer equipment.',
                    'history' => 'Developed by <strong>Joseph Pilates</strong> in the early 20th century (originally "Contrology"), it began as rehabilitation and body-conditioning and is now a global fitness and clinical practice.',
                    'focus' => 'Core ("powerhouse") control, breathing, alignment and precise, flowing movement — building deep stabilising strength and mobility with minimal joint stress.',
                    'benefits' => ['🏋️ Strong, stable core and improved posture.', '🤸 Better flexibility, mobility and body control.', '🩹 Excellent for rehab and back-care (low impact).', '🧠 Mind-body focus and stress relief.'],
                    'limitations' => ['❤️ Limited cardiovascular or high-strength stimulus alone.', '💰 Reformer classes need special equipment.', '🥋 No sport or self-defence application.'],
                    'rules' => ['🧘 Not competitive — practised as mat or equipment (reformer) classes.', '🎯 Guided by principles: centering, control, precision, breath, concentration, flow.', '🩺 Emphasises quality of movement over repetitions.'],
                ],
                'ar' => [
                    'intro' => '🧘 طريقة منخفضة التأثير تبني قوة الجذع والتحكّم والمرونة والوضعية عبر حركات دقيقة مُتحكَّم فيها — على البساط أو على جهاز الريفورمر المتخصّص.',
                    'history' => 'طوّرها <strong>جوزيف بيلاتس</strong> مطلع القرن العشرين (وسمّاها أصلاً «كونترولوجي»)، وبدأت تأهيلاً وإعداداً للجسم، وهي اليوم ممارسة لياقة وعلاجية عالمية.',
                    'focus' => 'التحكّم بالجذع («بيت القوة») والتنفّس والاصطفاف والحركة الدقيقة الانسيابية — لبناء قوة تثبيت عميقة وحركية بأقل إجهاد للمفاصل.',
                    'benefits' => ['🏋️ جذع قوي ثابت ووضعية أفضل.', '🤸 مرونة وحركية وتحكّم بالجسم أفضل.', '🩹 ممتاز للتأهيل والعناية بالظهر (منخفض التأثير).', '🧠 تركيز عقلي-جسدي وتفريغ للتوتر.'],
                    'limitations' => ['❤️ منبّه قلبي أو قوة عالية محدود بمفرده.', '💰 حصص الريفورمر تحتاج معدّات خاصة.', '🥋 لا تطبيق رياضياً أو دفاعاً عن النفس.'],
                    'rules' => ['🧘 غير تنافسي — يُمارَس حصصاً على البساط أو المعدّات (الريفورمر).', '🎯 يوجّهه مبادئ: التمركز، التحكّم، الدقة، التنفّس، التركيز، الانسياب.', '🩺 يشدّد على جودة الحركة لا عدد التكرارات.'],
                ],
                'links' => [
                    ['label' => 'Wikipedia — Pilates', 'url' => 'https://en.wikipedia.org/wiki/Pilates'],
                    ['label' => 'Wikipedia — Joseph Pilates', 'url' => 'https://en.wikipedia.org/wiki/Joseph_Pilates'],
                ],
                'image_prompt' => "A cinematic, ultra-detailed hero poster in dramatic dark fantasy game-art style, 16:9 widescreen. Two Pilates practitioners — a poised male and a graceful female — mid-flow in a controlled reformer plank and a mat teaser pose, wearing sleek activewear, glowing soft aqua-white core-energy spiralling from the midline. Behind them a translucent swan and jaguar spirit symbolising control amid a calm-stormy sky. Background: a bright minimalist studio with reformer machines silhouetted, soft petals drifting in gentle wind. Bold 3D title 'PILATES' with subtitle. Moody soft chiaroscuro, rim-lit silhouettes, aqua-white-slate palette, hyper-detailed 4K game cover art.",
            ],
            /* ---- Yoga ---- */
            [
                'slug' => 'yoga', 'name' => 'Yoga', 'name_ar' => 'اليوغا',
                'icon' => 'bi-heart-pulse',
                'en' => [
                    'intro' => '🧘 An ancient Indian mind-body practice uniting physical postures (asana), breathing (pranayama) and meditation to build flexibility, strength and calm.',
                    'history' => 'With roots over 2,000 years old in Indian philosophy (codified in Patanjali\'s Yoga Sutras), yoga evolved many modern styles (Hatha, Vinyasa, Ashtanga, Iyengar) and is now practised worldwide; the UN marks International Day of Yoga on June 21.',
                    'focus' => 'Linking breath and movement through postures and stillness — developing flexibility, balance, strength and a calm, focused mind.',
                    'benefits' => ['🤸 Greatly improves flexibility, balance and mobility.', '🧠 Reduces stress and supports mental well-being (well studied).', '💪 Builds functional strength and body awareness.', '🌬️ Better breathing, posture and sleep.'],
                    'limitations' => ['❤️ Most styles give limited high-intensity cardio.', '🤕 Overstretching/poor form can cause injury.', '🥋 No sport or self-defence application.'],
                    'rules' => ['🧘 Not competitive (though "yoga asana" sport exists) — practised in guided classes or self-practice.', '🎯 Guided by breath, alignment and mindful progression.', '🩺 Emphasises listening to the body and safe modification.'],
                ],
                'ar' => [
                    'intro' => '🧘 ممارسة هندية قديمة للعقل والجسد توحّد الوضعيات البدنية (أسانا) والتنفّس (براناياما) والتأمّل لبناء المرونة والقوة والهدوء.',
                    'history' => 'بجذور تتجاوز 2000 عام في الفلسفة الهندية (مُقنَّنة في «يوغا سوترا» لباتانجالي)، طوّرت اليوغا أساليب حديثة عديدة (هاثا، فينياسا، أشتانغا، أيينغار)، وتُمارَس اليوم عالمياً؛ وتحتفل الأمم المتحدة باليوم العالمي لليوغا في 21 يونيو.',
                    'focus' => 'ربط التنفّس بالحركة عبر الوضعيات والسكون — لتطوير المرونة والاتزان والقوة وذهن هادئ مركّز.',
                    'benefits' => ['🤸 تحسّن كبير في المرونة والاتزان والحركية.', '🧠 تقلّل التوتر وتدعم الصحة النفسية (مدروسة جيداً).', '💪 تبني قوة وظيفية ووعياً جسدياً.', '🌬️ تنفّس ووضعية ونوم أفضل.'],
                    'limitations' => ['❤️ معظم الأساليب تعطي كارديو محدود الشدّة.', '🤕 المبالغة في التمدّد أو الأداء الخاطئ قد يسبّب إصابة.', '🥋 لا تطبيق رياضياً أو دفاعاً عن النفس.'],
                    'rules' => ['🧘 غير تنافسية (وإن وُجدت رياضة «أسانا اليوغا») — تُمارَس حصصاً موجّهة أو ذاتياً.', '🎯 يوجّهها التنفّس والاصطفاف والتدرّج الواعي.', '🩺 تشدّد على الإصغاء للجسم والتعديل الآمن.'],
                ],
                'links' => [
                    ['label' => 'UN — International Day of Yoga', 'url' => 'https://www.un.org/en/observances/yoga-day'],
                    ['label' => 'Wikipedia — Yoga', 'url' => 'https://en.wikipedia.org/wiki/Yoga'],
                    ['label' => 'NIH/NCCIH — Yoga', 'url' => 'https://www.nccih.nih.gov/health/yoga-what-you-need-to-know'],
                ],
                'image_prompt' => "A cinematic, ultra-detailed hero poster in dramatic dark fantasy game-art style, 16:9 widescreen. Two yogis — a serene male and a graceful female — mid-flow in a balanced warrior and a floating crow pose, wearing elegant yoga wear, glowing seven-chakra rainbow-soft energy rising along the spine. Behind them a translucent lotus deity and serpent (kundalini) amid a dawn-lit misty sky. Background: a Himalayan temple terrace at sunrise with prayer flags, lotus petals and mist in gentle wind. Bold 3D title 'YOGA' with subtitle. Moody soft chiaroscuro, rim-lit silhouettes, saffron-violet-teal palette, hyper-detailed 4K game cover art.",
            ],
            /* ---- Zumba ---- */
            [
                'slug' => 'zumba', 'name' => 'Zumba', 'name_ar' => 'الزومبا',
                'icon' => 'bi-music-note-beamed',
                'en' => [
                    'intro' => '💃 A joyful dance-fitness workout set to Latin and world music — cardio disguised as a party, mixing salsa, merengue, reggaeton and more.',
                    'history' => 'Created by Colombian dancer <strong>Alberto "Beto" Pérez</strong> in the 1990s and launched as a brand in 2001, Zumba grew into one of the world\'s most popular group-fitness programs.',
                    'focus' => 'Easy-to-follow dance choreography over interval-style music — alternating high and low intensity to burn calories while having fun, no dance experience needed.',
                    'benefits' => ['❤️ Great cardio and calorie burn.', '😄 Fun, social and highly motivating.', '🎵 Improves rhythm, coordination and mood.', '🔧 Scalable for all fitness levels.'],
                    'limitations' => ['🏋️ Limited strength building on its own.', '🎯 Not a sport or skill discipline.', '🦵 High-tempo steps need care for joint-sensitive beginners.'],
                    'rules' => ['💃 Not competitive — instructor-led group classes.', '🎶 Follows the music\'s interval structure (warm-up, peaks, cool-down).', '🔧 Movements are freely modifiable to ability.'],
                ],
                'ar' => [
                    'intro' => '💃 تمرين لياقة راقص بهيج على إيقاع الموسيقى اللاتينية والعالمية — كارديو متخفٍّ في هيئة حفلة، يمزج السالسا والميرينغي والريغيتون وغيرها.',
                    'history' => 'ابتكرها الراقص الكولومبي <strong>ألبرتو «بيتو» بيريز</strong> في التسعينيات وأُطلقت كعلامة عام 2001، ونمت الزومبا لتصبح من أشهر برامج اللياقة الجماعية في العالم.',
                    'focus' => 'كوريغرافيا رقص سهلة المتابعة على موسيقى بأسلوب فتري — تتناوب الشدّة العالية والمنخفضة لحرق السعرات بمتعة، دون خبرة رقص.',
                    'benefits' => ['❤️ كارديو ممتاز وحرق للسعرات.', '😄 ممتعة واجتماعية ومحفّزة جداً.', '🎵 تحسّن الإيقاع والتناسق والمزاج.', '🔧 قابلة للتدرّج لكل مستويات اللياقة.'],
                    'limitations' => ['🏋️ بناء قوة محدود بمفردها.', '🎯 ليست رياضة أو مهارة تخصّصية.', '🦵 الخطوات السريعة تحتاج حذراً للمبتدئين ذوي المفاصل الحسّاسة.'],
                    'rules' => ['💃 غير تنافسية — حصص جماعية يقودها مدرّب.', '🎶 تتبع البنية الفترية للموسيقى (إحماء، ذروات، تهدئة).', '🔧 الحركات قابلة للتعديل بحرّية حسب القدرة.'],
                ],
                'links' => [
                    ['label' => 'Zumba (official)', 'url' => 'https://www.zumba.com/'],
                    ['label' => 'Wikipedia — Zumba', 'url' => 'https://en.wikipedia.org/wiki/Zumba'],
                ],
                'image_prompt' => "A cinematic, ultra-detailed hero poster in dramatic dark fantasy game-art style, 16:9 widescreen. Two Zumba dancers — a vibrant male and a joyful female — mid-action in an explosive salsa-spin and hip-shake, wearing colourful dance-fitness outfits, glowing pink-and-turquoise music-note energy and confetti swirling. Behind them a translucent tropical phoenix and jaguar spirit amid a festive neon storm. Background: a carnival street stage with lights and streamers, confetti and sparks in the wind. Bold 3D title 'ZUMBA' with subtitle. Moody vibrant chiaroscuro, rim-lit silhouettes, magenta-turquoise-gold palette, hyper-detailed 4K game cover art.",
            ],
            /* ---- Spinning ---- */
            [
                'slug' => 'spinning', 'name' => 'Spinning (Indoor Cycling)', 'name_ar' => 'سبينينغ (الدرّاجات الداخلية)',
                'icon' => 'bi-bicycle',
                'en' => [
                    'intro' => '🚴 High-energy indoor cycling on stationary bikes, led by an instructor to music through climbs, sprints and intervals — intense cardio for all levels.',
                    'history' => 'Pioneered by cyclist <strong>Jonathan Goldberg (Johnny G)</strong> in the late 1980s–90s, "Spinning" became a trademarked format and sparked the global indoor-cycling and boutique-studio boom.',
                    'focus' => 'Cardio intervals controlled by resistance and cadence — simulating flats, hills and sprints to music, building leg endurance and cardiovascular fitness.',
                    'benefits' => ['❤️ Powerful cardiovascular fitness and calorie burn.', '🦵 Builds leg endurance and strength (low-impact on joints).', '🎵 Motivating, music-driven group energy.', '🔧 Fully self-paced via resistance.'],
                    'limitations' => ['💪 Little upper-body or full-body strength work.', '🪑 Bike fit matters — poor setup can cause knee/back strain.', '🎯 Not a skill or competitive sport.'],
                    'rules' => ['🚴 Not competitive — instructor-led classes to music.', '🎛️ Intensity is controlled by each rider\'s resistance and cadence.', '🩺 Proper bike setup and hydration are key.'],
                ],
                'ar' => [
                    'intro' => '🚴 ركوب درّاجات داخلي عالي الطاقة على درّاجات ثابتة، يقوده مدرّب على الموسيقى عبر صعود وسباقات وفترات — كارديو مكثّف لكل المستويات.',
                    'history' => 'ابتكره الدرّاج <strong>جوناثان غولدبرغ (جوني جي)</strong> أواخر الثمانينيات والتسعينيات، وصار «سبينينغ» صيغةً مسجّلة أشعلت ازدهار الدرّاجات الداخلية والاستوديوهات حول العالم.',
                    'focus' => 'فترات كارديو تُتحكَّم بالمقاومة والإيقاع — تحاكي المسطّحات والتلال والسباقات على الموسيقى، لبناء تحمّل الساقين واللياقة القلبية.',
                    'benefits' => ['❤️ لياقة قلبية قوية وحرق للسعرات.', '🦵 تبني تحمّل وقوة الساقين (منخفض التأثير على المفاصل).', '🎵 طاقة جماعية محفّزة تقودها الموسيقى.', '🔧 ذاتيّ الإيقاع بالكامل عبر المقاومة.'],
                    'limitations' => ['💪 عمل قوة قليل للجزء العلوي أو الجسم كله.', '🪑 ضبط الدرّاجة مهم — والإعداد السيّئ قد يجهد الركبة/الظهر.', '🎯 ليست مهارة أو رياضة تنافسية.'],
                    'rules' => ['🚴 غير تنافسي — حصص يقودها مدرّب على الموسيقى.', '🎛️ تُتحكَّم الشدّة بمقاومة وإيقاع كل متدرّب.', '🩺 ضبط الدرّاجة الصحيح والترطيب أساسيان.'],
                ],
                'links' => [
                    ['label' => 'Wikipedia — Indoor cycling', 'url' => 'https://en.wikipedia.org/wiki/Indoor_cycling'],
                    ['label' => 'Wikipedia — Spinning (cycling)', 'url' => 'https://en.wikipedia.org/wiki/Spinning_(cycling)'],
                ],
                'image_prompt' => "A cinematic, ultra-detailed hero poster in dramatic dark fantasy game-art style, 16:9 widescreen. Two indoor cyclists — a driven male and a fierce female — mid-action out of the saddle sprinting on spin bikes, wearing cycling gear, sweat flying, glowing electric-blue speed-lines and energy pulsing from the wheels. Behind them a translucent cheetah and falcon spirit amid a stormy neon sky. Background: a dark boutique spin studio with rows of bikes and neon lights, sparks in the wind. Bold 3D title 'SPINNING' with subtitle. Moody chiaroscuro, rim-lit silhouettes, electric-blue-magenta-black palette, hyper-detailed 4K game cover art.",
            ],
            /* ---- Aerobics ---- */
            [
                'slug' => 'aerobics', 'name' => 'Aerobics', 'name_ar' => 'الأيروبيك',
                'icon' => 'bi-heart-pulse',
                'en' => [
                    'intro' => '🤸 Rhythmic, music-driven cardio exercise combining movement patterns, steps and light strength work to boost heart health, endurance and coordination.',
                    'history' => 'Popularised by <strong>Dr. Kenneth Cooper</strong>\'s 1968 book "Aerobics" and the 1980s fitness boom (Jane Fonda), aerobics remains a group-fitness staple, with a competitive "sport aerobics" discipline too.',
                    'focus' => 'Continuous rhythmic movement to music at moderate-to-high intensity — improving cardiovascular fitness, stamina and coordination, often with step or dance elements.',
                    'benefits' => ['❤️ Strong cardiovascular and endurance gains.', '🔥 Effective calorie burn and weight management.', '🧠 Mood, energy and coordination boost.', '🔧 Scalable and beginner-friendly.'],
                    'limitations' => ['🏋️ Limited maximal strength development.', '🦵 High-impact versions need joint care.', '🎯 Mostly fitness, not a skill sport (except competitive aerobics).'],
                    'rules' => ['🤸 Recreational classes are instructor-led to music, not scored.', '🏆 Competitive "sport aerobics" judges routines on execution and difficulty.', '🩺 Warm-up, footwear and pacing matter.'],
                ],
                'ar' => [
                    'intro' => '🤸 تمرين كارديو إيقاعي تقوده الموسيقى يجمع أنماط حركة وخطوات وعمل قوة خفيف لتعزيز صحة القلب والتحمّل والتناسق.',
                    'history' => 'شُعّب عبر كتاب <strong>د. كينيث كوبر</strong> «الأيروبيك» عام 1968 وطفرة اللياقة في الثمانينيات (جين فوندا)، ويبقى الأيروبيك ركناً في اللياقة الجماعية، مع وجود فرع تنافسي «أيروبيك رياضي» أيضاً.',
                    'focus' => 'حركة إيقاعية متّصلة على الموسيقى بشدّة متوسطة إلى عالية — لتحسين اللياقة القلبية والقدرة على التحمّل والتناسق، وغالباً بعناصر ستِب أو رقص.',
                    'benefits' => ['❤️ مكاسب قلبية وتحمّلية قوية.', '🔥 حرق فعّال للسعرات وإدارة للوزن.', '🧠 تحسين للمزاج والطاقة والتناسق.', '🔧 قابل للتدرّج وملائم للمبتدئين.'],
                    'limitations' => ['🏋️ تطوير محدود للقوة القصوى.', '🦵 النسخ عالية التأثير تحتاج عناية بالمفاصل.', '🎯 لياقة غالباً لا رياضة مهارية (إلا الأيروبيك التنافسي).'],
                    'rules' => ['🤸 الحصص الترفيهية يقودها مدرّب على الموسيقى دون تسجيل.', '🏆 «الأيروبيك الرياضي» التنافسي يقيّم الروتينات حسب الأداء والصعوبة.', '🩺 الإحماء والحذاء وإدارة الإيقاع مهمّة.'],
                ],
                'links' => [
                    ['label' => 'Wikipedia — Aerobics', 'url' => 'https://en.wikipedia.org/wiki/Aerobics'],
                    ['label' => 'Wikipedia — Aerobic gymnastics', 'url' => 'https://en.wikipedia.org/wiki/Aerobic_gymnastics'],
                ],
                'image_prompt' => "A cinematic, ultra-detailed hero poster in dramatic dark fantasy game-art style, 16:9 widescreen. Two aerobics athletes — an energetic male and a dynamic female — mid-action in a high-kick step and a synchronized jump, wearing bright retro-modern activewear, glowing pink-and-cyan rhythmic energy trails. Behind them a translucent flamingo and gazelle spirit amid a vibrant stormy sky. Background: a bright fitness studio stage with step platforms and lights, confetti and sparks in the wind. Bold 3D title 'AEROBICS' with subtitle. Moody vibrant chiaroscuro, rim-lit silhouettes, pink-cyan-white palette, hyper-detailed 4K game cover art.",
            ],
        ];
    }
}
