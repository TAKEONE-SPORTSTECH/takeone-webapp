# Setup Affiliations Enhancement

This guide will help you set up the enhanced affiliations feature with dummy data.

## Step 1: Run Migrations

Run the new migrations to enhance the database schema:

```bash
php artisan migrate
```

This will:
- Add `start_date`, `end_date`, `package_id`, `activity_id`, `instructor_id`, and `notes` columns to `skill_acquisitions` table
- Add `club_affiliation_id` column to `club_member_subscriptions` table

## Step 2: Seed Dummy Data

Run the seeder to populate affiliations data for all users:

```bash
php artisan db:seed --class=AffiliationsDataSeeder
```

This will create for each user:
- 2-4 club affiliations with realistic date ranges
- Multiple packages per affiliation
- Activities within each package
- Skills learned from each activity
- Instructor assignments for each skill
- Cross-club skill progression (skills that started in one club and continued in another)
- Affiliation media (certificates, photos)

## Step 3: View the Results

Navigate to the profile page:
```
http://127.0.0.1:8000/profile
```

Click on the **Affiliations** tab to see:
- Timeline of club memberships
- Skills wheel showing skills per club
- Detailed affiliation information including:
  - Package history
  - Activities and instructors
  - Skill acquisition timeline
  - Cross-club skill progression

## What the Seeder Creates

### For Each User:
- **2-4 Club Affiliations** spanning different time periods
- **2-3 Packages per Affiliation** with realistic start/end dates
- **2-4 Activities per Package** (Martial Arts, Boxing, Fitness classes)
- **1-3 Skills per Activity** taught by specific instructors
- **Cross-Club Skills** (25% chance) - skills that continue across different clubs
- **1-3 Media Items** per affiliation (certificates, photos, documents)

### Sample Clubs:
- Elite Martial Arts Academy (Manama)
- Champions Boxing Club (Riffa)
- Fitness First Gym (Seef)
- Warrior Taekwondo Center (Muharraq)

### Sample Skills:
**Martial Arts:**
- Taekwondo Basics, Forms, Sparring
- Boxing Fundamentals, Footwork, Combinations
- Karate Kata, Kumite
- Self-Defense Techniques
- Kickboxing, Muay Thai, Jiu-Jitsu

**Fitness:**
- Weight Training, Cardio Conditioning
- HIIT Training, CrossFit
- Functional Training, Core Strengthening
- Flexibility & Stretching
- Nutrition Planning

### Sample Instructors:
- Master Ahmed Al-Khalifa
- Coach Sarah Johnson
- Sensei Mohammed Ali
- Coach David Martinez
- Master Fatima Hassan
- And more...

## Troubleshooting

If you encounter any errors:

1. **Migration errors**: Make sure all previous migrations have run successfully
2. **Foreign key errors**: Ensure the related tables (tenants, users, club_packages, etc.) exist
3. **No users found**: Create some users first before running the seeder

## Next Steps

After seeding the data, you can:
1. View the enhanced affiliations tab
2. Test the skill timeline visualization
3. Check cross-club skill progression
4. Verify instructor information displays correctly
5. Review package history within each affiliation

## Clean Up (Optional)

To remove all seeded data and start fresh:

```bash
# This will remove all affiliations data
php artisan db:seed --class=AffiliationsDataSeeder --force
```

Or to reset the entire database:

```bash
php artisan migrate:fresh --seed
