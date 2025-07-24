# InfinityFree Upload Instructions

## Method 1: File Manager (Recommended)
1. Login to your InfinityFree control panel
2. Click "File Manager"
3. Navigate to `htdocs` folder
4. Upload all files/folders from your local project
5. Extract if uploaded as ZIP

## Method 2: FTP Upload
1. Download FileZilla or similar FTP client
2. Connect using your FTP credentials:
   - Host: files.infinityfree.com
   - Username: (from your control panel)
   - Password: (from your control panel)
3. Upload all files to `/htdocs/` folder

## Files to Upload (in order):
1. Upload `vendor/` folder first (contains dependencies)
2. Upload `assets/` folder (CSS, JS, images)
3. Upload `includes/` folder (common PHP files)
4. Upload all other folders and files
5. Upload `uploads/` folder (make sure it's writable)

## After Upload:
1. Go to your InfinityFree domain in browser
2. Should see your NCL League Platform
3. Test login with: admin / admin123
4. Test referee login with: referee / referee123

## Common Issues:
- If CSS/JS not loading: Check file paths in includes/header.php
- If database errors: Update db_connect.php with correct details
- If uploads not working: Set uploads/ folder permissions to 755
