@extends('layouts.admin-club')

@section('club-admin-content')
<div class="space-y-6">
    <!-- Header -->
    <div>
        <h2 class="text-2xl font-bold mb-2">Dashboard Overview</h2>
        <p class="text-muted-foreground">Welcome to {{ $club->club_name }} management</p>
    </div>

    <!-- Stats Cards -->
    <div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-5 gap-3">
        <!-- Active Members -->
        <div class="card h-full border-0 shadow-sm cursor-pointer hover:shadow-md transition-shadow" onclick="window.location='{{ route('admin.club.members', $club->slug) }}'">
            <div class="card-body">
                <div class="flex items-center justify-between mb-2">
                    <span class="text-muted-foreground text-sm">Active Members</span>
                    <div class="rounded p-2 bg-primary/10">
                        <i class="bi bi-people text-primary"></i>
                    </div>
                </div>
                <h3 class="font-bold text-primary mb-1">{{ $stats['members'] ?? 0 }}</h3>
                <p class="text-muted-foreground text-sm mb-0">Members with valid packages</p>
            </div>
        </div>

        <!-- Activities -->
        <div class="card h-full border-0 shadow-sm cursor-pointer hover:shadow-md transition-shadow" onclick="window.location='{{ route('admin.club.activities', $club->slug) }}'">
            <div class="card-body">
                <div class="flex items-center justify-between mb-2">
                    <span class="text-muted-foreground text-sm">Activities</span>
                    <div class="rounded p-2 bg-warning/10">
                        <i class="bi bi-activity text-warning"></i>
                    </div>
                </div>
                <h3 class="font-bold text-warning mb-1">{{ $stats['activities'] ?? 0 }}</h3>
                <p class="text-muted-foreground text-sm mb-0">Available activities</p>
            </div>
        </div>

        <!-- Packages -->
        <div class="card h-full border-0 shadow-sm cursor-pointer hover:shadow-md transition-shadow" onclick="window.location='{{ route('admin.club.packages', $club->slug) }}'">
            <div class="card-body">
                <div class="flex items-center justify-between mb-2">
                    <span class="text-muted-foreground text-sm">Packages</span>
                    <div class="rounded p-2 bg-success/10">
                        <i class="bi bi-box text-success"></i>
                    </div>
                </div>
                <h3 class="font-bold text-success mb-1">{{ $stats['packages'] ?? 0 }}</h3>
                <p class="text-muted-foreground text-sm mb-0">Available packages</p>
            </div>
        </div>

        <!-- Trainers -->
        <div class="card h-full border-0 shadow-sm cursor-pointer hover:shadow-md transition-shadow" onclick="window.location='{{ route('admin.club.instructors', $club->slug) }}'">
            <div class="card-body">
                <div class="flex items-center justify-between mb-2">
                    <span class="text-muted-foreground text-sm">Trainers</span>
                    <div class="rounded p-2 bg-info/10">
                        <i class="bi bi-person-badge text-info"></i>
                    </div>
                </div>
                <h3 class="font-bold text-info mb-1">{{ $stats['instructors'] ?? 0 }}</h3>
                <p class="text-muted-foreground text-sm mb-0">Available trainers</p>
            </div>
        </div>

        <!-- Rating -->
        <div class="card h-full border-0 shadow-sm cursor-pointer hover:shadow-md transition-shadow" onclick="window.location='{{ route('admin.club.details', $club->slug) }}'">
            <div class="card-body">
                <div class="flex items-center justify-between mb-2">
                    <span class="text-muted-foreground text-sm">Member Rating</span>
                    <div class="rounded p-2" style="background-color: hsl(35 90% 55% / 0.1);">
                        <i class="bi bi-star-fill" style="color: hsl(35, 90%, 55%);"></i>
                    </div>
                </div>
                <div class="flex items-center gap-2">
                    <h3 class="font-bold mb-1" style="color: hsl(35, 90%, 55%);">{{ number_format($stats['rating'] ?? 0, 1) }}</h3>
                    <i class="bi bi-star-fill" style="color: hsl(35, 90%, 55%);"></i>
                </div>
                <p class="text-muted-foreground text-sm mb-0">Average from reviews</p>
            </div>
        </div>
    </div>

    <!-- Financial Overview Chart -->
    <div class="card border-0 shadow-sm">
        <div class="px-6 pt-6 pb-0">
            <h5 class="font-bold mb-1">Financial Overview (Last 12 Months)</h5>
            <p class="text-muted-foreground text-sm">Monthly income, expenses, and profit trends</p>
        </div>
        <div class="card-body">
            <canvas id="financialChart" height="300"></canvas>
        </div>
    </div>

    <!-- Expiring Subscriptions -->
    @if(isset($expiringSubscriptions) && count($expiringSubscriptions) > 0)
    <div class="card border-0 shadow-sm border-l-4 border-l-warning">
        <div class="px-6 py-4">
            <h5 class="font-bold mb-0">
                <i class="bi bi-exclamation-triangle text-warning mr-2"></i>
                Expiring Subscriptions
            </h5>
        </div>
        <div class="card-body pt-0">
            <div class="overflow-x-auto">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Member</th>
                            <th>Package</th>
                            <th>Expires</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($expiringSubscriptions as $subscription)
                        <tr>
                            <td>{{ $subscription->user->full_name ?? 'N/A' }}</td>
                            <td>{{ $subscription->package->name ?? 'N/A' }}</td>
                            <td>{{ $subscription->end_date ? $subscription->end_date->format('M d, Y') : 'N/A' }}</td>
                            <td>
                                <button class="btn btn-sm btn-outline-primary">Renew</button>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    @endif
</div>

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        const ctx = document.getElementById('financialChart').getContext('2d');

        // Sample data - replace with actual data from controller
        const monthlyData = @json($monthlyFinancials ?? []);

        const labels = monthlyData.map(d => d.month) || ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
        const incomeData = monthlyData.map(d => d.income) || [0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0];
        const expensesData = monthlyData.map(d => d.expenses) || [0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0];
        const profitData = monthlyData.map(d => d.profit) || [0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0];

        new Chart(ctx, {
            type: 'line',
            data: {
                labels: labels,
                datasets: [
                    {
                        label: 'Income',
                        data: incomeData,
                        borderColor: '#22c55e',
                        backgroundColor: 'rgba(34, 197, 94, 0.1)',
                        borderWidth: 2,
                        tension: 0.3
                    },
                    {
                        label: 'Expenses',
                        data: expensesData,
                        borderColor: '#ef4444',
                        backgroundColor: 'rgba(239, 68, 68, 0.1)',
                        borderWidth: 2,
                        tension: 0.3
                    },
                    {
                        label: 'Profit',
                        data: profitData,
                        borderColor: '#3b82f6',
                        backgroundColor: 'rgba(59, 130, 246, 0.1)',
                        borderWidth: 2,
                        tension: 0.3
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(value) {
                                return '{{ $club->currency ?? "BHD" }} ' + value.toLocaleString();
                            }
                        }
                    }
                }
            }
        });
    });
</script>
@endpush
@endsection
