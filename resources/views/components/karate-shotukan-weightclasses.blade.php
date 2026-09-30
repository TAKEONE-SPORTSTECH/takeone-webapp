{{--
    <x-karate-shotukan-weightclasses /> — loads the karate-shotukan-weightclasses
    library (the federation's Karate/Shotokan age categories and kumite weight
    classes) with its table embedded, once per page. Then, in any script:

        KarateShotukanWeightclasses.classify({gender: 'Female', dob: '2012-03-04', weight: 41})
        // → {ageGroup: 'Cadets', weightClass: '-47', label: 'Kumite -47 kg'}  (on 26 Sep 2026)

    The table is public/libraries/karate-shotukan-weightclasses/weightclasses.json
    (see its README). PHP: App\Support\KarateShotukanWeightclasses.
--}}
@once
    {!! \App\Support\KarateShotukanWeightclasses::tags() !!}
@endonce
