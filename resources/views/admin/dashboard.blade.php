@extends('layouts.admin')

@section('admin-content')
<style>
    /* Dashboard Styles */
    .dashboard-header {
        margin-bottom: 2rem;
    }

    .dashboard-header h1 {
        font-size: 1.75rem;
        font-weight: 700;
        color: hsl(var(--foreground));
        margin-bottom: 0.5rem;
    }

    .dashboard-header p {
        color: hsl(var(--muted-foreground));
        margin: 0;
    }

    /* Stats Grid - 5 cards per row */
    .stats-grid {
        display: grid;
        grid-template-columns: repeat(5, 1fr);
        gap: 0.75rem;
        margin-bottom: 2rem;
    }

    /* Stat Card */
    .stat-card {
        background: hsl(var(--card));
        border: 1px solid hsl(var(--border));
        border-radius: 0.5rem;
        padding: 0.75rem;
        display: flex;
        align-items: center;
        gap: 0.5rem;
        transition: all 0.2s ease;
        cursor: pointer;
        text-decoration: none;
        color: inherit;
    }

    .stat-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1);
        border-color: hsl(var(--primary) / 0.3);
    }

    .stat-card:active {
        transform: translateY(0);
    }

    .stat-icon {
        width: 36px;
        height: 36px;
        border-radius: 0.4rem;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 0.9rem;
        flex-shrink: 0;
    }

    .stat-icon.clubs {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
    }

    .stat-icon.members {
        background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);
        color: white;
    }

    .stat-icon.trainers {
        background: linear-gradient(135deg, #fc4a1a 0%, #f7b733 100%);
        color: white;
    }

    .stat-icon.events {
        background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
        color: white;
    }

    .stat-icon.nutrition {
        background: linear-gradient(135deg, #a8edea 0%, #fed6e3 100%);
        color: #5f27cd;
    }

    .stat-icon.physiotherapy {
        background: linear-gradient(135deg, #ffecd2 0%, #fcb69f 100%);
        color: #ff6b6b;
    }

    .stat-icon.venues {
        background: linear-gradient(135deg, #d299c2 0%, #fef9d7 100%);
        color: #9b59b6;
    }

    .stat-icon.shops {
        background: linear-gradient(135deg, #89f7fe 0%, #66a6ff 100%);
        color: #fff;
    }

    .stat-icon.supplements {
        background: linear-gradient(135deg, #cd9cf2 0%, #f6f3ff 100%);
        color: #8e44ad;
    }

    .stat-icon.healthfood {
        background: linear-gradient(135deg, #b7f8db 0%, #50a7c2 100%);
        color: #27ae60;
    }

    .stat-content {
        flex: 1;
        min-width: 0;
    }

    .stat-value {
        font-size: 1.1rem;
        font-weight: 700;
        color: hsl(var(--foreground));
        line-height: 1.1;
        margin-bottom: 0.1rem;
    }

    .stat-label {
        font-size: 0.75rem;
        color: hsl(var(--muted-foreground));
        font-weight: 500;
    }

    .stat-arrow {
        display: none;
    }

    /* Recent Activity Section */
    .recent-section {
        background: hsl(var(--card));
        border: 1px solid hsl(var(--border));
        border-radius: 0.75rem;
        padding: 1.5rem;
        margin-bottom: 1.5rem;
    }

    .recent-section h3 {
        font-size: 1.125rem;
        font-weight: 600;
        color: hsl(var(--foreground));
        margin-bottom: 1rem;
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }

    .recent-section h3 i {
        color: hsl(var(--primary));
    }

    /* Quick Actions */
    .quick-actions {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 1rem;
    }

    .quick-action-btn {
        display: flex;
        align-items: center;
        gap: 0.75rem;
        padding: 1rem;
        background: hsl(var(--muted) / 0.5);
        border: 1px solid hsl(var(--border));
        border-radius: 0.5rem;
        cursor: pointer;
        transition: all 0.2s;
        text-decoration: none;
        color: hsl(var(--foreground));
    }

    .quick-action-btn:hover {
        background: hsl(var(--primary));
        color: white;
        border-color: hsl(var(--primary));
    }

    .quick-action-btn i {
        font-size: 1.25rem;
    }

    .quick-action-btn span {
        font-weight: 500;
        font-size: 0.875rem;
    }

    /* Responsive */
    @media (max-width: 1400px) {
        .stats-grid {
            grid-template-columns: repeat(4, 1fr);
        }
    }

    @media (max-width: 1200px) {
        .stats-grid {
            grid-template-columns: repeat(3, 1fr);
        }
    }

    @media (max-width: 992px) {
        .stats-grid {
            grid-template-columns: repeat(2, 1fr);
        }
    }

    @media (max-width: 576px) {
        .stats-grid {
            grid-template-columns: 1fr;
        }
    }
</style>

<div class="dashboard-header">
    <h1>Admin Dashboard</h1>
    <p>Welcome back! Here's an overview of your platform statistics.</p>
</div>

<!-- Statistics Grid -->
<div class="stats-grid">
    <!-- Clubs -->
    <a href="{{ route('admin.platform.clubs') }}" class="stat-card">
        <div class="stat-icon clubs">
            <i class="bi bi-house-door"></i>
        </div>
        <div class="stat-content">
            <div class="stat-value">{{ $stats['clubs'] ?? 0 }}</div>
            <div class="stat-label">Clubs</div>
        </div>
        <div class="stat-arrow">
            <i class="bi bi-arrow-right"></i>
        </div>
    </a>

    <!-- Members -->
    <a href="{{ route('admin.platform.members') }}" class="stat-card">
        <div class="stat-icon members">
            <i class="bi bi-people"></i>
        </div>
        <div class="stat-content">
            <div class="stat-value">{{ $stats['members'] ?? 0 }}</div>
            <div class="stat-label">Members</div>
        </div>
        <div class="stat-arrow">
            <i class="bi bi-arrow-right"></i>
        </div>
    </a>

    <!-- Trainers -->
    <a href="#" class="stat-card">
        <div class="stat-icon trainers">
            <i class="bi bi-person-badge"></i>
        </div>
        <div class="stat-content">
            <div class="stat-value">{{ $stats['trainers'] ?? 0 }}</div>
            <div class="stat-label">Trainers</div>
        </div>
        <div class="stat-arrow">
            <i class="bi bi-arrow-right"></i>
        </div>
    </a>

    <!-- Events -->
    <a href="#" class="stat-card">
        <div class="stat-icon events">
            <i class="bi bi-calendar-event"></i>
        </div>
        <div class="stat-content">
            <div class="stat-value">{{ $stats['events'] ?? 0 }}</div>
            <div class="stat-label">Events</div>
        </div>
        <div class="stat-arrow">
            <i class="bi bi-arrow-right"></i>
        </div>
    </a>

    <!-- Clinics - Nutrition -->
    <a href="#" class="stat-card">
        <div class="stat-icon nutrition">
            <i class="bi bi-cup-hot"></i>
        </div>
        <div class="stat-content">
            <div class="stat-value">{{ $stats['nutrition'] ?? 0 }}</div>
            <div class="stat-label">Nutrition</div>
        </div>
        <div class="stat-arrow">
            <i class="bi bi-arrow-right"></i>
        </div>
    </a>

    <!-- Clinics - Physiotherapy -->
    <a href="#" class="stat-card">
        <div class="stat-icon physiotherapy">
            <i class="bi bi-hospital"></i>
        </div>
        <div class="stat-content">
            <div class="stat-value">{{ $stats['physiotherapy'] ?? 0 }}</div>
            <div class="stat-label">Physiotherapy</div>
        </div>
        <div class="stat-arrow">
            <i class="bi bi-arrow-right"></i>
        </div>
    </a>

    <!-- Venues -->
    <a href="#" class="stat-card">
        <div class="stat-icon venues">
            <i class="bi bi-geo-alt"></i>
        </div>
        <div class="stat-content">
            <div class="stat-value">{{ $stats['venues'] ?? 0 }}</div>
            <div class="stat-label">Venues</div>
        </div>
        <div class="stat-arrow">
            <i class="bi bi-arrow-right"></i>
        </div>
    </a>

    <!-- Shops -->
    <a href="#" class="stat-card">
        <div class="stat-icon shops">
            <i class="bi bi-shop"></i>
        </div>
        <div class="stat-content">
            <div class="stat-value">{{ $stats['shops'] ?? 0 }}</div>
            <div class="stat-label">Shops</div>
        </div>
        <div class="stat-arrow">
            <i class="bi bi-arrow-right"></i>
        </div>
    </a>

    <!-- Supplements -->
    <a href="#" class="stat-card">
        <div class="stat-icon supplements">
            <i class="bi bi-capsule"></i>
        </div>
        <div class="stat-content">
            <div class="stat-value">{{ $stats['supplements'] ?? 0 }}</div>
            <div class="stat-label">Supplements</div>
        </div>
        <div class="stat-arrow">
            <i class="bi bi-arrow-right"></i>
        </div>
    </a>

    <!-- Health Food -->
    <a href="#" class="stat-card">
        <div class="stat-icon healthfood">
            <i class="bi bi-egg-fried"></i>
        </div>
        <div class="stat-content">
            <div class="stat-value">{{ $stats['healthfood'] ?? 0 }}</div>
            <div class="stat-label">Health Food</div>
        </div>
        <div class="stat-arrow">
            <i class="bi bi-arrow-right"></i>
        </div>
    </a>
</div>

<!-- Quick Actions & Recent Activity Row -->
<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem;">
    <!-- Quick Actions -->
    <div class="recent-section">
        <h3><i class="bi bi-lightning"></i> Quick Actions</h3>
        <div class="quick-actions">
            <a href="{{ route('admin.platform.clubs') }}" class="quick-action-btn">
                <i class="bi bi-plus-circle"></i>
                <span>Add New Club</span>
            </a>
            <a href="{{ route('admin.platform.members') }}" class="quick-action-btn">
                <i class="bi bi-person-plus"></i>
                <span>Add Member</span>
            </a>
            <a href="#" class="quick-action-btn">
                <i class="bi bi-calendar-plus"></i>
                <span>Create Event</span>
            </a>
            <a href="#" class="quick-action-btn">
                <i class="bi bi-database-down"></i>
                <span>Backup Now</span>
            </a>
        </div>
    </div>

    <!-- Recent Activity -->
    <div class="recent-section">
        <h3><i class="bi bi-clock-history"></i> Recent Activity</h3>
        <div style="display: flex; flex-direction: column; gap: 0.75rem;">
            <div style="display: flex; align-items: center; gap: 0.75rem; padding: 0.5rem; background: hsl(var(--muted) / 0.3); border-radius: 0.5rem;">
                <div style="width: 36px; height: 36px; background: hsl(var(--primary)); border-radius: 50%; display: flex; align-items: center; justify-content: center; color: white; font-size: 0.875rem;">
                    <i class="bi bi-person-plus"></i>
                </div>
                <div style="flex: 1;">
                    <p style="margin: 0; font-size: 0.875rem; font-weight: 500;">New member registered</p>
                    <p style="margin: 0; font-size: 0.75rem; color: hsl(var(--muted-foreground));">2 minutes ago</p>
                </div>
            </div>
            <div style="display: flex; align-items: center; gap: 0.75rem; padding: 0.5rem; background: hsl(var(--muted) / 0.3); border-radius: 0.5rem;">
                <div style="width: 36px; height: 36px; background: #11998e; border-radius: 50%; display: flex; align-items: center; justify-content: center; color: white; font-size: 0.875rem;">
                    <i class="bi bi-building"></i>
                </div>
                <div style="flex: 1;">
                    <p style="margin: 0; font-size: 0.875rem; font-weight: 500;">New club created</p>
                    <p style="margin: 0; font-size: 0.75rem; color: hsl(var(--muted-foreground));">1 hour ago</p>
                </div>
            </div>
            <div style="display: flex; align-items: center; gap: 0.75rem; padding: 0.5rem; background: hsl(var(--muted) / 0.3); border-radius: 0.5rem;">
                <div style="width: 36px; height: 36px; background: #4facfe; border-radius: 50%; display: flex; align-items: center; justify-content: center; color: white; font-size: 0.875rem;">
                    <i class="bi bi-calendar-event"></i>
                </div>
                <div style="flex: 1;">
                    <p style="margin: 0; font-size: 0.875rem; font-weight: 500;">Event scheduled</p>
                    <p style="margin: 0; font-size: 0.75rem; color: hsl(var(--muted-foreground));">3 hours ago</p>
                </div>
            </div>
        </div>
    </div>
</div>

@endsection

