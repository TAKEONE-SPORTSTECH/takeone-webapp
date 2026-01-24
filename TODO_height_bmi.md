# TODO: Add Height Field to Health Records with Auto BMI Calculation

## Task
Add height field to health record modals (add and edit/update) with auto BMI calculation when height is set. BMI calculation: weight(kg) / (height(m)^2). If height not set, BMI not calculated.

## Current State
- health_records table needs height column
- modals in resources/views/family/show.blade.php need height input and BMI auto-calc JS

## Required Changes
1. ✅ Database Migration: Add height column to health_records table
2. ✅ Model Updates: Add height to fillable and casts in HealthRecord model
3. ✅ Controller Validation: Add height validation to storeHealth and updateHealth methods
4. ✅ View Updates: Add height input to add/edit modals, update table headers and rows
5. ✅ JavaScript Updates: Add BMI auto-calculation, update modal reset/populate functions, update comparison table and radar chart
6. ✅ Test the functionality

## Files Modified
- database/migrations/2026_01_24_084323_add_height_to_health_records_table.php
- app/Models/HealthRecord.php
- app/Http/Controllers/FamilyController.php
- resources/views/family/show.blade.php

## Followup Steps
- Run the application and navigate to /profile
- Switch to Health tab
- Test adding/editing health records with height and weight
- Verify BMI auto-calculates correctly
- Check comparison table and radar chart include height
