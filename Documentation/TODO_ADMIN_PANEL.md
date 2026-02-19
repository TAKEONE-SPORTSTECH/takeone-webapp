# TAKEONE Admin Panel Implementation TODO

## Project Overview
Building a comprehensive Laravel-based admin panel for multi-club sports management platform.

**Tech Stack:** Laravel 12, Blade Templates, Bootstrap 5, Tailwind CSS, PostgreSQL/MySQL
**Start Date:** 2026-01-25
**Status:** In Progress

---

## Phase 1: Database Schema Expansion ⏳

### Core Admin Tables
- [ ] Create roles and permissions tables migration
- [ ] Expand tenants (clubs) table with all required fields
- [ ] Create club facilities table
- [ ] Create club instructors table
- [ ] Create club activities table
- [ ] Create club packages table
- [ ] Create club package activities (pivot) table
- [ ] Create club member subscriptions table
- [ ] Create club transactions table
- [ ] Create club gallery images table
- [ ] Create club social links table
- [ ] Create club bank accounts table (encrypted)
- [ ] Create club messages table
- [ ] Create club reviews table
- [ ] Create club settings table

---

## Phase 2: Models & Relationships

### Eloquent Models
- [ ] Role model
- [ ] Permission model
- [ ] ClubFacility model
- [ ] ClubInstructor model
- [ ] ClubActivity model
- [ ] ClubPackage model
- [ ] ClubMemberSubscription model
- [ ] ClubTransaction model
- [ ] ClubGalleryImage model
- [ ] ClubSocialLink model
- [ ] ClubBankAccount model
- [ ] ClubMessage model
- [ ] ClubReview model
- [ ] ClubSettings model
- [ ] Update Tenant model with new relationships
- [ ] Update User model with admin relationships

---

## Phase 3: Role-Based Access Control (RBAC)

### Authorization System
- [ ] Create role middleware
- [ ] Create permission middleware
- [ ] Create policy classes for all models
- [ ] Create role seeder (Super Admin, Club Admin, Instructor, Member)
- [ ] Create permission seeder
- [ ] Add helper functions for role checking
- [ ] Update User model with role methods

---

## Phase 4: Platform-Level Admin (PRIORITY)

### Routes
- [ ] Platform admin routes group
- [ ] All clubs management routes
- [ ] All members management routes
- [ ] Database backup/restore routes

### Controllers
- [ ] Admin\PlatformController
  - [ ] Dashboard overview
  - [ ] Statistics and analytics
- [ ] Admin\ClubManagementController
  - [ ] Index (all clubs grid)
  - [ ] Create new club
  - [ ] Edit club
  - [ ] Delete club
  - [ ] Search/filter clubs
- [ ] Admin\MemberManagementController
  - [ ] Index (all members grid)
  - [ ] Create member
  - [ ] Edit member
  - [ ] Delete member
  - [ ] Search/filter members
  - [ ] Add child member
- [ ] Admin\BackupController
  - [ ] Download database backup (JSON)
  - [ ] Restore database from backup
  - [ ] Export auth users

### Views (Blade Templates)
- [ ] layouts/admin.blade.php (platform admin layout)
- [ ] admin/dashboard.blade.php
- [ ] admin/clubs/index.blade.php (grid view)
- [ ] admin/clubs/create.blade.php
- [ ] admin/clubs/edit.blade.php
- [ ] admin/members/index.blade.php (grid view)
- [ ] admin/members/create.blade.php
- [ ] admin/members/edit.blade.php
- [ ] admin/backup/index.blade.php

---

## Phase 5: Club-Level Admin Dashboard

### Routes
- [ ] Club admin routes group (/club/{slug}/admin)
- [ ] Dashboard routes
- [ ] Club details routes (multi-tab)
- [ ] Gallery routes
- [ ] Facilities routes
- [ ] Instructors routes
- [ ] Activities routes
- [ ] Packages routes
- [ ] Members routes
- [ ] Financials routes
- [ ] Messages routes
- [ ] Analytics routes

### Controllers
- [ ] Club\DashboardController
- [ ] Club\ClubDetailsController
- [ ] Club\GalleryController
- [ ] Club\FacilityController
- [ ] Club\InstructorController
- [ ] Club\ActivityController
- [ ] Club\PackageController
- [ ] Club\MemberController
- [ ] Club\FinancialController
- [ ] Club\MessageController
- [ ] Club\AnalyticsController

### Views (Blade Templates)
- [ ] layouts/club-admin.blade.php (with sidebar)
- [ ] club/admin/dashboard.blade.php
- [ ] club/admin/details/index.blade.php (tabs)
- [ ] club/admin/gallery/index.blade.php
- [ ] club/admin/facilities/index.blade.php
- [ ] club/admin/instructors/index.blade.php
- [ ] club/admin/activities/index.blade.php
- [ ] club/admin/packages/index.blade.php
- [ ] club/admin/members/index.blade.php
- [ ] club/admin/financials/index.blade.php
- [ ] club/admin/messages/index.blade.php
- [ ] club/admin/analytics/index.blade.php

---

## Phase 6: Core Features Implementation

### Multi-Currency Support
- [ ] Create currency dropdown component
- [ ] Add currency helper functions
- [ ] Store currency in club settings
- [ ] Display prices in club currency

