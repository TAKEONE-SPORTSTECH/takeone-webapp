<?php

namespace Database\Seeders;

use Database\Seeders\Concerns\SeedsActivityContent;
use Illuminate\Database\Seeder;

/**
 * Deep, full-long-form bilingual (EN + AR) directory content — BATCH 1: the
 * flagship combat sports & martial arts, incl. variant splits. History is
 * multi-paragraph; rules and benefits are explained in depth; 5-6 trusted
 * sources each. Idempotent, keyed by slug. Generic parents are hidden once
 * split (see the `replaces` key).
 */
class ActivityContentSeeder extends Seeder
{
    use SeedsActivityContent;

    protected function entries(): array
    {
        return [

            /* ============ TAEKWONDO — WT / OLYMPIC ============ */
            [
                'slug' => 'taekwondo-wt',
                'name' => 'Taekwondo (WT / Olympic)',
                'name_ar' => 'التايكوندو (الاتحاد العالمي / الأولمبي)',
                'icon' => 'bi-person-arms-up',
                'replaces' => 'taekwondo',
                'en' => [
                    'intro' => '🥋 The Olympic, sport-oriented branch of Taekwondo governed by <strong>World Taekwondo (WT)</strong>. It is instantly recognisable for its lightning-fast, high and spinning kicks, its bouncing footwork, and its electronic scoring system — a modern combat sport built on a centuries-old Korean kicking tradition.',
                    'history' => [
                        'Taekwondo\'s roots reach back to indigenous Korean fighting arts such as <em>taekkyon</em> and <em>subak</em>, but the art in its modern form was forged in the turbulent years after the 1945 liberation of Korea. During the Japanese occupation many Koreans had trained in Japanese karate, and in the late 1940s a wave of schools known as the <em>kwans</em> (Chung Do Kwan, Moo Duk Kwan, Song Moo Kwan and others) opened in Seoul, blending Korean kicking traditions with karate\'s striking framework.',
                        'In 1955 a committee chose the unifying name "Taekwon-Do" — literally "the way of the foot and fist." The Korea Taekwondo Association formed in 1959–1961 to bring the kwans together, and in 1972 the <strong>Kukkiwon</strong> was established in Seoul as the art\'s technical headquarters and world academy. The <strong>World Taekwondo Federation</strong> (now World Taekwondo) was founded in 1973, the same year as the first World Championships, and set the sport rules that define this branch today.',
                        'Recognition followed quickly: Taekwondo appeared as a demonstration sport at the Seoul 1988 and Barcelona 1992 Olympics, and became a full medal sport at Sydney 2000. The introduction of electronic body protectors in the 2000s and rule changes rewarding head and spinning kicks shaped the fast, high-kicking style seen in today\'s Olympic competition.',
                    ],
                    'focus' => [
                        'WT Taekwondo is a kicking-dominant art built around distance, timing and speed. Fighters bounce in and out of range on a light, mobile stance, using cut kicks and fast round kicks to score to the trunk, and reserving the highest rewards for kicks to the head — especially spinning ones. Hand techniques exist but play a minor scoring role, so the game is largely fought with the legs.',
                        'Alongside sparring (<em>kyorugi</em>), practitioners train <em>poomsae</em> — set patterns of blocks, strikes and kicks that preserve the technical curriculum and are themselves a competitive discipline. Training also emphasises flexibility, explosive hip rotation and the sport\'s five tenets: courtesy, integrity, perseverance, self-control and indomitable spirit.',
                    ],
                    'benefits' => [
                        '🦵 <strong>Exceptional lower-body power and flexibility</strong> — years of high kicking build hip mobility and dynamic flexibility few other sports match.',
                        '❤️ <strong>Cardiovascular fitness and explosive speed</strong> from the constant bouncing, sprinting and reactive kicking.',
                        '🧠 <strong>Discipline and emotional control</strong> — the tenet system and belt structure teach focus, patience and respect, which studies link to improved behaviour and confidence in young people.',
                        '⚖️ <strong>Balance and coordination</strong> developed by controlling the body on one leg through fast, complex kicks.',
                        '🛡️ <strong>Confidence and stress relief</strong> — structured progress through the belts gives measurable achievement for children and adults alike.',
                        '🌍 <strong>A clear competitive pathway</strong> from club to national team to the Olympic Games.',
                    ],
                    'limitations' => [
                        '👊 <strong>Limited hand striking</strong> — punches score low and punches to the head are illegal, so it develops less boxing-style hand defence than Muay Thai or boxing.',
                        '🤼 <strong>No grappling, clinch or ground fighting</strong>, which leaves a gap for all-round self-defence.',
                        '🛡️ <strong>Sport rules can reward point-touches over power</strong>, and the electronic scoring game can differ from a real self-defence situation.',
                        '🦵 <strong>High-kick emphasis</strong> demands flexibility and can strain the hips/knees without proper conditioning.',
                    ],
                    'rules' => [
                        '⏱️ A bout is normally three rounds of two minutes with one-minute breaks, contested on an octagonal mat (8m across).',
                        '🎯 Scoring: <strong>1 point</strong> for a valid punch to the trunk, <strong>2</strong> for a trunk kick, <strong>4</strong> for a spinning trunk kick, <strong>3</strong> for a head kick and <strong>5</strong> for a spinning head kick.',
                        '🦺 Valid contact is registered by <strong>electronic body protectors (PSS)</strong> and electronic head guards, with judges confirming technical (spinning) points.',
                        '🚫 Illegal actions include punching the head, attacking below the waist, grabbing, pushing and turning the back — each drawing a <em>gam-jeom</em> penalty (a point to the opponent).',
                        '🥇 A round/match can end early by a large points gap or by knockout; otherwise the higher score wins, with best-of-three rounds.',
                        '🥋 <strong>Poomsae</strong> is a separate competitive event judged on accuracy, power and presentation of set patterns.',
                        '🎽 Ranks progress through coloured belts (<em>geup</em>) up to black belt (<em>dan</em>), certified through the Kukkiwon.',
                    ],
                ],
                'ar' => [
                    'intro' => '🥋 الفرع الأولمبي الرياضي من التايكوندو الخاضع لإشراف <strong>الاتحاد العالمي للتايكوندو (WT)</strong>. يُعرَف فوراً بركلاته العالية والدورانية الخاطفة، وحركة قدميه الوثّابة، ونظام احتسابه الإلكتروني — رياضة قتالية حديثة مبنيّة على تقليد كوري في الركل عمره قرون.',
                    'history' => [
                        'تعود جذور التايكوندو إلى فنون كورية أصيلة مثل <em>التايكيون</em> و<em>السوباك</em>، لكن الفن بشكله الحديث تبلور في السنوات المضطربة بعد تحرير كوريا عام 1945. فخلال الاحتلال الياباني تدرّب كثير من الكوريين على الكاراتيه الياباني، وفي أواخر الأربعينيات افتُتحت في سيول موجة من المدارس تُعرف بالـ<em>كوان</em> (تشونغ دو كوان، مو دوك كوان، سونغ مو كوان وغيرها) مازجةً تقاليد الركل الكورية بإطار الضرب الكاراتيهي.',
                        'وفي عام 1955 اختارت لجنة الاسم الموحّد «تايكوون-دو» — أي «طريق القدم والقبضة». وتشكّلت الرابطة الكورية للتايكوندو بين عامي 1959 و1961 لتوحيد الكوانات، وفي 1972 أُنشئ <strong>الكوكيون</strong> في سيول مركزاً تقنياً وأكاديمية عالمية للفن. وتأسّس <strong>الاتحاد العالمي للتايكوندو</strong> عام 1973، وهو عام أول بطولة عالمية، ووضع القوانين الرياضية التي تحدّد هذا الفرع اليوم.',
                        'وتتالى الاعتراف سريعاً: ظهر التايكوندو رياضةً استعراضية في أولمبياد سيول 1988 وبرشلونة 1992، وصار رياضة ميداليات كاملة في سيدني 2000. وأسهم إدخال الواقيات الإلكترونية في العقد الأول من الألفية وتعديلات القوانين التي تكافئ ركلات الرأس والدوران في تشكيل الأسلوب السريع عالي الركل الذي نراه في المنافسات الأولمبية اليوم.',
                    ],
                    'focus' => [
                        'التايكوندو (WT) فنّ قائم على الركل يدور حول المسافة والتوقيت والسرعة. يثب المقاتل داخل المدى وخارجه على وقفة خفيفة رشيقة، مستخدماً الركلات القاطعة والدائرية السريعة للتسجيل على الجذع، مع أعلى المكافآت لركلات الرأس — خصوصاً الدورانية. أما تقنيات اليد فحاضرة لكن دورها في التسجيل ثانوي، فاللعبة تُخاض بالساقين أساساً.',
                        'إلى جانب القتال (<em>كيوروغي</em>)، يتدرّب الممارسون على <em>البومسيه</em> — أنماط محدّدة من الصدّات والضربات والركلات تحافظ على المنهج التقني وهي نفسها فرع تنافسي. ويؤكّد التدريب أيضاً على المرونة ودوران الورك الانفجاري ومبادئ الفن الخمسة: اللياقة، والنزاهة، والمثابرة، وضبط النفس، والروح التي لا تُقهر.',
                    ],
                    'benefits' => [
                        '🦵 <strong>قوة ومرونة استثنائية للجزء السفلي</strong> — سنوات من الركل العالي تبني حركية الورك ومرونة ديناميكية قلّ أن تضاهيها رياضات أخرى.',
                        '❤️ <strong>لياقة قلبية وسرعة انفجارية</strong> من الوثب والعَدْو والركل التفاعلي المستمر.',
                        '🧠 <strong>انضباط وضبط انفعالي</strong> — يعلّم نظام المبادئ وبنية الأحزمة التركيز والصبر والاحترام، وهو ما تربطه الدراسات بتحسّن السلوك والثقة لدى الصغار.',
                        '⚖️ <strong>اتزان وتناسق</strong> يتطوّران بالتحكّم بالجسم على قدم واحدة عبر ركلات سريعة معقّدة.',
                        '🛡️ <strong>ثقة وتفريغ للتوتر</strong> — التدرّج المنظّم عبر الأحزمة يمنح إنجازاً ملموساً للأطفال والكبار.',
                        '🌍 <strong>مسار تنافسي واضح</strong> من النادي إلى المنتخب الوطني إلى الألعاب الأولمبية.',
                    ],
                    'limitations' => [
                        '👊 <strong>ضرب يدوي محدود</strong> — نقاط اللكم قليلة ولكم الرأس ممنوع، فيطوّر دفاعاً يدوياً أقل من الملاكمة أو المواي تاي.',
                        '🤼 <strong>لا مصارعة ولا اشتباك ولا قتال أرضي</strong>، ما يترك ثغرة في الدفاع الشامل عن النفس.',
                        '🛡️ <strong>قد تكافئ القوانين اللمسة على القوة</strong>، وقد تختلف لعبة الاحتساب الإلكتروني عن موقف دفاع حقيقي.',
                        '🦵 <strong>التركيز على الركل العالي</strong> يتطلب مرونة وقد يُجهد الورك/الركبة دون إعداد سليم.',
                    ],
                    'rules' => [
                        '⏱️ النزال عادةً ثلاث جولات مدة كل منها دقيقتان مع راحة دقيقة، على بساط ثماني الأضلاع (8 أمتار).',
                        '🎯 الاحتساب: <strong>نقطة</strong> للكمة صحيحة على الجذع، و<strong>نقطتان</strong> لركلة الجذع، و<strong>4</strong> للركلة الدورانية على الجذع، و<strong>3</strong> لركلة الرأس، و<strong>5</strong> للركلة الدورانية على الرأس.',
                        '🦺 تُسجَّل اللمسات الصحيحة بـ<strong>واقيات إلكترونية للجذع (PSS)</strong> وواقيات رأس إلكترونية، مع تأكيد الحكّام للنقاط الفنية (الدورانية).',
                        '🚫 من المخالفات لكم الرأس والضرب تحت الخصر والإمساك والدفع وإدارة الظهر — وكلٌّ منها يستوجب عقوبة <em>غام-جيوم</em> (نقطة للخصم).',
                        '🥇 قد تنتهي الجولة/النزال مبكّراً بفارق نقاط كبير أو بالضربة القاضية؛ وإلا فالأعلى نقاطاً يفوز، بنظام أفضل من ثلاث جولات.',
                        '🥋 <strong>البومسيه</strong> فعالية تنافسية منفصلة تُقيَّم على دقة الأنماط المحدّدة وقوّتها وتقديمها.',
                        '🎽 تتدرّج الرتب عبر الأحزمة الملوّنة (<em>غِيوب</em>) حتى الحزام الأسود (<em>دان</em>) بشهادة الكوكيون.',
                    ],
                ],
                'links' => [
                    ['label' => 'World Taekwondo (official)', 'url' => 'https://www.worldtaekwondo.org/'],
                    ['label' => 'Kukkiwon — World Taekwondo Headquarters', 'url' => 'https://www.kukkiwon.or.kr/'],
                    ['label' => 'Olympics — Taekwondo', 'url' => 'https://www.olympics.com/en/sports/taekwondo/'],
                    ['label' => 'Britannica — Taekwondo', 'url' => 'https://www.britannica.com/sports/tae-kwon-do'],
                    ['label' => 'Wikipedia — Taekwondo', 'url' => 'https://en.wikipedia.org/wiki/Taekwondo'],
                ],
                'image_prompt' => "A cinematic, ultra-detailed hero poster in dramatic dark fantasy game-art style, 16:9 widescreen. Two Taekwondo (WT/Olympic style) warriors — a muscular male fighter and a fierce female fighter — caught mid-action performing a spinning jump head kick (dwit-hurigi), wearing crisp white WT dobok uniforms with black-trimmed collars, Kukkiwon and World Taekwondo emblems, and colored competition trunk protectors (hogu) in red and blue, with glowing electric-blue chi energy crackling around their kicks. Behind them looms a massive translucent mythic Korean tiger and blue dragon deity coiled in the storm clouds. The background reveals Seoul's skyline with Gyeongbokgung palace rooftops and taegeuk-emblem banners, cherry-blossom petals and sparks caught in the wind. Bold stylized 3D calligraphic title text reading 'TAEKWONDO — WT / OLYMPIC' dominates the top in hangul-inspired lettering, with an evocative subtitle beneath. Moody chiaroscuro lighting, rim-lit silhouettes, red-white-blue palette, hyper-detailed digital painting, 4K premium game cover art quality.",
            ],

            /* ============ TAEKWONDO — ITF ============ */
            [
                'slug' => 'taekwondo-itf',
                'name' => 'Taekwondo (ITF)',
                'name_ar' => 'التايكوندو (الاتحاد الدولي ITF)',
                'icon' => 'bi-person-arms-up',
                'replaces' => 'taekwondo',
                'en' => [
                    'intro' => '🥋 The traditional branch of Taekwondo founded by General <strong>Choi Hong Hi</strong> under the <strong>International Taekwon-Do Federation (ITF)</strong>. It is known for its distinctive "sine-wave" power motion, its balanced blend of hand and foot techniques, and its 24 patterns (<em>tul</em>) — one for each hour of the day.',
                    'history' => [
                        'General <strong>Choi Hong Hi</strong>, a Korean army officer who had studied karate in Japan, is the figure most associated with the naming and early organisation of Taekwon-Do; he is widely credited with promoting the name adopted in 1955. Choi combined Korean kicking with a karate-derived technical base and a body of theory, patterns and philosophy that he formalised over the following years.',
                        'In 1966 Choi founded the <strong>International Taekwon-Do Federation</strong> in Seoul to spread the art internationally. Political tensions between Choi and the South Korean sporting establishment led to a decisive split in the 1970s: the sport-focused World Taekwondo branch grew under the Kukkiwon, while Choi took the ITF abroad, headquartering it in Canada and later Vienna, and emphasising a more self-defence-oriented, military-rooted curriculum.',
                        'After Choi\'s death in 2002, the ITF fractured into several organisations that still share his patterns and philosophy but operate independently. ITF Taekwon-Do remains a globally practised traditional martial art, distinct from the Olympic WT branch in both technique and competition rules.',
                    ],
                    'focus' => [
                        'ITF technique is driven by the "sine-wave" motion — a relax–drop–rise cycle of the body that Choi\'s theory uses to generate power by dropping and rising the mass with each technique. The art gives punches and hand techniques a much larger role than the Olympic branch, and in sparring, controlled punches to the head are permitted.',
                        'The curriculum centres on the <strong>24 tul (patterns)</strong>, each with historical or philosophical meaning, alongside semi/light-contact sparring, self-defence, power tests and "special technique" (high jumping kicks). Etiquette, tenets and theory are treated as core parts of the art rather than add-ons.',
                    ],
                    'benefits' => [
                        '🥊 <strong>A more complete striking mix</strong> than the Olympic branch — hands and feet both feature, including head punches in sparring.',
                        '🧩 <strong>Rich patterns (tul)</strong> that train memory, balance, coordination and precise technique.',
                        '🧠 <strong>Strong philosophical grounding</strong> in the tenets and theory, building discipline and self-control.',
                        '🛡️ <strong>Self-defence orientation</strong> — the curriculum keeps practical application close to the surface.',
                        '💪 <strong>Whole-body conditioning</strong> from patterns, power work and sparring.',
                        '🤝 <strong>Etiquette and community</strong> that make it accessible for families and all ages.',
                    ],
                    'limitations' => [
                        '🥇 <strong>Not on the Olympic programme</strong> — the Olympic branch is WT, so competitive pathways differ.',
                        '🤼 <strong>Limited grappling and ground work</strong>, as with most striking arts.',
                        '🌐 <strong>Organisational fragmentation</strong> — several ITF bodies exist after the post-2002 splits, so standards and rules can vary between them.',
                        '🥋 <strong>The sine-wave method is debated</strong> among martial artists regarding its combat efficiency.',
                    ],
                    'rules' => [
                        '🥋 Sparring is typically continuous light/semi-contact over timed rounds, emphasising control.',
                        '🎯 Points are awarded for controlled hand and foot strikes to legal targets; unlike WT, <strong>punches to the head are permitted</strong>.',
                        '🧩 Grading and competition include <strong>patterns (tul)</strong>, sparring, power test and special technique.',
                        '🚫 Excessive contact, attacks to prohibited areas and unsportsmanlike conduct are penalised.',
                        '🎽 Ranks run through coloured belts (<em>gup</em>) to black belt (<em>dan</em>), certified by the practitioner\'s ITF organisation.',
                        '🏆 Events are organised by the various ITF federations rather than a single Olympic body.',
                    ],
                ],
                'ar' => [
                    'intro' => '🥋 الفرع التقليدي من التايكوندو الذي أسّسه الجنرال <strong>تشوي هونغ هي</strong> ضمن <strong>الاتحاد الدولي للتايكوندو (ITF)</strong>. يُعرَف بحركة القوة «الموجية» المميّزة، ومزجه المتوازن بين تقنيات اليد والقدم، وأنماطه الأربعة والعشرين (<em>تول</em>) — نمط لكل ساعة من اليوم.',
                    'history' => [
                        'الجنرال <strong>تشوي هونغ هي</strong>، وهو ضابط في الجيش الكوري كان قد درس الكاراتيه في اليابان، هو الأكثر ارتباطاً بتسمية التايكوون-دو وتنظيمه المبكّر؛ ويُنسب إليه على نطاق واسع الترويج للاسم المعتمد عام 1955. مزج تشوي الركل الكوري بقاعدة تقنية مشتقّة من الكاراتيه ومنظومة من النظرية والأنماط والفلسفة صاغها في السنوات التالية.',
                        'وفي 1966 أسّس تشوي <strong>الاتحاد الدولي للتايكوندو</strong> في سيول لنشر الفن عالمياً. وأدّت التوترات السياسية بين تشوي والمؤسسة الرياضية في كوريا الجنوبية إلى انقسام حاسم في السبعينيات: نما الفرع الرياضي (الاتحاد العالمي) تحت الكوكيون، بينما نقل تشوي الـITF إلى الخارج فجعل مقرّه في كندا ثم فيينا، مؤكّداً منهجاً أقرب للدفاع عن النفس وذا جذور عسكرية.',
                        'وبعد وفاة تشوي عام 2002 انقسم الـITF إلى عدة منظمات لا تزال تتشارك أنماطه وفلسفته لكنها تعمل باستقلال. ويبقى تايكوون-دو الـITF فناً قتالياً تقليدياً يُمارَس عالمياً، متمايزاً عن الفرع الأولمبي (WT) في التقنية وقوانين المنافسة معاً.',
                    ],
                    'focus' => [
                        'تنطلق تقنية الـITF من الحركة «الموجية» — دورة استرخاء–هبوط–صعود للجسم تستخدمها نظرية تشوي لتوليد القوة عبر إنزال الكتلة ورفعها مع كل تقنية. ويمنح الفن تقنيات اليد دوراً أكبر بكثير من الفرع الأولمبي، ويُسمح في قتاله بلكم الرأس بتحكّم.',
                        'يتمحور المنهج حول <strong>الأنماط الأربعة والعشرين (تول)</strong>، ولكلٍّ معنى تاريخي أو فلسفي، إلى جانب القتال بتلامس خفيف، والدفاع عن النفس، واختبارات القوة، و«التقنية الخاصة» (الركلات القفزية العالية). وتُعامَل الآداب والمبادئ والنظرية كأجزاء أصيلة من الفن لا إضافات.',
                    ],
                    'benefits' => [
                        '🥊 <strong>مزيج ضربات أكثر اكتمالاً</strong> من الفرع الأولمبي — اليدان والقدمان حاضرتان، ويُسمح بلكم الرأس في القتال.',
                        '🧩 <strong>أنماط غنية (تول)</strong> تدرّب الذاكرة والاتزان والتناسق ودقة التقنية.',
                        '🧠 <strong>أساس فلسفي قوي</strong> في المبادئ والنظرية يبني الانضباط وضبط النفس.',
                        '🛡️ <strong>توجّه دفاعي</strong> — يُبقي المنهج التطبيق العملي قريباً من السطح.',
                        '💪 <strong>إعداد بدني شامل</strong> من الأنماط وعمل القوة والقتال.',
                        '🤝 <strong>آداب ومجتمع</strong> تجعله ملائماً للعائلات وكل الأعمار.',
                    ],
                    'limitations' => [
                        '🥇 <strong>ليس ضمن البرنامج الأولمبي</strong> — الفرع الأولمبي هو WT، فمسارات المنافسة مختلفة.',
                        '🤼 <strong>مصارعة وعمل أرضي محدودان</strong>، كحال معظم فنون الضرب.',
                        '🌐 <strong>تشظٍّ تنظيمي</strong> — توجد عدة هيئات ITF بعد انقسامات ما بعد 2002، فتتفاوت المعايير والقوانين بينها.',
                        '🥋 <strong>طريقة الحركة الموجية محلّ نقاش</strong> بين ممارسي الفنون القتالية من حيث كفاءتها القتالية.',
                    ],
                    'rules' => [
                        '🥋 القتال عادةً بتلامس خفيف مستمر ضمن جولات مؤقتة مع التأكيد على التحكّم.',
                        '🎯 تُمنح النقاط للضربات المُحكمة باليد والقدم على الأهداف المسموحة؛ وخلافاً لـWT <strong>يُسمح بلكم الرأس</strong>.',
                        '🧩 يشمل التقييم والمنافسة <strong>الأنماط (تول)</strong> والقتال واختبار القوة والتقنية الخاصة.',
                        '🚫 يُعاقَب على التلامس المفرط وضرب المناطق الممنوعة والسلوك غير الرياضي.',
                        '🎽 تتدرّج الرتب عبر الأحزمة الملوّنة (<em>غوب</em>) حتى الحزام الأسود (<em>دان</em>) بشهادة منظمة الـITF التابع لها الممارس.',
                        '🏆 تنظّم الفعاليات اتحادات الـITF المختلفة لا هيئة أولمبية واحدة.',
                    ],
                ],
                'links' => [
                    ['label' => 'International Taekwon-Do Federation (ITF)', 'url' => 'https://www.itf-tkd.org/'],
                    ['label' => 'Britannica — Taekwondo', 'url' => 'https://www.britannica.com/sports/tae-kwon-do'],
                    ['label' => 'Wikipedia — International Taekwon-Do Federation', 'url' => 'https://en.wikipedia.org/wiki/International_Taekwon-Do_Federation'],
                    ['label' => 'Wikipedia — Choi Hong Hi', 'url' => 'https://en.wikipedia.org/wiki/Choi_Hong_Hi'],
                    ['label' => 'Wikipedia — Taekwondo', 'url' => 'https://en.wikipedia.org/wiki/Taekwondo'],
                ],
                'image_prompt' => "A cinematic, ultra-detailed hero poster in dramatic dark fantasy game-art style, 16:9 widescreen. Two ITF Taekwon-Do warriors — a muscular male fighter and a fierce female fighter — caught mid-action mid a flying reverse turning kick with a simultaneous knife-hand guard, wearing traditional ITF doboks with diagonal-cut open jackets and the ITF tree-and-globe emblem, black-and-gold trim, with glowing gold chi energy trailing their sine-wave motion. Behind them looms a massive translucent Korean mountain-spirit and phoenix amid stormy skies. The background shows a misty Korean mountain temple with ITF banners and hanja calligraphy, autumn leaves and embers in the wind. Bold stylized 3D calligraphic title text reading 'TAEKWONDO — ITF' dominates the top, with an evocative subtitle beneath. Moody chiaroscuro lighting, rim-lit silhouettes, black-gold-crimson palette, hyper-detailed digital painting, 4K premium game cover art quality.",
            ],

            /* ============ KARATE — SHOTOKAN ============ */
            [
                'slug' => 'karate-shotokan',
                'name' => 'Karate (Shotokan)',
                'name_ar' => 'الكاراتيه (شوتوكان)',
                'icon' => 'bi-person-arms-up',
                'replaces' => 'karate',
                'en' => [
                    'intro' => '🥋 The most widely practised style of karate in the world, founded through the teaching of <strong>Gichin Funakoshi</strong>. Shotokan is defined by long, deep, powerful stances, strong linear techniques, and the pursuit of the decisive single strike — <em>ikken hissatsu</em>, "to finish with one blow."',
                    'history' => [
                        'Karate developed on the island of <strong>Okinawa</strong>, where an indigenous fighting method known simply as <em>te</em> ("hand") blended over centuries with Chinese martial arts brought through trade with the Fujian region. By the 19th century distinct Okinawan traditions such as Shuri-te and Naha-te had formed.',
                        '<strong>Gichin Funakoshi</strong> (1868–1957), an Okinawan schoolteacher and student of masters Anko Itosu and Anko Asato, is regarded as the father of modern karate. He gave landmark demonstrations in mainland Japan in 1922 and stayed to teach, adapting the art for the Japanese education system and changing the writing of "karate" to mean "empty hand." His students named his school <strong>Shotokan</strong> after his pen-name "Shoto" (meaning "pine waves").',
                        'In 1949 Funakoshi\'s followers formed the <strong>Japan Karate Association (JKA)</strong>, which produced a generation of influential instructors who spread Shotokan across the globe in the second half of the 20th century, making it the most numerous karate style today. Karate as a whole made its Olympic debut at Tokyo 2020.',
                    ],
                    'focus' => [
                        'Shotokan emphasises strong, deep stances such as <em>zenkutsu-dachi</em> (front stance) and <em>kiba-dachi</em> (horse stance), from which powerful, direct punches, blocks and kicks are delivered. Long stances build stability, leg strength and the ability to drive body mass into a technique.',
                        'Training rests on three pillars: <strong>kihon</strong> (basics — repeated fundamental techniques), <strong>kata</strong> (formal patterns encoding the style\'s techniques and principles) and <strong>kumite</strong> (sparring, from pre-arranged to free). The style prizes precision, timing, distance and decisive power over flurries of contact.',
                    ],
                    'benefits' => [
                        '💥 <strong>Explosive power and stability</strong> generated from deep stances and hip rotation.',
                        '🧠 <strong>Kata training</strong> sharpens memory, balance, body awareness and concentration.',
                        '🧘 <strong>Discipline and respect</strong> — the etiquette-rich dojo culture cultivates a calm, focused mind and strong character.',
                        '🛡️ <strong>Practical fundamentals</strong> of blocking, distancing and counter-striking.',
                        '💪 <strong>Whole-body strength and coordination</strong> suitable for children and adults.',
                        '🏆 <strong>A clear grading and competition pathway</strong>, including Olympic sport karate.',
                    ],
                    'limitations' => [
                        '🤼 <strong>Minimal grappling or ground fighting</strong>, so it needs complementary training for a complete self-defence game.',
                        '🧍 <strong>Deep, long stances</strong> are excellent for developing power but are less mobile than the guards used in sport fighting.',
                        '🥊 <strong>Traditional control emphasis</strong> means less full-contact sparring than styles like Kyokushin.',
                        '🎛️ <strong>Sport-kumite rules</strong> (controlled contact) can differ from the demands of real confrontation.',
                    ],
                    'rules' => [
                        '🥋 Competition has two disciplines: <strong>kata</strong> (judged solo forms) and <strong>kumite</strong> (sparring).',
                        '🎯 Kumite scoring: <em>yuko</em> (1 point) for a punch, <em>waza-ari</em> (2) for a kick to the body, and <em>ippon</em> (3) for a head kick or a takedown followed by a scoring technique.',
                        '🎛️ Contact is controlled — techniques must be well-formed and controlled; excessive contact is penalised.',
                        '🥇 Bouts are won by an 8-point lead, the most points at time, or by the <em>senshu</em> (first unopposed point) advantage.',
                        '🚫 Dangerous techniques, attacks to prohibited areas, exits from the area and passivity draw penalties.',
                        '🎽 Ranks progress through <em>kyu</em> (coloured belts) to <em>dan</em> (black belt) grades.',
                        '🏅 Kata is scored on stances, technique, power, timing and overall performance of recognised patterns.',
                    ],
                ],
                'ar' => [
                    'intro' => '🥋 أكثر أساليب الكاراتيه انتشاراً في العالم، تأسّس عبر تعليم <strong>غيتشين فوناكوشي</strong>. ويتميّز الشوتوكان بوقفاته الطويلة العميقة القوية، وتقنياته الخطية المتينة، والسعي للضربة الحاسمة الواحدة — <em>إيكِن هيساتسو</em>، «الإنهاء بضربة واحدة».',
                    'history' => [
                        'نشأ الكاراتيه في جزيرة <strong>أوكيناوا</strong>، حيث امتزج فنّ قتالي محلي يُعرف ببساطة بـ<em>تي</em> («يد») على مدى قرون بالفنون القتالية الصينية القادمة عبر التجارة مع إقليم فوجيان. وبحلول القرن التاسع عشر تشكّلت تقاليد أوكيناوية متمايزة مثل شوري-تي وناها-تي.',
                        'ويُعدّ <strong>غيتشين فوناكوشي</strong> (1868–1957)، وهو معلّم مدرسة أوكيناوي وتلميذ المعلّمَين آنكو إيتوسو وآنكو آساتو، أبا الكاراتيه الحديث. قدّم عروضاً بارزة في اليابان الرئيسة عام 1922 وبقي ليعلّم، مكيّفاً الفن لنظام التعليم الياباني ومغيّراً كتابة «كاراتيه» لتعني «اليد الخالية». وسمّى تلاميذه مدرسته <strong>شوتوكان</strong> نسبةً إلى اسمه الأدبي «شوتو» (أي «أمواج الصنوبر»).',
                        'وفي 1949 أسّس أتباع فوناكوشي <strong>رابطة الكاراتيه اليابانية (JKA)</strong> التي أنجبت جيلاً من المدرّبين المؤثّرين نشروا الشوتوكان حول العالم في النصف الثاني من القرن العشرين، فصار أكثر أساليب الكاراتيه عدداً اليوم. وظهر الكاراتيه ككل أولمبياً لأول مرة في طوكيو 2020.',
                    ],
                    'focus' => [
                        'يؤكّد الشوتوكان على الوقفات القوية العميقة مثل <em>زِنكوتسو-داتشي</em> (الوقفة الأمامية) و<em>كيبا-داتشي</em> (وقفة الحصان)، ومنها تُطلَق لكمات وصدّات وركلات مباشرة قوية. وتبني الوقفات الطويلة الثبات وقوة الساقين والقدرة على دفع كتلة الجسم في التقنية.',
                        'يقوم التدريب على ثلاثة أركان: <strong>الكيهون</strong> (الأساسيات — تكرار التقنيات الجوهرية) و<strong>الكاتا</strong> (الأنماط الرسمية التي تُشفّر تقنيات الأسلوب ومبادئه) و<strong>الكوميتيه</strong> (القتال، من المُرتَّب مسبقاً إلى الحرّ). ويقدّر الأسلوب الدقة والتوقيت والمسافة والقوة الحاسمة على وابل التلامس.',
                    ],
                    'benefits' => [
                        '💥 <strong>قوة انفجارية وثبات</strong> يتولّدان من الوقفات العميقة ودوران الورك.',
                        '🧠 <strong>تدريب الكاتا</strong> يشحذ الذاكرة والاتزان والوعي الجسدي والتركيز.',
                        '🧘 <strong>انضباط واحترام</strong> — تغرس ثقافة الدوجو الغنية بالآداب ذهناً هادئاً مركّزاً وشخصية قوية.',
                        '🛡️ <strong>أساسيات عملية</strong> في الصدّ وإدارة المسافة والضرب المضاد.',
                        '💪 <strong>قوة وتناسق للجسم كله</strong> يناسبان الأطفال والكبار.',
                        '🏆 <strong>مسار واضح للتقييم والمنافسة</strong>، بما فيه الكاراتيه الأولمبي.',
                    ],
                    'limitations' => [
                        '🤼 <strong>مصارعة وقتال أرضي محدودان</strong>، فيحتاج تدريباً مكمّلاً للعبة دفاع متكاملة.',
                        '🧍 <strong>الوقفات العميقة الطويلة</strong> ممتازة لتطوير القوة لكنها أقل رشاقة من وضعيات القتال الرياضي.',
                        '🥊 <strong>التركيز التقليدي على التحكّم</strong> يعني قتالاً أقل بتلامس كامل من أساليب مثل الكيوكوشين.',
                        '🎛️ <strong>قوانين الكوميتيه الرياضي</strong> (التلامس المُتحكَّم) قد تختلف عن متطلّبات المواجهة الحقيقية.',
                    ],
                    'rules' => [
                        '🥋 للمنافسة فرعان: <strong>الكاتا</strong> (أنماط فردية تُقيَّم) و<strong>الكوميتيه</strong> (القتال).',
                        '🎯 احتساب الكوميتيه: <em>يوكو</em> (نقطة) للكمة، و<em>وازا-آري</em> (نقطتان) لركلة الجسم، و<em>إيبون</em> (3) لركلة الرأس أو إسقاط يتبعه تقنية مُسجِّلة.',
                        '🎛️ التلامس مُتحكَّم فيه — يجب أن تكون التقنيات مُحكمة ومضبوطة؛ ويُعاقَب التلامس المفرط.',
                        '🥇 يُحسم النزال بفارق 8 نقاط، أو بأكثر النقاط عند انتهاء الوقت، أو بأفضلية <em>السِنشو</em> (أول نقطة دون ردّ).',
                        '🚫 تُعاقَب التقنيات الخطرة وضرب المناطق الممنوعة والخروج من الساحة والسلبية.',
                        '🎽 تتدرّج الرتب عبر <em>كيو</em> (الأحزمة الملوّنة) إلى درجات <em>دان</em> (الحزام الأسود).',
                        '🏅 تُقيَّم الكاتا على الوقفات والتقنية والقوة والتوقيت والأداء العام للأنماط المعتمدة.',
                    ],
                ],
                'links' => [
                    ['label' => 'World Karate Federation (WKF)', 'url' => 'https://www.wkf.net/'],
                    ['label' => 'Japan Karate Association (JKA)', 'url' => 'https://www.jka.or.jp/en/'],
                    ['label' => 'Olympics — Karate', 'url' => 'https://www.olympics.com/en/sports/karate/'],
                    ['label' => 'Britannica — Karate', 'url' => 'https://www.britannica.com/sports/karate'],
                    ['label' => 'Wikipedia — Shotokan', 'url' => 'https://en.wikipedia.org/wiki/Shotokan'],
                ],
                'image_prompt' => "A cinematic, ultra-detailed hero poster in dramatic dark fantasy game-art style, 16:9 widescreen. Two Shotokan Karate warriors — a muscular male karateka and a fierce female karateka — frozen mid-action in a deep front stance delivering a decisive reverse punch (gyaku-zuki) and a high front kick, wearing crisp white heavyweight gi with black belts and a subtle tiger (Shoto) emblem, with glowing white-and-crimson force lines snapping around the finishing blow. Behind them looms a massive translucent tiger spirit — the Shotokan tiger — amid stormy skies. The background reveals an Okinawan castle and Japanese torii gate with kanji banners, cherry-blossom petals and dust swirling in the wind. Bold stylized 3D calligraphic title text reading 'KARATE — SHOTOKAN' dominates the top in brush-stroke lettering, with an evocative subtitle beneath. Moody chiaroscuro lighting, rim-lit silhouettes, white-black-crimson palette, hyper-detailed digital painting, 4K premium game cover art quality.",
            ],

            /* ============ JUDO ============ */
            [
                'slug' => 'judo',
                'name' => 'Judo',
                'name_ar' => 'الجودو',
                'icon' => 'bi-person-arms-up',
                'en' => [
                    'intro' => '🥋 The Olympic grappling art known as "the gentle way," founded by <strong>Jigoro Kano</strong>. Judo turns an opponent\'s force and balance against them through throws, pins, chokes and joint locks — maximum effect from minimum effort.',
                    'history' => [
                        '<strong>Jigoro Kano</strong> (1860–1938), a Japanese educator, studied several schools of the older battlefield art of <em>jujutsu</em>. Concerned that jujutsu was declining and that some techniques were too dangerous for safe practice, he refined and reorganised it into a coherent, principled system he called <strong>Judo</strong>, founding the <strong>Kodokan</strong> in Tokyo in 1882.',
                        'Kano built his art on two guiding principles: <em>seiryoku zen\'yo</em> (maximum efficiency, minimum effort) and <em>jita kyoei</em> (mutual welfare and benefit). By removing the most dangerous techniques and introducing <em>randori</em> (free practice) and safe break-falling, he made full-resistance sparring possible — a revolution that let judo be trained hard yet safely.',
                        'Judo spread worldwide through the 20th century, entered the Olympic Games for men at Tokyo 1964 and for women at Barcelona 1992, and became one of the most practised sports on earth, governed by the <strong>International Judo Federation (IJF)</strong>.',
                    ],
                    'focus' => [
                        'The heart of judo is off-balancing the opponent (<em>kuzushi</em>) and then executing an explosive throw (<em>nage-waza</em>) — hip throws, shoulder throws, foot sweeps and sacrifice throws. Grip fighting (<em>kumi-kata</em>) is the chess match that sets up the throw.',
                        'On the ground (<em>ne-waza</em>), judoka use pins (<em>osaekomi</em>), strangles (<em>shime-waza</em>) and arm-locks (<em>kansetsu-waza</em>) to finish. The art rewards timing, balance, leverage and technique over brute strength — a smaller, skilled player can throw a larger one.',
                    ],
                    'benefits' => [
                        '🤸 <strong>Superb balance, coordination and safe falling</strong> (<em>ukemi</em>) — a life-long skill that helps prevent injury in daily life.',
                        '💪 <strong>Full-body functional strength and grip power</strong> from constant gripping, throwing and grappling.',
                        '🛡️ <strong>Highly practical self-defence</strong> — controlling, throwing and pinning an opponent without necessarily striking.',
                        '🧠 <strong>Respect, humility and composure</strong> under pressure, central to judo\'s educational philosophy.',
                        '❤️ <strong>Excellent conditioning</strong> — randori is intensely aerobic and anaerobic.',
                        '🌍 <strong>A structured belt system and clear path</strong> from club to Olympic competition.',
                    ],
                    'limitations' => [
                        '👊 <strong>No striking</strong> — judo must be paired with other arts for stand-up self-defence against punches/kicks.',
                        '🤕 <strong>High-impact throws</strong> require good falling technique and proper mats; injuries can occur without them.',
                        '📏 <strong>Sport rules restrict leg grabs</strong> and some classic techniques, narrowing the competitive toolkit versus older jujutsu.',
                        '⏳ <strong>Throws take time to master safely</strong>, and hard randori is physically demanding.',
                    ],
                    'rules' => [
                        '🏆 The cleanest win is a full <strong>ippon</strong>: a throw landing the opponent largely on their back with control, a 20-second pin, or a submission by choke or arm-lock.',
                        '🥋 <em>Waza-ari</em> is a lesser score; two waza-ari now also equal an ippon and end the contest.',
                        '⏱️ Senior contests run 4 minutes, with unlimited "golden score" sudden-death overtime if tied.',
                        '🚫 No striking, no direct leg-grab takedowns, and the only permitted joint locks are to the elbow.',
                        '⚖️ Penalties (<em>shido</em>) are given for passivity, false attacks and rule breaches; three shido result in disqualification (<em>hansoku-make</em>).',
                        '🥋 Contests are fought in a <em>judogi</em> (jacket, trousers and belt); grips on the gi are central to the game.',
                        '🎽 Ranks run from white through coloured belts to black belt (<em>dan</em>), then the red-and-white and red belts of the highest masters.',
                    ],
                ],
                'ar' => [
                    'intro' => '🥋 فن المصارعة الأولمبي المعروف بـ«الطريق اللطيف»، أسّسه <strong>جيغورو كانو</strong>. يحوّل الجودو قوة الخصم واتزانه ضده عبر الرميات والتثبيتات والخنق وأقفال المفاصل — أقصى أثر بأقل جهد.',
                    'history' => [
                        'درس <strong>جيغورو كانو</strong> (1860–1938)، وهو تربوي ياباني، عدة مدارس من فن ساحات المعارك القديم <em>الجوجيتسو</em>. وإذ قلقه تراجع الجوجيتسو وخطورة بعض تقنياته على التدريب الآمن، هذّبه وأعاد تنظيمه في نظام متماسك قائم على المبدأ سمّاه <strong>الجودو</strong>، مؤسِّساً <strong>الكودوكان</strong> في طوكيو عام 1882.',
                        'بنى كانو فنّه على مبدأين موجِّهين: <em>سيريوكو زِنيو</em> (أقصى كفاءة بأقل جهد) و<em>جيتا كيويه</em> (المنفعة والرفاه المتبادلان). وبإزالة أخطر التقنيات وإدخال <em>الراندوري</em> (التدريب الحرّ) والسقوط الآمن، جعل القتال بمقاومة كاملة ممكناً — ثورةٌ أتاحت تدريب الجودو بقوة وأمان معاً.',
                        'انتشر الجودو عالمياً عبر القرن العشرين، ودخل الألعاب الأولمبية للرجال في طوكيو 1964 وللنساء في برشلونة 1992، وصار من أكثر الرياضات مُمارسةً على الأرض، ويشرف عليه <strong>الاتحاد الدولي للجودو (IJF)</strong>.',
                    ],
                    'focus' => [
                        'جوهر الجودو كسر اتزان الخصم (<em>كوزوشي</em>) ثم تنفيذ رمية انفجارية (<em>ناغيه-وازا</em>) — رميات الورك والكتف وكسحات القدم والرميات التضحوية. وصراع القبضات (<em>كومي-كاتا</em>) هو مباراة الشطرنج التي تهيّئ للرمية.',
                        'وعلى الأرض (<em>نيه-وازا</em>)، يستخدم الجودوكا التثبيت (<em>أوساي-كومي</em>) والخنق (<em>شيميه-وازا</em>) وأقفال الذراع (<em>كانسيتسو-وازا</em>) للإنهاء. ويكافئ الفن التوقيت والاتزان والرافعة والتقنية على القوة الغاشمة — فاللاعب الأصغر الماهر قد يرمي الأكبر.',
                    ],
                    'benefits' => [
                        '🤸 <strong>اتزان وتناسق وسقوط آمن ممتاز</strong> (<em>أوكيمي</em>) — مهارة تدوم مدى الحياة وتساعد على تفادي الإصابة في الحياة اليومية.',
                        '💪 <strong>قوة وظيفية للجسم كله وقوة قبضة</strong> من الإمساك والرمي والمصارعة المستمرة.',
                        '🛡️ <strong>دفاع عن النفس عملي جداً</strong> — تحكّم بالخصم ورميه وتثبيته دون الحاجة إلى الضرب.',
                        '🧠 <strong>احترام وتواضع ورباطة جأش</strong> تحت الضغط، وهي محور فلسفة الجودو التربوية.',
                        '❤️ <strong>إعداد بدني ممتاز</strong> — الراندوري مكثّف هوائياً ولاهوائياً.',
                        '🌍 <strong>نظام أحزمة منظّم ومسار واضح</strong> من النادي إلى المنافسة الأولمبية.',
                    ],
                    'limitations' => [
                        '👊 <strong>لا ضرب</strong> — يلزم دمج الجودو بفنون أخرى للدفاع واقفاً أمام اللكم/الركل.',
                        '🤕 <strong>الرميات عالية التأثير</strong> تتطلب تقنية سقوط جيدة وبُسُطاً مناسبة؛ وقد تقع إصابات دونها.',
                        '📏 <strong>تقيّد القوانين الإمساك بالساقين</strong> وبعض التقنيات الكلاسيكية، ما يضيّق الترسانة التنافسية مقارنةً بالجوجيتسو القديم.',
                        '⏳ <strong>الرميات تحتاج وقتاً لإتقانها بأمان</strong>، والراندوري الشاق مُتعِب بدنياً.',
                    ],
                    'rules' => [
                        '🏆 أنظف فوز هو <strong>الإيبون</strong> الكامل: رمية تُوقِع الخصم على ظهره غالباً مع تحكّم، أو تثبيت 20 ثانية، أو استسلام بالخنق أو قفل الذراع.',
                        '🥋 <em>وازا-آري</em> نقطة أقل؛ وقد صار وازا-آري مرتان يعادلان إيبون وينهيان النزال.',
                        '⏱️ نزالات الكبار 4 دقائق، مع وقت إضافي حاسم «النتيجة الذهبية» دون حدّ عند التعادل.',
                        '🚫 لا ضرب، ولا إسقاط بالإمساك المباشر بالساقين، والأقفال المسموحة للمرفق فقط.',
                        '⚖️ تُمنح العقوبات (<em>شيدو</em>) على السلبية والهجمات الكاذبة ومخالفات القوانين؛ وثلاث شيدو تعني الإقصاء (<em>هانسوكو-ماكيه</em>).',
                        '🥋 يُخاض النزال ببدلة <em>الجودوغي</em> (سترة وسروال وحزام)؛ والقبض على البدلة محوريّ في اللعبة.',
                        '🎽 تتدرّج الرتب من الأبيض عبر الأحزمة الملوّنة إلى الأسود (<em>دان</em>)، ثم حزامَي الأحمر-الأبيض والأحمر لكبار المعلّمين.',
                    ],
                ],
                'links' => [
                    ['label' => 'International Judo Federation (IJF)', 'url' => 'https://www.ijf.org/'],
                    ['label' => 'Kodokan Judo Institute', 'url' => 'https://kodokanjudoinstitute.org/en/'],
                    ['label' => 'Olympics — Judo', 'url' => 'https://www.olympics.com/en/sports/judo/'],
                    ['label' => 'Britannica — Judo', 'url' => 'https://www.britannica.com/sports/judo'],
                    ['label' => 'Wikipedia — Judo', 'url' => 'https://en.wikipedia.org/wiki/Judo'],
                ],
                'image_prompt' => "A cinematic, ultra-detailed hero poster in dramatic dark fantasy game-art style, 16:9 widescreen. Two Judo warriors — a muscular male judoka and a fierce female judoka — frozen at the apex of a perfect seoi-nage shoulder throw, one lifting the other overhead, wearing heavyweight white and blue judogi with worn black belts and IJF-style emblems, glowing white momentum-arcs and force spirals swirling around the throw. Behind them looms a massive translucent crane and dragon spirit symbolising flow and balance amid stormy skies. The background reveals the Kodokan and a Tokyo skyline with Japanese banners, cherry-blossom petals and sparks in the wind. Bold stylized 3D calligraphic title text reading 'JUDO' dominates the top in elegant brush lettering, with an evocative subtitle beneath. Moody chiaroscuro lighting, rim-lit silhouettes, white-indigo-silver palette, hyper-detailed digital painting, 4K premium game cover art quality.",
            ],

            /* ============ BOXING ============ */
            [
                'slug' => 'boxing',
                'name' => 'Boxing',
                'name_ar' => 'الملاكمة',
                'icon' => 'bi-person-arms-up',
                'en' => [
                    'intro' => '🥊 "The sweet science" — the pure art of punching, footwork and defence with the fists. One of the oldest and most refined combat sports, boxing is a cornerstone of the Olympic Games and the foundation of hand striking for countless other disciplines.',
                    'history' => [
                        'Fist-fighting is ancient: depictions survive from Egypt and Mesopotamia, and boxing was a formal event at the ancient Greek Olympics from 688 BC, where fighters bound their hands with leather thongs. The sport faded with antiquity and re-emerged in 17th–18th century Britain as bare-knuckle prizefighting.',
                        'Modern boxing was shaped by successive codes of rules. The London Prize Ring Rules brought early structure, but it was the <strong>Marquess of Queensberry Rules</strong> of 1867 — mandating padded gloves, three-minute rounds, the ten-second count and a ban on wrestling — that created the sport we know today.',
                        'Boxing has featured at every modern Olympics since 1904 (with women\'s boxing added at London 2012) and developed a vast professional world of weight divisions and world titles. It remains one of the most globally popular and culturally significant sports.',
                    ],
                    'focus' => [
                        'Boxing is built on four core punches — the jab, cross, hook and uppercut — combined into combinations and delivered behind footwork, head movement and distance management. Defence is as important as offence: slipping, rolling, blocking and parrying.',
                        'Success rewards timing, conditioning, "ring IQ" and precise, powerful hands. Because the toolkit is deliberately narrow, mastery lies in the subtlety of angles, feints, rhythm and defensive craft rather than in variety of techniques.',
                    ],
                    'benefits' => [
                        '❤️ <strong>Elite cardiovascular fitness</strong> and one of the most effective full-body, fat-burning workouts available.',
                        '⚡ <strong>Sharp reflexes, hand speed, coordination and timing</strong> developed through pad-work, bag-work and sparring.',
                        '🧠 <strong>Focus, stress relief and confidence</strong> — boxing training is widely used for mental well-being.',
                        '🛡️ <strong>Highly effective stand-up self-defence</strong> with the hands, plus the footwork to create and close distance.',
                        '💪 <strong>Core and lower-body power</strong>, since real punching power comes from the legs and hips.',
                        '🏆 <strong>A clear amateur-to-Olympic-to-professional pathway.</strong>',
                    ],
                    'limitations' => [
                        '🦵 <strong>Hands only</strong> — no kicks, knees, clinch striking or grappling, so it is one dimension of a complete fighting game.',
                        '🤕 <strong>Repeated head impacts carry concussion and long-term brain-health risk</strong>; sparring volume must be managed carefully.',
                        '🤼 <strong>No answer to takedowns or ground fighting</strong> on its own.',
                        '👀 <strong>Vulnerability to low-line attacks</strong> (leg kicks) in a mixed context, since the sport never trains against them.',
                    ],
                    'rules' => [
                        '🥊 Fought with padded gloves over timed rounds — three minutes in the professional game, and three three-minute rounds for elite amateurs.',
                        '🎯 Legal targets are the front and sides of the head and the body above the belt line.',
                        '🏆 A bout ends by knockout (KO), technical knockout (TKO/referee stoppage), disqualification, or the judges\' scorecards.',
                        '📊 Judges use the <strong>10-point must system</strong>, awarding the round winner 10 and the loser 9 or fewer.',
                        '🚫 No hitting below the belt, behind the head (rabbit punches), holding, or striking a downed or rising opponent.',
                        '⏳ A fighter knocked down has until the referee\'s count of ten to rise; three knockdowns in a round can end the bout under some rules.',
                        '⚖️ Fighters compete within strict weight divisions to keep contests fair.',
                    ],
                ],
                'ar' => [
                    'intro' => '🥊 «العلم اللطيف» — فن اللكم وحركة القدمين والدفاع بالقبضتين. من أقدم الرياضات القتالية وأكثرها صقلاً، والملاكمة ركن في الألعاب الأولمبية وأساس الضرب اليدوي لعدد لا يُحصى من الفنون الأخرى.',
                    'history' => [
                        'القتال بالقبضات قديم: بقيت تصاويره من مصر وبلاد الرافدين، وكانت الملاكمة فعالية رسمية في أولمبياد اليونان القديمة منذ 688 ق.م حيث كان المقاتلون يلفّون أيديهم بسيور جلدية. ثم خبت مع العصور القديمة وعادت في بريطانيا في القرنين السابع عشر والثامن عشر مصارعةً بالقبضة العارية على الجوائز.',
                        'وتشكّلت الملاكمة الحديثة عبر مدوّنات قوانين متعاقبة. فقد جلبت «قوانين حلبة لندن للجوائز» بنيةً مبكّرة، لكن <strong>قوانين ماركيز كوينزبري</strong> عام 1867 — التي فرضت القفازات المبطّنة وجولات الثلاث دقائق والعدّ عشر ثوانٍ ومنعت المصارعة — هي التي أنشأت الرياضة التي نعرفها اليوم.',
                        'ظهرت الملاكمة في كل أولمبياد حديث منذ 1904 (مع إضافة ملاكمة السيدات في لندن 2012)، وطوّرت عالماً احترافياً واسعاً من فئات الوزن وألقاب العالم. وتبقى من أكثر الرياضات شعبيةً وأهميةً ثقافياً في العالم.',
                    ],
                    'focus' => [
                        'تقوم الملاكمة على أربع لكمات جوهرية — الجاب والمستقيمة والهوك والأبركت — تُدمَج في توليفات وتُطلَق خلف حركة القدمين وحركة الرأس وإدارة المسافة. والدفاع بأهمية الهجوم: التزحلق والدوران والصدّ والإبعاد.',
                        'ويكافئ النجاح التوقيت والإعداد و«ذكاء الحلبة» واليدين الدقيقتين القويتين. ولأن الترسانة ضيّقة عمداً، فالإتقان في دقّة الزوايا والخداع والإيقاع والحرفة الدفاعية لا في تنوّع التقنيات.',
                    ],
                    'benefits' => [
                        '❤️ <strong>لياقة قلبية رفيعة</strong> ومن أكثر تمارين الجسم كله فعاليةً في حرق الدهون.',
                        '⚡ <strong>ردود فعل حادة وسرعة يد وتناسق وتوقيت</strong> تتطوّر عبر عمل المخدّات والأكياس والقتال.',
                        '🧠 <strong>تركيز وتفريغ للتوتر وثقة</strong> — يُستخدَم تدريب الملاكمة كثيراً للصحة النفسية.',
                        '🛡️ <strong>دفاع عن النفس فعّال جداً واقفاً</strong> باليدين، مع حركة قدمين لصنع المسافة وإغلاقها.',
                        '💪 <strong>قوة للجذع والجزء السفلي</strong>، فقوة اللكم الحقيقية تأتي من الساقين والوركين.',
                        '🏆 <strong>مسار واضح من الهواة إلى الأولمبياد إلى الاحتراف.</strong>',
                    ],
                    'limitations' => [
                        '🦵 <strong>اليدان فقط</strong> — لا ركل ولا ركب ولا ضرب في الاشتباك ولا مصارعة، فهي بُعد واحد من لعبة قتال متكاملة.',
                        '🤕 <strong>تكرار ضربات الرأس يحمل خطر الارتجاج وأثراً بعيد المدى على صحة الدماغ</strong>؛ ويجب إدارة حجم القتال التدريبي بعناية.',
                        '🤼 <strong>لا حلّ للإسقاط أو القتال الأرضي</strong> بمفردها.',
                        '👀 <strong>ضعف أمام هجمات الخط المنخفض</strong> (ركلات الساق) في سياق مختلط، إذ لا تتدرّب الرياضة عليها إطلاقاً.',
                    ],
                    'rules' => [
                        '🥊 يُخاض بقفازات مبطّنة عبر جولات مؤقتة — ثلاث دقائق في الاحتراف، وثلاث جولات مدة كل منها ثلاث دقائق لنخبة الهواة.',
                        '🎯 الأهداف المسموحة مقدمة الرأس وجانباه والجسم فوق خط الحزام.',
                        '🏆 ينتهي النزال بالضربة القاضية (KO) أو القاضية الفنية (إيقاف الحكم) أو الاستبعاد أو ببطاقات الحكام.',
                        '📊 يستخدم الحكّام <strong>نظام العشر نقاط الإلزامي</strong>، فيمنحون الفائز بالجولة 10 والخاسر 9 أو أقل.',
                        '🚫 يُمنع الضرب تحت الحزام أو خلف الرأس أو الإمساك أو ضرب خصم ساقط أو ناهض.',
                        '⏳ للملاكم الساقط حتى عدّ الحكم عشرة لينهض؛ وثلاث سقطات في جولة قد تنهي النزال في بعض القوانين.',
                        '⚖️ يتنافس الملاكمون ضمن فئات وزن صارمة لإبقاء النزالات عادلة.',
                    ],
                ],
                'links' => [
                    ['label' => 'World Boxing', 'url' => 'https://www.worldboxing.org/'],
                    ['label' => 'Olympics — Boxing', 'url' => 'https://www.olympics.com/en/sports/boxing/'],
                    ['label' => 'Britannica — Boxing', 'url' => 'https://www.britannica.com/sports/boxing'],
                    ['label' => 'Wikipedia — Boxing', 'url' => 'https://en.wikipedia.org/wiki/Boxing'],
                    ['label' => 'Wikipedia — Marquess of Queensberry Rules', 'url' => 'https://en.wikipedia.org/wiki/Marquess_of_Queensberry_Rules'],
                ],
                'image_prompt' => "A cinematic, ultra-detailed hero poster in dramatic dark fantasy game-art style, 16:9 widescreen. Two boxers — a muscular male boxer and a fierce female boxer — caught mid-action landing a perfect cross and slipping a counter, wearing glossy competition gloves, hand-wraps and satin trunks with subtle championship-belt emblems, sweat spraying, glowing white-gold impact shockwaves bursting from the punch. Behind them looms a massive translucent roaring lion spirit symbolising heart and courage amid a stormy, spotlight-pierced arena sky. The background reveals a packed classic boxing arena with hanging ring lights, championship banners, and confetti and sparks in the wind. Bold stylized 3D title text reading 'BOXING' dominates the top in bold metallic lettering, with an evocative subtitle beneath. Moody chiaroscuro lighting, rim-lit silhouettes, crimson-gold-black palette, hyper-detailed digital painting, 4K premium game cover art quality.",
            ],

            /* ============ MUAY THAI ============ */
            [
                'slug' => 'muay-thai',
                'name' => 'Muay Thai',
                'name_ar' => 'المواي تاي',
                'icon' => 'bi-person-arms-up',
                'en' => [
                    'intro' => '🥊 "The Art of Eight Limbs" — Thailand\'s national martial art, which weaponises fists, elbows, knees and shins, together with a dominant standing clinch. It is one of the most effective and respected stand-up striking arts in the world and a foundational base for modern MMA.',
                    'history' => [
                        'Muay Thai descends from <em>Muay Boran</em> ("ancient boxing"), the battlefield and self-defence arts of the Thai people, practised for centuries and tied to Thai military history and folklore. Bouts were historically fought with hemp-bound fists at festivals and before royalty.',
                        'In the early 20th century the art was modernised: rings, timed rounds, weight classes and padded gloves were adopted, and it took the form recognised today. Despite modernisation it retained deep ritual — the <em>Wai Kru Ram Muay</em> pre-fight dance honouring teacher and heritage, and the sacred <em>Mongkol</em> headband and <em>prajioud</em> armbands.',
                        'From the late 20th century Muay Thai spread globally as both a sport and a fitness pursuit, and its clinch and devastating kicks made it a core striking component of mixed martial arts. It is now overseen internationally by bodies such as the <strong>IFMA</strong>, and has gained Olympic recognition.',
                    ],
                    'focus' => [
                        'Muay Thai uses eight points of contact — punches, elbows, knees and kicks — far more than boxing or taekwondo. Its signature weapons are the powerful roundhouse shin kick and the knee, and it is unique among strikers for its dominant standing clinch, used to control the opponent and land knees and off-balancing sweeps.',
                        'The style prizes toughness, timing, relentless forward pressure and shin/body conditioning. Scoring traditionally favours visibly damaging, balanced, dominant technique over volume, rewarding fighters who stay composed and control the ring.',
                    ],
                    'benefits' => [
                        '🔥 <strong>Brutal full-body conditioning</strong> and outstanding cardiovascular power.',
                        '🦵 <strong>Devastating, complete stand-up striking</strong> — kicks, knees, elbows and punches.',
                        '🛡️ <strong>Highly practical self-defence</strong>, especially the clinch for controlling an aggressor at close range.',
                        '🧠 <strong>Discipline, respect and mental toughness</strong> rooted in Thai tradition and ritual.',
                        '⚖️ <strong>Balance and core strength</strong> from kicking, kneeing and clinch work.',
                        '💪 <strong>Confidence and resilience</strong> built through hard, structured training.',
                    ],
                    'limitations' => [
                        '🤼 <strong>No ground fighting</strong> — it must be paired with grappling for an all-round game.',
                        '🤕 <strong>Hard sparring and shin conditioning carry injury risk</strong> and demand careful management.',
                        '🦴 <strong>High physical toll</strong> on shins, joints and body; recovery and technique matter greatly.',
                        '👶 <strong>Full-contact intensity</strong> means beginners and children need carefully scaled training.',
                    ],
                    'rules' => [
                        '🥊 Professional bouts are typically five rounds of three minutes with two-minute breaks, fought in a ring with gloves.',
                        '🎯 Legal weapons are punches, elbows, knees and kicks; the standing <strong>clinch is allowed</strong> and central to the sport.',
                        '📊 Bouts are scored round-by-round on effective, damaging strikes, dominance and balance; the winner takes the round.',
                        '🏆 A fight can end by knockout, technical knockout, or the judges\' decision.',
                        '🙏 Fighters perform the <em>Wai Kru Ram Muay</em> ritual dance before the bout to honour their teachers.',
                        '🚫 Prohibited actions include head-butting, biting, spitting and striking a downed opponent.',
                        '⚖️ Contests are organised within weight classes for fairness.',
                    ],
                ],
                'ar' => [
                    'intro' => '🥊 «فن الأطراف الثمانية» — الفن القتالي الوطني لتايلاند الذي يوظّف القبضات والأكواع والركب والسيقان مع اشتباك واقف مهيمن. من أكثر فنون الضرب واقفاً فعاليةً واحتراماً في العالم، وأساس متين للفنون القتالية المختلطة الحديثة.',
                    'history' => [
                        'ينحدر المواي تاي من <em>مواي بوران</em> («الملاكمة القديمة»)، فنون ساحات المعارك والدفاع عن النفس لدى الشعب التايلاندي، المُمارَسة قروناً والمرتبطة بتاريخ تايلاند العسكري وفلكلورها. وكانت النزالات تُخاض تاريخياً بقبضات ملفوفة بالقنّب في المهرجانات وأمام الملوك.',
                        'وفي مطلع القرن العشرين تحدّث الفن: اعتُمدت الحلبات والجولات المؤقتة وفئات الوزن والقفازات المبطّنة، فاتخذ شكله المعروف اليوم. ورغم التحديث احتفظ بطقوس عميقة — رقصة ما قبل النزال <em>الواي كرو رام مواي</em> تكريماً للمعلّم والتراث، وعصابة الرأس المقدّسة <em>المونغكول</em> وأربطة الذراع <em>البراجيود</em>.',
                        'ومنذ أواخر القرن العشرين انتشر المواي تاي عالمياً رياضةً وسعياً للياقة، وجعلته اشتباكاته وركلاته المدمّرة مكوّناً ضاربياً محورياً في الفنون القتالية المختلطة. ويشرف عليه دولياً اليوم هيئات مثل <strong>IFMA</strong>، وقد نال اعترافاً أولمبياً.',
                    ],
                    'focus' => [
                        'يستخدم المواي تاي ثماني نقاط تلامس — لكمات وأكواع وركب وركلات — أكثر بكثير من الملاكمة أو التايكوندو. وأسلحته المميّزة ركلة الساق الدائرية القوية والركبة، وهو فريد بين فنون الضرب باشتباكه الواقف المهيمن الذي يُستخدَم للتحكّم بالخصم وإطلاق الركب وكسحات كسر الاتزان.',
                        'يقدّر الأسلوب الصلابة والتوقيت والضغط الأمامي المتواصل وإعداد الساق/الجسم. ويميل الاحتساب تقليدياً إلى التقنية المؤثّرة المتوازنة المهيمنة على الكثرة، مكافئاً المقاتل الذي يحافظ على رباطة جأشه ويتحكّم بالحلبة.',
                    ],
                    'benefits' => [
                        '🔥 <strong>إعداد بدني قاسٍ للجسم كله</strong> وقوة قلبية استثنائية.',
                        '🦵 <strong>ضرب واقف متكامل ومدمّر</strong> — ركلات وركب وأكواع ولكمات.',
                        '🛡️ <strong>دفاع عن النفس عملي جداً</strong>، خصوصاً الاشتباك للتحكّم بالمعتدي في المدى القريب.',
                        '🧠 <strong>انضباط واحترام وصلابة ذهنية</strong> متجذّرة في التقاليد والطقوس التايلاندية.',
                        '⚖️ <strong>اتزان وقوة للجذع</strong> من الركل والركب وعمل الاشتباك.',
                        '💪 <strong>ثقة ومتانة</strong> تُبنى عبر تدريب شاق منظّم.',
                    ],
                    'limitations' => [
                        '🤼 <strong>لا قتال أرضي</strong> — يلزم دمجه بالمصارعة للعبة شاملة.',
                        '🤕 <strong>القتال الشاق وإعداد الساق يحملان خطر إصابة</strong> ويتطلبان إدارة دقيقة.',
                        '🦴 <strong>عبء بدني كبير</strong> على السيقان والمفاصل والجسم؛ والتعافي والتقنية بالغا الأهمية.',
                        '👶 <strong>شدّة التلامس الكامل</strong> تعني حاجة المبتدئين والأطفال إلى تدريب مُتدرِّج بعناية.',
                    ],
                    'rules' => [
                        '🥊 نزالات المحترفين عادةً خمس جولات مدة كل منها ثلاث دقائق مع راحة دقيقتين، في حلبة بالقفازات.',
                        '🎯 الأسلحة المسموحة اللكمات والأكواع والركب والركلات؛ و<strong>الاشتباك الواقف مسموح</strong> ومحوريّ في الرياضة.',
                        '📊 يُحتسَب النزال جولةً بجولة على الضربات الفعّالة المؤثّرة والهيمنة والاتزان؛ والفائز يأخذ الجولة.',
                        '🏆 قد ينتهي النزال بالضربة القاضية أو القاضية الفنية أو بقرار الحكام.',
                        '🙏 يؤدّي المقاتلون رقصة <em>الواي كرو رام مواي</em> الطقسية قبل النزال تكريماً لمعلّميهم.',
                        '🚫 من المحظورات النطح والعضّ والبصق وضرب خصم ساقط.',
                        '⚖️ تُنظَّم النزالات ضمن فئات وزن تحقيقاً للعدالة.',
                    ],
                ],
                'links' => [
                    ['label' => 'International Federation of Muaythai Associations (IFMA)', 'url' => 'https://muaythai.sport/'],
                    ['label' => 'Britannica — Muay Thai', 'url' => 'https://www.britannica.com/sports/muay-Thai'],
                    ['label' => 'Wikipedia — Muay Thai', 'url' => 'https://en.wikipedia.org/wiki/Muay_Thai'],
                    ['label' => 'Wikipedia — Muay Boran', 'url' => 'https://en.wikipedia.org/wiki/Muay_Boran'],
                ],
                'image_prompt' => "A cinematic, ultra-detailed hero poster in dramatic dark fantasy game-art style, 16:9 widescreen. Two Muay Thai warriors — a muscular male nak muay and a fierce female nak muay — caught mid-action throwing a spinning elbow and a thundering shin kick, wearing traditional Muay Thai shorts, prajioud arm-bands and the sacred Mongkol headband, sweat and impact sparks flying, glowing golden temple-fire energy swirling around their strikes. Behind them looms a massive translucent Garuda and Naga serpent deity amid stormy skies. The background reveals a golden Thai temple (wat) with spires and banners, lotus petals and embers in the wind. Bold stylized 3D calligraphic title text reading 'MUAY THAI' dominates the top in Thai-inspired lettering, with an evocative subtitle beneath. Moody chiaroscuro lighting, rim-lit silhouettes, gold-crimson-emerald palette, hyper-detailed digital painting, 4K premium game cover art quality.",
            ],

            /* ============ BRAZILIAN JIU-JITSU — GI ============ */
            [
                'slug' => 'brazilian-jiu-jitsu-gi',
                'name' => 'Brazilian Jiu-Jitsu (Gi)',
                'name_ar' => 'الجوجيتسو البرازيلي (مع البدلة)',
                'icon' => 'bi-person-arms-up',
                'replaces' => 'brazilian-jiu-jitsu',
                'en' => [
                    'intro' => '🥋 The traditional, kimono-based form of Brazilian Jiu-Jitsu — "the gentle art" of ground fighting, where grips on the gi enable a rich web of control, sweeps and submissions. It is famous for proving that a smaller, skilled grappler can defeat a larger, stronger opponent through leverage and technique.',
                    'history' => [
                        'BJJ traces to the ground-fighting (<em>ne-waza</em>) of Kodokan Judo. In the early 20th century the Japanese judoka and prizefighter <strong>Mitsuyo Maeda</strong> travelled the world and settled in Brazil, where he taught the young <strong>Carlos Gracie</strong>.',
                        'The <strong>Gracie family</strong> — especially Carlos and his brother Hélio — adapted and refined the ground game over the following decades, emphasising leverage, position and submissions so that a physically weaker person could prevail. They tested and proved the system in countless "vale tudo" (anything-goes) challenge matches in Brazil.',
                        'BJJ exploded onto the global stage when <strong>Royce Gracie</strong> won the early Ultimate Fighting Championship tournaments in the 1990s, submitting far larger opponents. That demonstration made ground grappling indispensable in modern MMA, and BJJ is now a worldwide sport governed by bodies such as the <strong>IBJJF</strong>.',
                    ],
                    'focus' => [
                        'Gi BJJ is fought entirely around ground control: playing and passing the <em>guard</em>, achieving dominant positions (side control, mount, back), and finishing with chokes and joint locks. The gi\'s collars, sleeves and lapels create additional gripping points that slow the game down.',
                        'This makes gi jiu-jitsu a deeply technical, chess-like discipline — often called "human chess" — that rewards patience, precise sequencing and strategy over raw athleticism and speed.',
                    ],
                    'benefits' => [
                        '🧠 <strong>A profoundly technical "human chess"</strong> that builds problem-solving, patience and composure.',
                        '🛡️ <strong>Elite control and submission skills</strong> — among the most practical self-defence methods, allowing control of a larger person.',
                        '💪 <strong>Functional strength, mobility and grip endurance</strong> developed through live rolling.',
                        '🤝 <strong>Lower striking-injury risk</strong> than percussive arts, since sparring ("rolling") is done at full resistance but without blows.',
                        '❤️ <strong>Excellent conditioning</strong> and stress relief.',
                        '🏆 <strong>A clear belt and competition ladder</strong> (white to black belt over many years).',
                    ],
                    'limitations' => [
                        '👊 <strong>No striking</strong> — it must be paired with a stand-up art for a complete fighting or self-defence game.',
                        '🥋 <strong>Gi-specific grips</strong> don\'t transfer directly to no-gi grappling or real-world clothing situations.',
                        '⏳ <strong>A steep learning curve</strong> — meaningful skill takes years and the black belt is famously hard-earned.',
                        '🤼 <strong>Ground focus</strong> can be a disadvantage against multiple opponents or in unsafe environments.',
                    ],
                    'rules' => [
                        '🥋 Fought in a gi; gripping the opponent\'s gi is legal and central to the game.',
                        '🏆 The cleanest win is by <strong>submission</strong> (the opponent "taps out"); otherwise the match is decided on points.',
                        '📊 Points are awarded for positions: takedown and sweep (2), knee-on-belly (2), guard pass (3), mount and back control (4).',
                        '⏱️ Match length varies by belt and level; "advantages" break ties when points are equal.',
                        '🚫 Legal submissions depend on belt/level — for example, heel-hooks are traditionally restricted in gi competition and lower belts have further limits.',
                        '⚖️ Competitors are divided by belt, age and weight for fair matchups.',
                        '🎽 Ranks progress white → blue → purple → brown → black, with stripes marking progress within each belt.',
                    ],
                ],
                'ar' => [
                    'intro' => '🥋 الشكل التقليدي من الجوجيتسو البرازيلي القائم على الكيمونو — «الفن اللطيف» للقتال الأرضي، حيث تتيح القبضات على البدلة شبكةً غنية من التحكم والقلب والإخضاع. ويشتهر بإثبات أن مصارعاً أصغر ماهراً يمكنه هزيمة خصم أكبر وأقوى عبر الرافعة والتقنية.',
                    'history' => [
                        'يعود الجوجيتسو البرازيلي إلى العمل الأرضي (<em>نيه-وازا</em>) في جودو الكودوكان. ففي مطلع القرن العشرين جاب الجودوكا والمقاتل المحترف الياباني <strong>ميتسويو مايدا</strong> العالم واستقرّ في البرازيل حيث علّم الشاب <strong>كارلوس غرايسي</strong>.',
                        'وطوّرت <strong>عائلة غرايسي</strong> — خصوصاً كارلوس وأخوه هيليو — اللعبة الأرضية وهذّبتها عبر العقود التالية، مؤكّدةً الرافعة والوضعية والإخضاع بحيث يتغلّب الأضعف جسدياً. واختبروا النظام وأثبتوه في نزالات تحدٍّ لا تُحصى «فالي تودو» (كل شيء مسموح) في البرازيل.',
                        'وانفجر الجوجيتسو البرازيلي على المسرح العالمي حين فاز <strong>رويس غرايسي</strong> ببطولات UFC الأولى في التسعينيات، مُخضِعاً خصوماً أكبر منه بكثير. وجعل ذلك العرض المصارعة الأرضية لا غنى عنها في الفنون المختلطة الحديثة، وصار الجوجيتسو البرازيلي رياضةً عالمية تشرف عليها هيئات مثل <strong>IBJJF</strong>.',
                    ],
                    'focus' => [
                        'يُخاض الجوجيتسو بالبدلة بالكامل حول التحكّم الأرضي: لعب <em>الحارس (الغارد)</em> وتجاوزه، وبلوغ الوضعيات المهيمنة (التحكّم الجانبي، الاعتلاء، الظهر)، والإنهاء بالخنق وأقفال المفاصل. وتخلق ياقات البدلة وأكمامها وطيّاتها نقاط قبض إضافية تُبطئ اللعب.',
                        'وهذا يجعل جوجيتسو البدلة فناً تقنياً عميقاً شبيهاً بالشطرنج — يُسمّى غالباً «الشطرنج البشري» — يكافئ الصبر والتسلسل الدقيق والاستراتيجية على الرياضية الخام والسرعة.',
                    ],
                    'benefits' => [
                        '🧠 <strong>«شطرنج بشري» تقنيّ عميق</strong> يبني حلّ المشكلات والصبر ورباطة الجأش.',
                        '🛡️ <strong>مهارات تحكّم وإخضاع رفيعة</strong> — من أعمَل طرق الدفاع عن النفس، إذ تتيح التحكّم بشخص أكبر.',
                        '💪 <strong>قوة وظيفية ومرونة وتحمّل قبضة</strong> تتطوّر عبر القتال الحيّ.',
                        '🤝 <strong>خطر إصابة أقل</strong> من فنون الضرب، إذ يُخاض القتال («الرول») بمقاومة كاملة دون لكمات.',
                        '❤️ <strong>إعداد بدني ممتاز</strong> وتفريغ للتوتر.',
                        '🏆 <strong>سلّم أحزمة ومنافسة واضح</strong> (من الأبيض إلى الأسود عبر سنوات).',
                    ],
                    'limitations' => [
                        '👊 <strong>لا ضرب</strong> — يلزم دمجه بفنّ وقوف للعبة قتال أو دفاع متكاملة.',
                        '🥋 <strong>قبضات البدلة</strong> لا تنتقل مباشرةً إلى القتال بلا بدلة أو مواقف الملابس الواقعية.',
                        '⏳ <strong>منحنى تعلّم حادّ</strong> — تحتاج المهارة الحقيقية سنوات والحزام الأسود صعب المنال شهيراً.',
                        '🤼 <strong>التركيز الأرضي</strong> قد يكون عيباً أمام عدّة خصوم أو في بيئات غير آمنة.',
                    ],
                    'rules' => [
                        '🥋 يُخاض بالبدلة؛ والقبض على بدلة الخصم قانونيّ ومحوريّ في اللعبة.',
                        '🏆 أنظف فوز بالـ<strong>إخضاع</strong> (استسلام الخصم بالنقر)؛ وإلا يُحسَم النزال بالنقاط.',
                        '📊 تُمنح النقاط حسب الوضعيات: الإسقاط والقلب (2)، الركبة على البطن (2)، تجاوز الحارس (3)، الاعتلاء والتحكّم بالظهر (4).',
                        '⏱️ تختلف مدة النزال حسب الحزام والمستوى؛ وتُحسم التعادلات بالـ«أفضليات» عند تساوي النقاط.',
                        '🚫 تعتمد الإخضاعات المسموحة على الحزام/المستوى — مثلاً تُقيَّد أقفال الكعب تقليدياً في منافسة البدلة، وللأحزمة الأدنى قيود إضافية.',
                        '⚖️ يُقسَّم المتنافسون حسب الحزام والعمر والوزن لمواجهات عادلة.',
                        '🎽 تتدرّج الرتب أبيض ← أزرق ← بنفسجي ← بنّي ← أسود، مع خطوط تُبيّن التقدّم داخل كل حزام.',
                    ],
                ],
                'links' => [
                    ['label' => 'IBJJF (International Brazilian Jiu-Jitsu Federation)', 'url' => 'https://ibjjf.com/'],
                    ['label' => 'Britannica — Brazilian jiu-jitsu', 'url' => 'https://www.britannica.com/sports/Brazilian-jiu-jitsu'],
                    ['label' => 'Wikipedia — Brazilian jiu-jitsu', 'url' => 'https://en.wikipedia.org/wiki/Brazilian_jiu-jitsu'],
                    ['label' => 'Wikipedia — Gracie family', 'url' => 'https://en.wikipedia.org/wiki/Gracie_family'],
                    ['label' => 'Wikipedia — Mitsuyo Maeda', 'url' => 'https://en.wikipedia.org/wiki/Mitsuyo_Maeda'],
                ],
                'image_prompt' => "A cinematic, ultra-detailed hero poster in dramatic dark fantasy game-art style, 16:9 widescreen. Two Brazilian Jiu-Jitsu practitioners — a muscular male grappler and a fierce female grappler — locked mid-action in a dynamic guard-and-submission exchange (one securing a triangle choke, the other posturing to pass), wearing crisp gi kimonos in white and navy with colored belts and IBJJF-style patches, glowing green-and-gold flowing energy ribbons tracing their grips. Behind them looms a massive translucent boa constrictor and jaguar spirit symbolising control amid stormy skies. The background reveals Rio de Janeiro with Christ the Redeemer and Sugarloaf Mountain, Brazilian-flag-toned banners, and leaves and sparks in the wind. Bold stylized 3D title text reading 'BRAZILIAN JIU-JITSU — GI' dominates the top, with an evocative subtitle beneath. Moody chiaroscuro lighting, rim-lit silhouettes, green-gold-navy palette, hyper-detailed digital painting, 4K premium game cover art quality.",
            ],

        ];
    }
}
