# 🚀 Deployment Guide: "Certifié exact" Checkbox Feature

## Quick Reference

**Feature:** Add "Certifié exact" checkbox to état des lieux form and PDF
**Branch:** `copilot/add-certifie-exact-checkbox`
**Status:** ✅ Ready for deployment
**Files Changed:** 3 code files + 4 documentation files

---

## 📦 What's Included

### Code Changes (3 files)
1. `migrations/031_add_certifie_exact_to_etat_lieux_locataires.php` - Database migration
2. `admin-v2/edit-etat-lieux.php` - Form handling and display
3. `pdf/generate-etat-lieux.php` - PDF generation

### Documentation (4 files)
1. `IMPLEMENTATION_CERTIFIE_EXACT.md` - Technical implementation details
2. `VISUAL_GUIDE_CERTIFIE_EXACT.md` - Visual before/after guide
3. `PR_SUMMARY_CERTIFIE_EXACT.md` - PR summary
4. `SECURITY_SUMMARY_CERTIFIE_EXACT.md` - Security analysis
5. `DEPLOYMENT_GUIDE_CERTIFIE_EXACT.md` - This file

---

## ⚡ Quick Deployment (5 minutes)

### Step 1: Merge the PR
```bash
# From GitHub web interface:
# 1. Review the PR
# 2. Click "Merge pull request"
# 3. Confirm merge
```

### Step 2: Pull Latest Code
```bash
cd /path/to/contrat-de-bail
git checkout main
git pull origin main
```

### Step 3: Run Database Migration
```bash
php migrations/031_add_certifie_exact_to_etat_lieux_locataires.php
```

**Expected Output:**
```
Starting migration 031: Add certifie_exact to etat_lieux_locataires...
✓ Added certifie_exact column to etat_lieux_locataires table
Migration 031 completed successfully!
```

### Step 4: Verify Deployment
```bash
# Check database column was added
mysql -u root -p bail_signature -e "DESCRIBE etat_lieux_locataires;"

# Should see certifie_exact column in the output
```

### Step 5: Test in Browser
1. Navigate to: `https://your-domain.com/admin-v2/edit-etat-lieux.php?id=5`
2. Look for "Certifié exact" checkbox after signature canvas
3. Check the checkbox and save
4. Generate PDF and verify "☑ Certifié exact" appears

---

## 🔧 Detailed Deployment Steps

### Pre-Deployment Checklist
- [ ] Backup database
- [ ] Review PR changes
- [ ] Confirm staging tests passed (if applicable)
- [ ] Schedule deployment window
- [ ] Notify team of deployment

### Deployment Process

#### 1. Backup Database
```bash
mysqldump -u root -p bail_signature > backup_$(date +%Y%m%d_%H%M%S).sql
```

#### 2. Merge Code
```bash
git checkout main
git pull origin main
# Or merge via GitHub web interface
```

#### 3. Run Migration
```bash
php migrations/031_add_certifie_exact_to_etat_lieux_locataires.php
```

**If migration fails:**
- Check database connection in `includes/config.php`
- Verify MySQL is running
- Check user permissions
- Review error message

#### 4. Verify Database
```bash
mysql -u root -p bail_signature
```

```sql
-- Check column exists
SHOW COLUMNS FROM etat_lieux_locataires LIKE 'certifie_exact';

-- Expected output:
-- +----------------+---------+------+-----+---------+-------+
-- | Field          | Type    | Null | Key | Default | Extra |
-- +----------------+---------+------+-----+---------+-------+
-- | certifie_exact | tinyint | NO   |     | 0       |       |
-- +----------------+---------+------+-----+---------+-------+

-- Check table structure
DESCRIBE etat_lieux_locataires;
```

#### 5. Test Functionality

**Test 1: Form Display**
- URL: `/admin-v2/edit-etat-lieux.php?id=5`
- ✅ Checkbox appears after signature canvas
- ✅ Label reads "Certifié exact"

**Test 2: Save Functionality**
- Check the checkbox
- Click "Enregistrer"
- Reload page
- ✅ Checkbox remains checked

**Test 3: Uncheck Functionality**
- Uncheck the checkbox
- Click "Enregistrer"
- Reload page
- ✅ Checkbox is unchecked

**Test 4: PDF Generation**
- Check checkbox and save
- Generate PDF (download or view)
- ✅ "☑ Certifié exact" appears in signature section
- Uncheck and regenerate PDF
- ✅ Text does NOT appear

**Test 5: Multiple Tenants**
- Test with état des lieux having 2 tenants
- Check only one checkbox
- ✅ Only that tenant shows "☑ Certifié exact" in PDF

