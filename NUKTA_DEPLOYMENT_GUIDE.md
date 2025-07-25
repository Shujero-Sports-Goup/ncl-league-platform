# Nukta League Platform - InfinityFree Deployment Guide

## Files to Upload
Upload these folders/files to your InfinityFree htdocs folder:

### Core Files
- admin/
- api/
- assets/
- includes/
- leagues/
- manager/
- referee/
- sql/
- uploads/
- vendor/
- composer.json
- composer.lock
- db_connect.php
- generate_fixture.php
- index.php
- login.php
- logout.php
- README.md
- TEAMS.PHP

### Database Setup
1. Import sql/schema.sql to create tables
2. Run the enhanced match_results table commands
3. Update db_connect.php with InfinityFree database details

### Configuration Changes Needed
1. Update database connection in db_connect.php
2. Set proper file permissions for uploads/ folder
3. Update any localhost references to your new domain

## InfinityFree Limitations to Consider
- 5GB storage limit
- 10GB bandwidth per month
- No email support (use external email)
- Limited to 400 CPU seconds per day
- No SSL on free plan (use HTTP)

## Testing Checklist
- [ ] Database connection works
- [ ] Login system functions
- [ ] Fixture generation works
- [ ] Score submission works
- [ ] PDF exports work
- [ ] Image uploads work
- [ ] All league pages display correctly
