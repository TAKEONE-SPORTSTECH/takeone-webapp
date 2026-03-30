@extends('layouts.admin')

@section('admin-content')
<div>
    <!-- Page Header -->
    <div class="mb-4">
        <h1 class="text-2xl font-bold mb-2">Audit Log</h1>
        <p class="text-muted-foreground">Every recorded action on the platform — who did what and when.</p>
    </div>

    <!-- Filters -->
    <form method="GET" action="{{ route('admin.platform.audit-log') }}">
        <div class="card border shadow-sm mb-4">
            <div class="card-body p-4">
                {{-- Search --}}
                <div class="relative mb-3">
                    <i class="bi bi-search absolute left-3 top-1/2 -translate-y-1/2 text-muted-foreground pointer-events-none"></i>
                    <input type="text" name="search" class="form-control pl-10"
                           placeholder="Search by action, model, user name or email..."
                           value="{{ $search ?? '' }}">
                </div>

                {{-- Row 2: dropdowns + date range + actions --}}
                <div class="flex flex-wrap gap-2 items-end">
                    <div class="flex flex-col gap-1">
                        <label class="text-xs text-muted-foreground font-medium">Channel</label>
                        <select name="log_name" class="form-control form-control-sm" style="min-width: 130px;">
                            <option value="">All</option>
                            @foreach($logNames as $name)
                                <option value="{{ $name }}" @selected($logName === $name)>{{ ucfirst($name) }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="flex flex-col gap-1">
                        <label class="text-xs text-muted-foreground font-medium">Event</label>
                        <select name="event" class="form-control form-control-sm" style="min-width: 110px;">
                            <option value="">All</option>
                            @foreach(['created','updated','deleted'] as $ev)
                                <option value="{{ $ev }}" @selected($event === $ev)>{{ ucfirst($ev) }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="border-start border-border mx-1 align-self-stretch d-none d-sm-block"></div>

                    <div class="flex flex-col gap-1">
                        <label class="text-xs text-muted-foreground font-medium">From</label>
                        <input type="date" name="date_from" class="form-control form-control-sm"
                               value="{{ $dateFrom ?? '' }}" style="min-width: 140px;">
                    </div>

                    <div class="flex flex-col gap-1">
                        <label class="text-xs text-muted-foreground font-medium">To</label>
                        <input type="date" name="date_to" class="form-control form-control-sm"
                               value="{{ $dateTo ?? '' }}" style="min-width: 140px;">
                    </div>

                    <div class="flex gap-2 ms-auto">
                        @if($search || $logName || $event || $dateFrom || $dateTo)
                            <a href="{{ route('admin.platform.audit-log') }}" class="btn btn-sm btn-outline-secondary">
                                <i class="bi bi-x-lg me-1"></i>Clear
                            </a>
                        @endif
                        <button type="submit" class="btn btn-sm btn-primary">
                            <i class="bi bi-funnel me-1"></i>Apply
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </form>

    <!-- Log Table -->
    @if($logs->count() > 0)
        <div class="card border shadow-sm mb-4">
            <div class="overflow-x-auto">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="text-nowrap">When</th>
                            <th class="text-nowrap">Who</th>
                            <th class="text-nowrap">Channel</th>
                            <th class="text-nowrap">Event</th>
                            <th>Description</th>
                            <th class="text-nowrap">Subject</th>
                            <th class="text-nowrap">Changes</th>
                        </tr>
                    </thead>
                    @php
                        // Human-readable labels for tracked model fields
                        $fieldLabels = [
                            'club_name'        => 'Club Name',
                            'slug'             => 'URL Slug',
                            'status'           => 'Status',
                            'enrollment_fee'   => 'Enrollment Fee',
                            'vat_percentage'   => 'VAT %',
                            'owner_user_id'    => 'Owner',
                            'email'            => 'Email',
                            'name'             => 'Name',
                            'price'            => 'Price',
                            'duration_months'  => 'Duration (months)',
                            'session_count'    => 'Sessions',
                            'age_min'          => 'Min Age',
                            'age_max'          => 'Max Age',
                            'gender'           => 'Gender',
                            'role'             => 'Role',
                            'rating'           => 'Rating',
                            'description'      => 'Description',
                            'location'         => 'Location',
                            'date'             => 'Date',
                            'start_time'       => 'Start Time',
                            'end_time'         => 'End Time',
                            'max_capacity'     => 'Max Capacity',
                            'is_archived'      => 'Archived',
                            'payment_status'   => 'Payment Status',
                            'amount_paid'      => 'Amount Paid',
                            'amount_due'       => 'Amount Due',
                            'start_date'       => 'Start Date',
                            'end_date'         => 'End Date',
                            'type'             => 'Type',
                            'category'         => 'Category',
                            'amount'           => 'Amount',
                            'transaction_date' => 'Transaction Date',
                            // Manual activity() props
                            'ip'               => 'IP Address',
                            'user_agent'       => 'Device / Browser',
                            'registrants'      => 'Registrants',
                            'pay_later'        => 'Pay Later',
                            'guardian_email'   => 'Guardian Email',
                            'people_count'     => 'People Registered',
                            'club_id'          => 'Club',
                        ];

                        // Human-readable model names
                        $modelLabels = [
                            'Tenant'                  => 'Club',
                            'ClubPackage'             => 'Package',
                            'ClubInstructor'          => 'Instructor',
                            'ClubFacility'            => 'Facility',
                            'ClubActivity'            => 'Activity',
                            'ClubEvent'               => 'Event',
                            'ClubMemberSubscription'  => 'Subscription',
                            'ClubTransaction'         => 'Transaction',
                        ];

                        // Money fields — format as currency
                        $moneyFields = ['price', 'enrollment_fee', 'amount_paid', 'amount_due', 'amount'];

                        $formatValue = function($field, $val) use ($moneyFields) {
                            if (is_null($val) || $val === '')       return 'Not set';
                            if (is_bool($val))                      return $val ? 'Yes' : 'No';
                            if ($val === 1 || $val === '1')         return 'Yes';
                            if ($val === 0 || $val === '0')         return 'No';
                            if (in_array($field, $moneyFields))     return number_format((float)$val, 2);
                            if (is_array($val))                     return implode(', ', $val);
                            // Format datetime-ish strings
                            if (preg_match('/^\d{4}-\d{2}-\d{2}T/', $val)) {
                                try { return \Carbon\Carbon::parse($val)->format('d M Y, H:i'); } catch (\Exception $e) {}
                            }
                            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $val)) {
                                try { return \Carbon\Carbon::parse($val)->format('d M Y'); } catch (\Exception $e) {}
                            }
                            return ucfirst(str_replace('_', ' ', $val));
                        };
                    @endphp
                    <tbody>
                        @foreach($logs as $log)
                            @php
                                $subjectBase  = $log->subject_type ? class_basename($log->subject_type) : null;
                                $subjectLabel = $modelLabels[$subjectBase] ?? $subjectBase;

                                $old        = $log->properties['old'] ?? [];
                                $new        = $log->properties['attributes'] ?? [];
                                $extraProps = collect($log->properties->toArray())
                                    ->except(['old', 'attributes'])
                                    ->filter();

                                $channelColors = [
                                    'auth'       => 'bg-blue-100 text-blue-700',
                                    'club'       => 'bg-purple-100 text-purple-700',
                                    'membership' => 'bg-green-100 text-green-700',
                                    'financial'  => 'bg-yellow-100 text-yellow-700',
                                    'default'    => 'bg-gray-100 text-gray-700',
                                ];
                                $channelColor = $channelColors[$log->log_name] ?? $channelColors['default'];

                                $eventColors = [
                                    'created' => 'bg-green-100 text-green-700',
                                    'updated' => 'bg-blue-100 text-blue-700',
                                    'deleted' => 'bg-red-100 text-red-700',
                                ];
                                $eventColor = $eventColors[$log->event] ?? 'bg-gray-100 text-gray-600';
                            @endphp
                            <tr>
                                {{-- When --}}
                                <td class="text-nowrap align-top">
                                    <span class="text-sm">{{ $log->created_at->format('d M Y') }}</span><br>
                                    <span class="text-xs text-muted-foreground">{{ $log->created_at->format('H:i:s') }}</span>
                                </td>

                                {{-- Who --}}
                                <td class="align-top">
                                    @if($log->causer)
                                        <span class="text-sm font-medium">{{ $log->causer->full_name }}</span><br>
                                        <span class="text-xs text-muted-foreground">{{ $log->causer->email }}</span>
                                    @else
                                        <span class="text-xs text-muted-foreground">System</span>
                                    @endif
                                </td>

                                {{-- Channel --}}
                                <td class="align-top">
                                    <span class="px-2 py-0.5 rounded text-xs font-medium {{ $channelColor }}">
                                        {{ ucfirst($log->log_name ?? '—') }}
                                    </span>
                                </td>

                                {{-- Event --}}
                                <td class="align-top">
                                    @if($log->event)
                                        <span class="px-2 py-0.5 rounded text-xs font-medium {{ $eventColor }}">
                                            {{ ucfirst($log->event) }}
                                        </span>
                                    @else
                                        <span class="text-xs text-muted-foreground">—</span>
                                    @endif
                                </td>

                                {{-- Description --}}
                                <td class="align-top">
                                    <span class="text-sm">{{ $log->description }}</span>
                                </td>

                                {{-- Subject --}}
                                <td class="align-top text-nowrap">
                                    @if($subjectLabel)
                                        <span class="text-sm font-medium">{{ $subjectLabel }}</span>
                                        @if($log->subject_id)
                                            <br><span class="text-xs text-muted-foreground">#{{ $log->subject_id }}</span>
                                        @endif
                                    @else
                                        <span class="text-xs text-muted-foreground">—</span>
                                    @endif
                                </td>

                                {{-- Changes --}}
                                <td class="align-top" style="max-width: 300px;">
                                    @if(!empty($old) || !empty($new))
                                        <div class="space-y-1.5">
                                            @foreach($new as $field => $newVal)
                                                @php
                                                    $oldVal   = $old[$field] ?? null;
                                                    $label    = $fieldLabels[$field] ?? ucwords(str_replace('_', ' ', $field));
                                                    $fmtNew   = $formatValue($field, $newVal);
                                                    $fmtOld   = $formatValue($field, $oldVal);
                                                    $changed  = $oldVal !== null && $oldVal !== $newVal;
                                                @endphp
                                                <div class="text-xs">
                                                    <span class="font-semibold text-foreground">{{ $label }}</span>
                                                    @if($changed)
                                                        <div class="mt-0.5 flex items-center gap-1 flex-wrap">
                                                            <span class="px-1.5 py-0.5 rounded bg-red-50 text-red-600 line-through">{{ $fmtOld }}</span>
                                                            <i class="bi bi-arrow-right text-muted-foreground"></i>
                                                            <span class="px-1.5 py-0.5 rounded bg-green-50 text-green-700 font-medium">{{ $fmtNew }}</span>
                                                        </div>
                                                    @else
                                                        <span class="text-muted-foreground ms-1">{{ $fmtNew }}</span>
                                                    @endif
                                                </div>
                                            @endforeach
                                        </div>
                                    @elseif($extraProps->isNotEmpty())
                                        <div class="space-y-1.5">
                                            @foreach($extraProps as $key => $val)
                                                @php
                                                    $label  = $fieldLabels[$key] ?? ucwords(str_replace('_', ' ', $key));
                                                    $fmtVal = $formatValue($key, $val);
                                                @endphp
                                                <div class="text-xs">
                                                    <span class="font-semibold text-foreground">{{ $label }}:</span>
                                                    <span class="text-muted-foreground ms-1">{{ $fmtVal }}</span>
                                                </div>
                                            @endforeach
                                        </div>
                                    @else
                                        <span class="text-xs text-muted-foreground">—</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Pagination -->
        <div class="flex justify-between items-center mb-4">
            <p class="text-sm text-muted-foreground mb-0">
                Showing {{ $logs->firstItem() }}–{{ $logs->lastItem() }} of {{ number_format($logs->total()) }} entries
            </p>
            {{ $logs->links() }}
        </div>

    @else
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-body text-center py-12">
                <i class="bi bi-journal-text text-muted-foreground text-6xl"></i>
                <h5 class="mt-3 mb-2">No Log Entries Found</h5>
                <p class="text-muted-foreground mb-0">
                    @if($search || $logName || $event)
                        No entries match your filters.
                    @else
                        No activity has been recorded yet.
                    @endif
                </p>
            </div>
        </div>
    @endif
</div>
@endsection
