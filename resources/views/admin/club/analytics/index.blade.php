@extends('layouts.admin-club')

@section('club-admin-content')
<div>
    <div class="flex justify-between items-center mb-4">
        <div>
            <h2 class="tf-section-title">Analytics</h2>
            <p class="text-sm text-gray-500 mt-1">Track your club's performance</p>
        </div>
        <div class="flex gap-2">
            <select class="form-select" style="width: auto;">
                <option value="7">Last 7 days</option>
                <option value="30" selected>Last 30 days</option>
                <option value="90">Last 90 days</option>
                <option value="365">Last year</option>
            </select>
            <button class="btn btn-outline-primary">
                <i class="bi bi-download mr-2"></i>Export
            </button>
        </div>
    </div>

    <!-- Key Metrics -->
    @php
        $newMembersChange  = $analytics['new_members_change']  ?? 0;
        $retentionChange   = $analytics['retention_change']    ?? 0;
        $checkinsChange    = $analytics['checkins_change']     ?? 0;
        $monthlySpark      = $analytics['monthly_members']     ?? array_fill(0, 12, 0);
        $hourlySpark       = $analytics['hourly_checkins']     ?? array_fill(0, 9, 0);
        $monthLabels       = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
        $hourLabels        = ['6am','8am','10am','12pm','2pm','4pm','6pm','8pm','10pm'];
    @endphp
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 mb-4">
        <x-stat-card
            card-id="sc-new-members"
            label="New Members"
            :value="$analytics['new_members'] ?? 0"
            sub-label="this month"
            icon="bi-person-plus-fill"
            icon-bg="bg-violet-100"
            icon-color="text-violet-600"
            :spark-data="$monthlySpark"
            :spark-labels="$monthLabels"
            spark-color="#7c3aed"
            :trend="($newMembersChange > 0 ? '+' : '') . $newMembersChange . '%'"
            :trend-up="$newMembersChange >= 0"
        />
        <x-stat-card
            card-id="sc-retention"
            label="Retention Rate"
            :value="($analytics['retention_rate'] ?? 0) . '%'"
            sub-label="active members kept"
            icon="bi-arrow-repeat"
            icon-bg="bg-green-100"
            icon-color="text-green-600"
            :spark-data="$monthlySpark"
            :spark-labels="$monthLabels"
            spark-color="#16a34a"
            :trend="($retentionChange > 0 ? '+' : '') . $retentionChange . '%'"
            :trend-up="$retentionChange >= 0"
        />
        <x-stat-card
            card-id="sc-avg-revenue"
            label="Avg. Revenue / Member"
            :value="($club->currency ?? 'BHD') . ' ' . number_format($analytics['avg_revenue'] ?? 0, 2)"
            sub-label="per active member"
            icon="bi-cash-coin"
            icon-bg="bg-amber-100"
            icon-color="text-amber-600"
            :spark-data="$analytics['monthly_revenue'] ?? array_fill(0, 12, 0)"
            :spark-labels="$monthLabels"
            spark-color="#d97706"
        />
        <x-stat-card
            card-id="sc-checkins"
            label="Check-ins"
            :value="$analytics['total_checkins'] ?? 0"
            sub-label="this month"
            icon="bi-door-open-fill"
            icon-bg="bg-sky-100"
            icon-color="text-sky-600"
            :spark-data="$hourlySpark"
            :spark-labels="$hourLabels"
            spark-color="#0284c7"
            :trend="($checkinsChange > 0 ? '+' : '') . $checkinsChange . '%'"
            :trend-up="$checkinsChange >= 0"
        />
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-12 gap-4">
        <!-- Membership Growth Chart -->
        <div class="lg:col-span-8">
            <div class="card border-0 shadow-sm h-full">
                <div class="card-header bg-white border-0">
                    <h5 class="font-semibold mb-0">Membership Growth</h5>
                </div>
                <div class="card-body">
                    <div class="h-48 md:h-64">
                        <canvas id="membershipChart"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <!-- Popular Packages -->
        <div class="lg:col-span-4">
            <div class="card border-0 shadow-sm h-full">
                <div class="card-header bg-white border-0">
                    <h5 class="font-semibold mb-0">Popular Packages</h5>
                </div>
                <div class="card-body">
                    @if(isset($popularPackages) && count($popularPackages) > 0)
                        @foreach($popularPackages as $package)
                        <div class="flex justify-between items-center mb-3">
                            <div>
                                <p class="font-semibold mb-0">{{ $package->name }}</p>
                                <p class="text-muted small mb-0">{{ $package->subscriptions_count }} subscriptions</p>
                            </div>
                            <div class="text-right">
                                <p class="font-bold text-primary mb-0">{{ $package->percentage ?? 0 }}%</p>
                            </div>
                        </div>
                        <div class="progress mb-3" style="height: 6px;">
                            <div class="progress-bar bg-primary" style="width: {{ $package->percentage ?? 0 }}%"></div>
                        </div>
                        @endforeach
                    @else
                        <div class="text-center py-4">
                            <p class="text-muted mb-0">No package data available</p>
                        </div>
                    @endif
                </div>
            </div>
        </div>

        <!-- Activity Breakdown -->
        <div class="lg:col-span-6">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white border-0">
                    <h5 class="font-semibold mb-0">Activity Breakdown</h5>
                </div>
                <div class="card-body">
                    <div class="h-40 md:h-52">
                        <canvas id="activityChart"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <!-- Peak Hours -->
        <div class="lg:col-span-6">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white border-0">
                    <h5 class="font-semibold mb-0">Peak Hours</h5>
                </div>
                <div class="card-body">
                    <div class="h-40 md:h-52">
                        <canvas id="peakHoursChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

@push('scripts')
@php
$chartMonthlyMembers = $analytics['monthly_members'] ?? array_fill(0, 12, 0);
$chartActivityLabels = $analytics['activity_labels'] ?? ['No data'];
$chartActivityData   = $analytics['activity_data']   ?? [100];
$chartHourlyCheckins = $analytics['hourly_checkins'] ?? array_fill(0, 9, 0);
@endphp
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Membership Growth Chart
        new Chart(document.getElementById('membershipChart'), {
            type: 'line',
            data: {
                labels: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'],
                datasets: [{
                    label: 'Members',
                    data: @json($chartMonthlyMembers),
                    borderColor: 'hsl(355, 84%, 44%)',
                    backgroundColor: 'hsla(355, 84%, 44%, 0.1)',
                    fill: true,
                    tension: 0.3
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } }
            }
        });

        // Activity Chart
        new Chart(document.getElementById('activityChart'), {
            type: 'doughnut',
            data: {
                labels: @json($chartActivityLabels),
                datasets: [{
                    data: @json($chartActivityData),
                    backgroundColor: ['#CE1126', '#3b82f6', '#22c55e', '#f59e0b', '#8b5cf6']
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false
            }
        });

        // Peak Hours Chart
        new Chart(document.getElementById('peakHoursChart'), {
            type: 'bar',
            data: {
                labels: ['6am', '8am', '10am', '12pm', '2pm', '4pm', '6pm', '8pm', '10pm'],
                datasets: [{
                    label: 'Check-ins',
                    data: @json($chartHourlyCheckins),
                    backgroundColor: 'hsl(355, 84%, 44%)'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } }
            }
        });
    });
</script>
@endpush
@endsection