---

## 🔄 Rollback Procedure

If issues are discovered:

### Quick Rollback (Code Only)
```bash
git revert <commit-hash>
git push origin main
```

### Full Rollback (Code + Database)
```bash
# 1. Rollback code
git revert <commit-hash>
git push origin main

# 2. Rollback database
mysql -u root -p bail_signature -e "ALTER TABLE etat_lieux_locataires DROP COLUMN certifie_exact;"

# 3. Restore from backup if needed
mysql -u root -p bail_signature < backup_YYYYMMDD_HHMMSS.sql
```

---

## 🧪 Testing Matrix

| Test Case | Expected Result | Status |
|-----------|----------------|--------|
| Checkbox appears in form | ✅ Visible after signature | |
| Checkbox can be checked | ✅ Accepts user input | |
| Checkbox value saves | ✅ Persists in database | |
| Checkbox state reloads | ✅ Shows correct state | |
| PDF shows when checked | ✅ "☑ Certifié exact" visible | |
| PDF hides when unchecked | ✅ Text not shown | |
| Works with 1 tenant | ✅ Functions correctly | |
| Works with 2 tenants | ✅ Functions correctly | |
| Independent per tenant | ✅ Each tenant separate | |

---

## 📊 Monitoring Post-Deployment

### What to Monitor
1. **Application Logs**
   - Check for PHP errors
   - Look for database errors
   - Monitor for unexpected warnings

2. **Database Performance**
   - New column adds minimal overhead
   - No performance impact expected

3. **User Feedback**
   - Confirm users can see checkbox
   - Verify PDF generation works
   - Check for any confusion

### Log Locations
```bash
# PHP error log
tail -f /var/log/php/error.log

# Apache error log
tail -f /var/log/apache2/error.log

# Application log (if configured)
tail -f /path/to/app/logs/application.log
```

---

## ❓ Troubleshooting

### Issue: Migration fails with "Table doesn't exist"
**Solution:**
```bash
# Check if table exists
mysql -u root -p bail_signature -e "SHOW TABLES LIKE 'etat_lieux_locataires';"

# If missing, check if base migrations were run
ls -la migrations/
```

### Issue: Checkbox doesn't appear in form
**Solution:**
1. Clear browser cache (Ctrl+F5)
2. Check file was updated: `git log --oneline admin-v2/edit-etat-lieux.php`
3. Verify PHP syntax: `php -l admin-v2/edit-etat-lieux.php`

### Issue: Checkbox value doesn't save
**Solution:**
1. Check PHP error logs
2. Verify database column exists
3. Test database connection
4. Check form POST data in browser dev tools

### Issue: PDF doesn't show checkbox
**Solution:**
1. Verify checkbox was saved (check database)
2. Clear any PDF cache
3. Check file was updated: `git log --oneline pdf/generate-etat-lieux.php`
4. Test with fresh PDF generation

---

## 📞 Support

### Documentation References
- Technical details: `IMPLEMENTATION_CERTIFIE_EXACT.md`
- Visual guide: `VISUAL_GUIDE_CERTIFIE_EXACT.md`
- Security info: `SECURITY_SUMMARY_CERTIFIE_EXACT.md`
- PR summary: `PR_SUMMARY_CERTIFIE_EXACT.md`

### Common Commands
```bash
# Check migration status
ls -la migrations/031_*.php

# View database column
mysql -u root -p bail_signature -e "DESCRIBE etat_lieux_locataires;"

# Test PHP syntax
php -l admin-v2/edit-etat-lieux.php
php -l pdf/generate-etat-lieux.php

# View recent changes
git log --oneline -5
git diff HEAD~1 admin-v2/edit-etat-lieux.php
```

---

## ✅ Deployment Completion Checklist

- [ ] Code merged to main branch
- [ ] Database migration executed successfully
- [ ] Database column verified in production
- [ ] Form checkbox visible and functional
- [ ] Checkbox save/load working correctly
- [ ] PDF generation showing checkbox correctly
- [ ] Multi-tenant scenarios tested
- [ ] No errors in logs
- [ ] Team notified of deployment
- [ ] Documentation accessible
- [ ] Backup created and stored

---

## 🎉 Success Criteria

**Deployment is successful when:**
1. ✅ Migration completes without errors
2. ✅ Checkbox appears in edit form
3. ✅ Checkbox value persists
4. ✅ PDF shows "☑ Certifié exact" when checked
5. ✅ No PHP/database errors logged
6. ✅ Users can successfully use the feature

---

**Last Updated:** 2026-02-07
**Version:** 1.0
**Status:** Ready for Production
