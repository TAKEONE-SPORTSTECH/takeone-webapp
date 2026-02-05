@extends('layouts.app')

@section('content')
<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-4">
    <div class="flex justify-between items-center mb-4">
        <div>
            <h1 class="mb-1 text-2xl font-bold">Family Members</h1>
            <p class="text-gray-500 mb-0">Manage and view your family members</p>
        </div>
    </div>

    <!-- Family Members Card Grid -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-5">


        <!-- Dependents Cards -->
        @foreach($dependents as $relationship)
            <div>
                <a href="{{ route('member.show', $relationship->dependent->id) }}" class="no-underline">
                    <div class="bg-white rounded-lg h-full shadow-sm border border-gray-200 overflow-hidden flex flex-col family-card">
                        <!-- Header with gradient background -->
                        <div class="p-4 pb-3" style="background: linear-gradient(135deg, {{ $relationship->dependent->gender == 'm' ? 'rgba(147, 51, 234, 0.1) 0%, rgba(147, 51, 234, 0.05) 50%' : 'rgba(214, 51, 132, 0.1) 0%, rgba(214, 51, 132, 0.05) 50%' }}, transparent 100%);">
                        <div class="flex items-start gap-3">
                            <div class="relative">
                                <div class="rounded-full border-4 border-white shadow w-20 h-20 overflow-hidden" style="box-shadow: 0 0 0 2px {{ $relationship->dependent->gender == 'm' ? 'rgba(147, 51, 234, 0.3)' : 'rgba(214, 51, 132, 0.3)' }} !important;">
                                @if($relationship->dependent->profile_picture)
                                    <img src="{{ asset('storage/' . $relationship->dependent->profile_picture) }}" alt="{{ $relationship->dependent->full_name }}" class="w-full h-full object-cover">
                                @else
                                    <div class="w-full h-full flex items-center justify-center text-white font-bold text-2xl" style="background: linear-gradient(135deg, {{ $relationship->dependent->gender == 'm' ? '#8b5cf6 0%, #7c3aed 100%' : '#d63384 0%, #a61e4d 100%' }});">
                                        {{ strtoupper(substr($relationship->dependent->full_name, 0, 1)) }}
                                    </div>
                                @endif
                                </div>
                            </div>
                            <div class="flex-1 min-w-0">
                                <h5 class="font-bold mb-2 truncate">{{ $relationship->dependent->full_name }}</h5>
                                <div class="flex flex-wrap gap-2">
                                    @php
                                        $age = $relationship->dependent->age;
                                        $ageGroup = 'Adult';
                                        if ($age < 2) {
                                            $ageGroup = 'Infant';
                                        } elseif ($age < 4) {
                                            $ageGroup = 'Toddler';
                                        } elseif ($age < 6) {
                                            $ageGroup = 'Preschooler';
                                        } elseif ($age < 13) {
                                            $ageGroup = 'Child';
                                        } elseif ($age < 20) {
                                            $ageGroup = 'Teenager';
                                        } elseif ($age < 40) {
                                            $ageGroup = 'Young Adult';
                                        } elseif ($age < 60) {
                                            $ageGroup = 'Adult';
                                        } else {
                                            $ageGroup = 'Senior';
                                        }
                                    @endphp
                                    <span class="inline-block px-2 py-0.5 text-xs font-medium rounded-full text-white {{ $relationship->dependent->gender == 'm' ? 'bg-purple-500' : 'bg-pink-500' }}">{{ $ageGroup }}</span>
                                    <span class="inline-block px-2 py-0.5 text-xs font-medium rounded-full text-white bg-green-500">Active</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Contact Info -->
                    <div class="px-4 py-3 bg-gray-50 border-t border-b">
                        <div class="flex items-center gap-2 text-sm mb-2">
                            <i class="bi bi-telephone-fill {{ $relationship->dependent->gender == 'm' ? 'text-primary' : 'text-destructive' }}"></i>
                            <span class="font-medium text-gray-500">{{ $relationship->dependent->mobile_formatted ?: ($user->mobile_formatted ?: 'Not provided') }}</span>
                            @if(!$relationship->dependent->mobile_formatted && $user->mobile_formatted)
                                <span class="inline-block px-2 py-0.5 text-xs font-medium rounded-full ml-auto {{ $relationship->dependent->gender == 'm' ? 'bg-sky-100 text-sky-700' : 'bg-pink-500 text-white' }}">Guardian's</span>
                            @endif
                        </div>
                        @if($relationship->dependent->email)
                        <div class="flex items-center gap-2 text-sm">
                            <i class="bi bi-envelope-fill {{ $relationship->dependent->gender == 'm' ? 'text-primary' : 'text-destructive' }}"></i>
                            <span class="font-medium text-gray-500 truncate">{{ $relationship->dependent->email }}</span>
                        </div>
                        @elseif($user->email)
                        <div class="flex items-center gap-2 text-sm">
                            <i class="bi bi-envelope-fill {{ $relationship->dependent->gender == 'm' ? 'text-primary' : 'text-destructive' }}"></i>
                            <span class="font-medium text-gray-500 truncate">{{ $user->email }}</span>
                            <span class="inline-block px-2 py-0.5 text-xs font-medium rounded-full ml-auto {{ $relationship->dependent->gender == 'm' ? 'bg-sky-100 text-sky-700' : 'bg-pink-500 text-white' }}">Guardian's</span>
                        </div>
                        @endif
                    </div>

                    <!-- Details -->
                    <div class="px-4 py-3 flex-1">
                        <div class="grid grid-cols-2 gap-3 mb-3">
                            <div>
                                <div class="text-xs text-gray-500 uppercase font-medium mb-1" style="letter-spacing: 0.5px;">Gender</div>
                                <div class="font-semibold text-gray-500 capitalize">
                                    <i class="bi {{ $relationship->dependent->gender == 'm' ? 'bi-man text-primary' : 'bi-woman text-destructive' }} mr-1"></i>
                                    {{ $relationship->dependent->gender == 'm' ? 'Male' : 'Female' }}
                                </div>
                            </div>
                            <div>
                                <div class="text-xs text-gray-500 uppercase font-medium mb-1" style="letter-spacing: 0.5px;">Age</div>
                                <div class="font-semibold text-gray-500">
                                    <i class="bi bi-cake text-warning mr-1"></i>
                                    {{ $relationship->dependent->age }} years
                                </div>
                            </div>
                        </div>
                        <div class="grid grid-cols-2 gap-3 mb-3">
                            <div>
                                <div class="text-xs text-gray-500 uppercase font-medium mb-1" style="letter-spacing: 0.5px;">Nationality</div>
                                <div class="font-semibold text-gray-500 text-lg nationality-display" data-iso3="{{ $relationship->dependent->nationality }}">{{ $relationship->dependent->nationality }}</div>
                            </div>
                            <div>
                                <div class="text-xs text-gray-500 uppercase font-medium mb-1" style="letter-spacing: 0.5px;">Horoscope</div>
                                <div class="font-semibold text-gray-500">
                                    @php
                                        $horoscopeSymbols = [
                                            'Aries' => '♈',
                                            'Taurus' => '♉',
                                            'Gemini' => '♊',
                                            'Cancer' => '♋',
                                            'Leo' => '♌',
                                            'Virgo' => '♍',
                                            'Libra' => '♎',
                                            'Scorpio' => '♏',
                                            'Sagittarius' => '♐',
                                            'Capricorn' => '♑',
                                            'Aquarius' => '♒',
                                            'Pisces' => '♓'
                                        ];
                                        $horoscope = $relationship->dependent->horoscope ?? 'N/A';
                                        $symbol = $horoscopeSymbols[$horoscope] ?? '';
                                    @endphp
                                    {{ $symbol }} {{ $horoscope }}
                                </div>
                            </div>
                        </div>
                        <div class="pt-2 border-t">
                            <div class="flex justify-between items-center text-sm mb-2">
                                <span class="text-gray-500 font-medium">Next Birthday</span>
                                <span class="font-semibold text-gray-500">
                                    @if($relationship->dependent->birthdate)
                                        {{ $relationship->dependent->birthdate->copy()->year(now()->year)->isFuture()
                                            ? $relationship->dependent->birthdate->copy()->year(now()->year)->diffForHumans(['parts' => 2, 'syntax' => \Carbon\CarbonInterface::DIFF_ABSOLUTE])
                                            : $relationship->dependent->birthdate->copy()->year(now()->year + 1)->diffForHumans(['parts' => 2, 'syntax' => \Carbon\CarbonInterface::DIFF_ABSOLUTE]) }}
                                    @else
                                        N/A
                                    @endif
                                </span>
                            </div>
                            <div class="flex justify-between items-center text-sm">
                                <span class="text-gray-500 font-medium">Member Since</span>
                                <span class="font-semibold text-gray-500">{{ $relationship->dependent->created_at->format('d/m/Y') }}</span>
                            </div>
                    </div>
                </div>

                <!-- Sponsor/Guardian Info - Footer -->
                <div class="px-4 py-2 {{ $relationship->dependent->gender == 'm' ? 'bg-primary/10' : 'bg-destructive/10' }} border-t">
                    <div class="flex items-center justify-center gap-2 text-sm">
                        <span class="font-medium text-white">
                            {{ $relationship->relationship_type === 'spouse' ? 'WIFE' : strtoupper($relationship->relationship_type) }}
                        </span>
                    </div>
                </div>
                </div>
            </a>
        </div>
        @endforeach

        <!-- Add New Family Member Card -->
        <div>
            <a href="{{ route('members.create') }}" class="no-underline">
                <div class="bg-white rounded-lg h-full shadow-sm border-2 border-dashed border-gray-300 add-card">
                    <div class="text-center flex flex-col justify-center items-center h-full cursor-pointer p-6">
                        <div class="mb-3">
                            <i class="bi bi-plus-circle text-5xl"></i>
                        </div>
                        <h5 class="font-semibold text-gray-500">Add Member</h5>
                    </div>
                </div>
            </a>
        </div>
    </div>


