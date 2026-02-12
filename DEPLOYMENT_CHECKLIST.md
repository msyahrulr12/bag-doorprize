# Pre-Deployment Checklist

## ✅ Code Quality Checks Completed

### 1. Debug Code Removed

- ✅ No `dd()` statements found
- ✅ No `var_dump()` statements found
- ✅ No `dump()` statements found
- ✅ No `Ray::` debug calls found
- ✅ Removed commented `// dd($result);` from `app/Helper/PdfHelper.php`

### 2. Placeholder Content

- ✅ No "Lorem ipsum" text found
- ✅ No "test@test" email addresses found
- ✅ No "dummy" data found

### 3. Development Markers

- ✅ No TODO comments found
- ✅ No FIXME comments found

### 4. Configuration Files

- ✅ `.env.example` properly configured
- ✅ `APP_ENV` defaults to 'production' in `config/app.php`
- ✅ `APP_DEBUG` defaults to false in `config/app.php`
- ✅ Timezone set to 'Asia/Jakarta'

### 5. Security Checks

- ✅ No hardcoded localhost URLs in application code
- ✅ No hardcoded example.com URLs in application code
- ✅ PDF encryption uses environment variables (`PDF_OWNER_PASSWORD`, `PDF_USER_PERMISSIONS`)
- ⚠️ Public draw routes (`/draw/{uuid}`, `/draw-bulk/{uuid}`) are accessible without authentication
    - This appears intentional for public drawing events
    - Ensure UUIDs are properly secured and not guessable

### 6. Database Migrations

- ✅ All migrations are clean and ready
- ⚠️ Migration `2026_02_12_111001_change_id_data_type_on_participants_table.php` has been fixed
- ⚠️ Performance indexes migration removed (had compatibility issues with newer Laravel/PostgreSQL)

## 📋 Recommended Performance Indexes

For optimal performance with millions of records in `ParticipantTable`, manually create these indexes in production:

```sql
-- Participants table indexes
CREATE INDEX IF NOT EXISTS participants_event_id_account_id_index
ON participants (event_id, account_id);

CREATE INDEX IF NOT EXISTS participants_account_id_index
ON participants (account_id);

-- Lottery tickets table indexes
CREATE INDEX IF NOT EXISTS lottery_tickets_participant_id_status_index
ON lottery_tickets (participant_id, status);

-- Event participant pivot table indexes
CREATE INDEX IF NOT EXISTS event_participant_event_id_participant_id_index
ON event_participant (event_id, participant_id);

-- Accounts table indexes
CREATE INDEX IF NOT EXISTS accounts_branch_id_index
ON accounts (branch_id);
```

## ⚙️ Environment Variables to Set

Ensure these are properly configured in production `.env`:

```env
APP_NAME="Bag Doorprize"
APP_ENV=production
APP_DEBUG=false
APP_URL=https://your-production-domain.com

# Database
DB_CONNECTION=pgsql
DB_HOST=your-db-host
DB_PORT=5432
DB_DATABASE=your-db-name
DB_USERNAME=your-db-user
DB_PASSWORD=your-secure-password

# Queue (important for bulk drawing)
QUEUE_CONNECTION=database

# Cache
CACHE_STORE=database

# Session
SESSION_DRIVER=database
SESSION_LIFETIME=120

# Mail
MAIL_MAILER=smtp
MAIL_HOST=your-smtp-host
MAIL_PORT=587
MAIL_USERNAME=your-email
MAIL_PASSWORD=your-email-password
MAIL_FROM_ADDRESS="noreply@yourdomain.com"
MAIL_FROM_NAME="${APP_NAME}"

# PDF Security
PDF_OWNER_PASSWORD=your-secure-owner-password
PDF_USER_PERMISSIONS=print

# Octane (if using)
OCTANE_SERVER=swoole
```

## 🚀 Deployment Steps

1. **Run migrations**:

    ```bash
    php artisan migrate --force
    ```

2. **Run seeders** (if fresh install):

    ```bash
    php artisan db:seed --class=BranchSeeder
    php artisan db:seed --class=SettingSeeder
    php artisan db:seed --class=ProductSeeder
    ```

3. **Create performance indexes** (copy SQL from above)

4. **Clear and cache config**:

    ```bash
    php artisan config:cache
    php artisan route:cache
    php artisan view:cache
    ```

5. **Start queue worker**:

    ```bash
    php artisan queue:work --queue=tickets,imports,draws --daemon
    ```

6. **Start Octane** (if using):
    ```bash
    php artisan octane:start --workers=4 --port=8002
    ```

## ⚠️ Important Notes

1. **Queue Worker**: The bulk drawing feature REQUIRES a queue worker to be running. Without it, bulk draws will fail.

2. **Draw Sessions**: Ensure draw sessions are properly configured with correct start/end times before going live.

3. **Settings**: Review all settings in the admin panel, especially:
    - `activate_re_draw_and_confirm` - Controls if winners need manual confirmation
    - `draw_delay` - Delay in seconds for drawing animations
    - `region_weights` - Regional distribution weights for winner selection
    - `base_point_ntb` - Base points for new-to-bank customers
    - `point_sub_month` - Point reduction per month
    - `threshold_reduction_balance` - Balance threshold for point reduction

4. **Branch Data**: Ensure all branch data is properly seeded before importing customers.

5. **File Permissions**: Ensure storage directories are writable:
    ```bash
    chmod -R 775 storage bootstrap/cache
    chown -R www-data:www-data storage bootstrap/cache
    ```

## 🔍 Testing Checklist

Before going live, test:

- [ ] Admin login and authentication
- [ ] Customer import (NTB/ETB CSV files)
- [ ] Lottery ticket generation
- [ ] Single winner drawing (Grand Drawing)
- [ ] Bulk winner drawing
- [ ] Winner confirmation workflow
- [ ] PDF generation (bank statements)
- [ ] Export functionality (CSV, Excel, PDF)
- [ ] Public drawing pages (with valid UUIDs)
- [ ] Email notifications (if configured)
- [ ] Approval workflow (if enabled)

## 📊 Performance Optimizations Applied

1. **ParticipantTable Widget**:
    - Removed eager loading of `lotteryTickets` collection
    - Used `withCount()` instead of `counts()` relationship
    - Optimized total points calculation with database aggregates
    - Improved inactive event query with subquery instead of `whereIntegerInRaw`
    - Added pagination limits (default 25 per page)
    - Specified searchable columns explicitly

2. **Bulk Drawing**:
    - Uses background jobs for processing
    - Implements progressive loading during drawing
    - Proper status tracking and cancellation support

## ✅ Final Status

**Code is PRODUCTION READY** with the following minor notes:

- All debug code removed
- No dummy/placeholder content
- Security configurations use environment variables
- Performance optimizations implemented
- Database indexes documented for manual creation

**Action Required**:

1. Create the performance indexes manually in production database
2. Configure all environment variables
3. Test the complete workflow in staging before production deployment
