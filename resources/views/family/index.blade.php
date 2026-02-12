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
            <x-member-card
                :member="$relationship->dependent"
                :href="route('member.show', $relationship->dependent->id)"
                :footerLabel="$relationship->relationship_type === 'spouse' ? 'WIFE' : strtoupper($relationship->relationship_type)"
                :guardian="$user"
            />
        @endforeach

        <!-- Add New Family Member Card -->
        <div x-data>
            <div class="bg-white rounded-lg h-full shadow-sm border-2 border-dashed border-gray-300 add-card"
                 @click="$dispatch('open-member-create-modal')">
                <div class="text-center flex flex-col justify-center items-center h-full cursor-pointer p-6">
                    <div class="mb-3">
                        <i class="bi bi-plus-circle text-5xl"></i>
                    </div>
                    <h5 class="font-semibold text-gray-500">Add Member</h5>
                </div>
            </div>
        </div>
    </div>

{{-- Add Family Member Modal --}}
<x-profile-modal
    mode="create"
    title="Add Family Member"
    subtitle="Fill in the details to add a new family member"
    :showRelationshipFields="true"
    :showEmailField="false"
    :formAction="route('family.store')"
    formMethod="POST"
/>


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
