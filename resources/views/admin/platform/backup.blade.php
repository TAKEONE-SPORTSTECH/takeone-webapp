@extends('layouts.admin')

@section('admin-content')
<div>
    <!-- Page Header -->
    <div class="mb-4">
        <h1 class="h2 fw-bold mb-2">Database Backup & Restore</h1>
        <p class="text-muted">Manage platform database backups</p>
    </div>

    <!-- Warning Message -->
    <div class="alert alert-warning d-flex align-items-center mb-4" role="alert">
        <i class="bi bi-exclamation-triangle-fill me-3" style="font-size: 1.5rem;"></i>
        <div>
            <strong>Important:</strong> Database backup and restore operations are powerful tools. Always test backups in a safe environment before using them in production.
        </div>
    </div>

    <!-- Operations Grid -->
    <div class="row g-4 mb-4">
        <!-- Download Backup -->
        <div class="col-md-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body text-center">
                    <div class="mb-3">
                        <i class="bi bi-download text-primary" style="font-size: 3rem;"></i>
                    </div>
                    <h5 class="card-title">Download Backup</h5>
                    <p class="text-muted small mb-4">
                        Export the complete database as a JSON file. This includes all tables from the public schema.
                    </p>
                    <a href="{{ route('admin.platform.backup.download') }}" class="btn btn-primary w-100" onclick="return confirm('This will download a complete backup of the database. Continue?')">
                        <i class="bi bi-download me-2"></i>Download Full Backup
                    </a>
                    <small class="text-muted d-block mt-3">
                        <i class="bi bi-info-circle me-1"></i>File format: JSON
                    </small>
                </div>
            </div>
        </div>

        <!-- Restore Database -->
        <div class="col-md-4">
            <div class="card border-0 shadow-sm h-100 border-danger">
                <div class="card-body text-center">
                    <div class="mb-3">
                        <i class="bi bi-arrow-clockwise text-danger" style="font-size: 3rem;"></i>
                    </div>
                    <h5 class="card-title text-danger">Restore Database</h5>
                    <p class="text-muted small mb-4">
                        Upload a JSON backup file to restore the database. <strong class="text-danger">This will overwrite all existing data!</strong>
                    </p>
                    <button type="button" class="btn btn-danger w-100" data-bs-toggle="modal" data-bs-target="#restoreModal">
                        <i class="bi bi-arrow-clockwise me-2"></i>Restore from Backup
                    </button>
                    <small class="text-danger d-block mt-3">
                        <i class="bi bi-exclamation-triangle me-1"></i>Use with extreme caution
                    </small>
                </div>
            </div>
        </div>

        <!-- Export Auth Users -->
        <div class="col-md-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body text-center">
                    <div class="mb-3">
                        <i class="bi bi-people text-success" style="font-size: 3rem;"></i>
                    </div>
                    <h5 class="card-title">Export Auth Users</h5>
                    <p class="text-muted small mb-4">
                        Download all authentication users with encrypted passwords for migration purposes.
                    </p>
                    <a href="{{ route('admin.platform.backup.export-users') }}" class="btn btn-success w-100">
                        <i class="bi bi-download me-2"></i>Export Users
                    </a>
                    <small class="text-muted d-block mt-3">
                        <i class="bi bi-info-circle me-1"></i>Includes encrypted passwords
                    </small>
                </div>
            </div>
        </div>
    </div>

    <!-- Best Practices -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-white border-0">
            <h5 class="mb-0"><i class="bi bi-lightbulb me-2"></i>Best Practices</h5>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-6">
                    <h6 class="text-primary mb-3">Backup Guidelines</h6>
                    <ul class="list-unstyled">
                        <li class="mb-2">
                            <i class="bi bi-check-circle text-success me-2"></i>
                            Schedule regular automated backups (daily recommended)
                        </li>
                        <li class="mb-2">
                            <i class="bi bi-check-circle text-success me-2"></i>
                            Store backups in multiple secure locations
                        </li>
                        <li class="mb-2">
                            <i class="bi bi-check-circle text-success me-2"></i>
                            Test backup restoration in a staging environment
                        </li>
                        <li class="mb-2">
                            <i class="bi bi-check-circle text-success me-2"></i>
                            Keep backups for at least 30 days
                        </li>
                        <li class="mb-2">
                            <i class="bi bi-check-circle text-success me-2"></i>
                            Document your backup and restore procedures
                        </li>
                    </ul>
                </div>
                <div class="col-md-6">
                    <h6 class="text-danger mb-3">Restore Warnings</h6>
                    <ul class="list-unstyled">
                        <li class="mb-2">
                            <i class="bi bi-exclamation-triangle text-danger me-2"></i>
                            Always backup current data before restoring
                        </li>
                        <li class="mb-2">
                            <i class="bi bi-exclamation-triangle text-danger me-2"></i>
                            Verify backup file integrity before restoration
                        </li>
                        <li class="mb-2">
                            <i class="bi bi-exclamation-triangle text-danger me-2"></i>
                            Test restore in staging environment first
                        </li>
                        <li class="mb-2">
                            <i class="bi bi-exclamation-triangle text-danger me-2"></i>
                            Notify all users before performing restore
                        </li>
                        <li class="mb-2">
                            <i class="bi bi-exclamation-triangle text-danger me-2"></i>
                            Restoration will overwrite ALL existing data
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Restore Modal -->
<div class="modal fade" id="restoreModal" tabindex="-1" aria-labelledby="restoreModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title" id="restoreModalLabel">
                    <i class="bi bi-exclamation-triangle me-2"></i>Restore Database
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="{{ route('admin.platform.backup.restore') }}" method="POST" enctype="multipart/form-data" onsubmit="return confirmRestore()">
                @csrf
                <div class="modal-body">
                    <div class="alert alert-danger">
                        <strong>Warning:</strong> This action will permanently delete all current data and replace it with the backup file contents. This cannot be undone!
                    </div>

                    <div class="mb-3">
                        <label for="backup_file" class="form-label">Select Backup File (JSON)</label>
                        <input type="file" class="form-control" id="backup_file" name="backup_file" accept=".json" required>
                        <small class="text-muted">Only JSON backup files are accepted</small>
                    </div>

                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="confirmRestore" required>
                        <label class="form-check-label" for="confirmRestore">
                            I understand that this will overwrite all existing data
                        </label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger">
                        <i class="bi bi-arrow-clockwise me-2"></i>Restore Database
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

@push('scripts')
<script>
function confirmRestore() {
    return confirm('FINAL WARNING: Are you absolutely sure you want to restore the database? This will delete ALL current data!');
}
</script>
@endpush
@endsection