</div>

<style>
    /* Family Card Hover Effects */
    .family-card {
        transition: all 0.3s ease-in-out;
        cursor: pointer;
    }

    .family-card:hover {
        transform: translateY(-8px);
        box-shadow: 0 12px 24px rgba(0, 0, 0, 0.15) !important;
    }

    .family-card:hover .rounded-full {
        transform: scale(1.1);
        transition: transform 0.3s ease-in-out;
    }

    /* Add Card Hover Effects */
    .add-card {
        transition: all 0.3s ease-in-out;
    }

    .add-card:hover {
        transform: translateY(-8px);
        box-shadow: 0 12px 24px rgba(0, 0, 0, 0.15) !important;
        border-color: #7c3aed !important;
    }

    .add-card:hover .bi-plus-circle {
        color: #7c3aed;
        transition: color 0.3s ease-in-out;
    }

    .add-card:hover h5 {
        color: #7c3aed;
        transition: color 0.3s ease-in-out;
    }
</style>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Load countries from JSON file
        fetch('/data/countries.json')
            .then(response => response.json())
            .then(countries => {
                // Convert all nationality displays from ISO3 to country name with flag
                document.querySelectorAll('.nationality-display').forEach(element => {
                    const iso3Code = element.getAttribute('data-iso3');
                    if (!iso3Code) return;

                    const country = countries.find(c => c.iso3 === iso3Code);
                    if (country) {
                        // Get flag emoji from ISO2 code
                        const flagEmoji = country.iso2
                            .toUpperCase()
                            .split('')
                            .map(char => String.fromCodePoint(127397 + char.charCodeAt(0)))
                            .join('');

                        element.textContent = `${flagEmoji} ${country.iso2.toUpperCase()}`;
                    }
                });
            })
            .catch(error => console.error('Error loading countries:', error));
    });
</script>
@endsection