### Multi-Timezone Support
- [ ] Create timezone dropdown component
- [ ] Add timezone helper functions
- [ ] Store timezone in club settings
- [ ] Display times in club timezone

### File Upload & Management
- [ ] Configure storage (S3 or local)
- [ ] Image optimization on upload
- [ ] Gallery management CRUD
- [ ] File deletion handling

### Financial System
- [ ] Transaction ledger view
- [ ] Income tracking
- [ ] Expense tracking
- [ ] Refund handling
- [ ] Financial reports
- [ ] CSV export functionality
- [ ] Charts (Chart.js integration)

### Backup & Restore
- [ ] JSON export of all tables
- [ ] Secure backup storage
- [ ] Restore functionality
- [ ] Validation on restore
- [ ] Export auth users with encrypted passwords

### Analytics Dashboard
- [ ] Member growth chart
- [ ] Revenue trends chart
- [ ] Package popularity metrics
- [ ] Activity attendance rates
- [ ] Instructor performance metrics
- [ ] Member retention rates
- [ ] Financial health indicators

### Messaging System
- [ ] Inbox/conversation list
- [ ] Message thread view
- [ ] Send message functionality
- [ ] Mark as read/unread
- [ ] Notification integration

---

## Phase 7: Additional Features

### Club Details Management
- [ ] Basic information tab
- [ ] Contact information tab
- [ ] Location & GPS tab (with map)
- [ ] Branding assets tab
- [ ] Social media links tab
- [ ] Banking tab (encrypted)
- [ ] Settings tab (code prefixes)
- [ ] Danger zone (delete club)

### Gallery Management
- [ ] Grid display of images
- [ ] Multiple image upload
- [ ] Lightbox view
- [ ] Delete images
- [ ] Image captions

### Facilities Management
- [ ] Card-based layout
- [ ] Add facility
- [ ] Edit facility
- [ ] Delete facility
- [ ] GPS location on map
- [ ] Availability status

### Instructors Management
- [ ] Card layout
- [ ] Add instructor
- [ ] Edit instructor
- [ ] Delete instructor
- [ ] Skills/specialties tags
- [ ] Rating system
- [ ] Search/filter

### Activities Management
- [ ] Grid of activity cards
- [ ] Add activity
- [ ] Edit activity
- [ ] Delete activity
- [ ] Duplicate activity
- [ ] Schedule management
- [ ] Facility assignment

### Packages Management
- [ ] Large card layout
- [ ] Add package
- [ ] Edit package
- [ ] Delete package
- [ ] Duplicate package
- [ ] Single/Multi activity types
- [ ] Age range configuration
- [ ] Price management
- [ ] Activity inclusion

### Members Management
- [ ] Current members tab
- [ ] Pending requests tab
- [ ] Status filters
- [ ] Add existing user
- [ ] Walk-in registration
- [ ] Member invitation system
- [ ] Subscription management

---

## Phase 8: Components & Reusables

### Blade Components
- [ ] Country dropdown component
- [ ] Currency dropdown component
- [ ] Timezone dropdown component
- [ ] Phone code dropdown component
- [ ] Nationality dropdown component
- [ ] Image upload modal component
- [ ] Confirmation modal component
- [ ] Stats card component
- [ ] Chart component wrapper

---

## Phase 9: Testing & Quality Assurance

### Tests
- [ ] Feature tests for platform admin
- [ ] Feature tests for club admin
- [ ] Policy tests
- [ ] Model tests
- [ ] Controller tests
- [ ] Validation tests

### Seeders
- [ ] Role and permission seeder
- [ ] Demo clubs seeder
- [ ] Demo members seeder
- [ ] Demo packages seeder
- [ ] Demo transactions seeder

### Code Quality
- [ ] Run Laravel Pint (code formatting)
- [ ] Security audit (CSRF, XSS, SQL injection)
- [ ] Performance optimization (caching, eager loading)
- [ ] Database indexing
- [ ] Query optimization

---

## Phase 10: Documentation & Deployment

### Documentation
- [ ] API documentation
- [ ] Admin user guide
- [ ] Installation guide
- [ ] Deployment guide
- [ ] Database schema documentation

### Deployment Preparation
- [ ] Environment configuration
- [ ] Storage setup
- [ ] Backup automation
- [ ] SSL certificates
- [ ] Performance monitoring
- [ ] Error logging

---

## Current Progress

**Phase 1:** Not Started
**Phase 2:** Not Started
**Phase 3:** Not Started
**Phase 4:** Not Started (PRIORITY)
**Overall:** 0% Complete

---

## Notes

- Using Blade templates (not React) for consistency with existing codebase
- Bootstrap 5 + custom soft purple theme
- All club-specific tables have tenant_id foreign key
- Soft deletes on important records
- Encrypted storage for bank account information
- Multi-currency support with BHD as default
- Multi-timezone handling
- File upload with image optimization
- Role-based access control throughout

---

## Next Steps

1. Create all database migrations (Phase 1)
2. Create all Eloquent models (Phase 2)
3. Implement RBAC system (Phase 3)
4. Build platform-level admin (Phase 4) - PRIORITY
5. Continue with remaining phases

---

**Last Updated:** 2026-01-25
