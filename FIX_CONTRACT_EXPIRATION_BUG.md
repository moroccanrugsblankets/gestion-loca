# Fix: Contract Expiration Validation Bug

## Problem Statement

Users were receiving "Ce lien a expiré" (This link has expired) error messages when accessing contract signature links, even though the expiration date was still in the future.

### Example from Bug Report

**URL**: https://contrat.myinvest-immobilier.com/signature/index.php?token=52fd62663fe41f79429bf4b82a90c93dda927dce89e4363badd2f11c8d1f6bf5

**Error Message**: "Ce lien a expiré. Il était valide jusqu'au 02/02/2026 à 00:45"

**Debug Output**:
```
[01-Feb-2026 11:33:20] DEBUG: Contract ID: 1
[01-Feb-2026 11:33:20] DEBUG: Contract status: disponible
[01-Feb-2026 11:33:20] DEBUG: Expiration date: 2026-02-02 00:45:26
[01-Feb-2026 11:33:20] DEBUG: Current time: 2026-02-01 11:33:20
[01-Feb-2026 11:33:20] DEBUG: Expiration timestamp: 1769989526
[01-Feb-2026 11:33:20] DEBUG: Current timestamp: 1769942000
```

**Analysis**:
- Expiration timestamp: 1769989526
- Current timestamp: 1769942000
- Difference: 47,526 seconds ≈ 13.2 hours remaining
- **The link should be VALID, but it was being rejected!**

## Root Cause

The issue was a **SQL column name collision** in the `getContractByToken()` function in `includes/functions.php`.

### The Problematic Query

```php
function getContractByToken($token) {
    $sql = "SELECT c.*, l.* 
            FROM contrats c 
            INNER JOIN logements l ON c.logement_id = l.id 
            WHERE c.token_signature = ?";
    return fetchOne($sql, [$token]);
}
```

### Why This Caused the Bug

Both the `contrats` and `logements` tables have a `statut` column:

| Table | Column | Valid Values |
|-------|--------|--------------|
| `contrats` | `statut` | `en_attente`, `signe`, `expire`, `annule`, `actif`, `termine` |
| `logements` | `statut` | `disponible`, `en_location`, `maintenance`, `indisponible` |

When selecting `c.*, l.*` in that order:
1. First, all columns from `contrats` are selected (including `c.statut = 'en_attente'`)
2. Then, all columns from `logements` are selected (including `l.statut = 'disponible'`)
3. In PHP's result array, **`l.statut` overwrites `c.statut`** because they have the same key name
4. The contract ends up with `statut = 'disponible'` instead of `statut = 'en_attente'`

### Why Validation Failed

The `isContractValid()` function checks:

```php
$validStatuses = ['en_attente'];
if (!in_array($contract['statut'], $validStatuses)) {
    return false;  // ← Contract with 'disponible' fails here
}
```

Since the contract had `statut = 'disponible'` (from the logement, not the contrat), it failed validation even though:
- The actual contract status in the database was `en_attente`
- The expiration date was still in the future

## Solution

Changed the SELECT order in `getContractByToken()` to ensure contract columns take precedence:

```php
function getContractByToken($token) {
    // Note: Select l.* first, then c.* to ensure contract fields (especially statut) 
    // take precedence over logement fields in case of column name collisions
    $sql = "SELECT l.*, c.* 
            FROM contrats c 
            INNER JOIN logements l ON c.logement_id = l.id 
            WHERE c.token_signature = ?";
    return fetchOne($sql, [$token]);
}
```

Now:
1. All columns from `logements` are selected first
2. Then all columns from `contrats` are selected
3. **`c.statut` overwrites `l.statut`** in the result array
4. The contract correctly has `statut = 'en_attente'`

## Testing

### Unit Test Results

```
Test: Bug Report Case - Contract with 'disponible' status...
  Status: disponible
  Result: INVALID
  Expected: INVALID (status 'disponible' is not allowed)
  PASS ✓

Test: After Fix - Same contract with 'en_attente' status...
  Status: en_attente
  Expiration: 2026-02-02 00:45:26
  Result: VALID
  Expected: VALID
  PASS ✓
```

### Why This Fix Works

1. **Minimal Change**: Only swaps the order of `l.*` and `c.*` in the SELECT clause
2. **Backward Compatible**: All the same columns are returned, just with correct precedence
3. **No Schema Changes**: Doesn't require database migrations
4. **Fixes the Root Cause**: Ensures contract columns are authoritative

## Impact

- Users with valid contracts (status `en_attente`, future expiration) will no longer see false expiration errors
- All existing functionality remains unchanged
- No breaking changes

## Files Modified

1. **includes/functions.php** (Line 115)
   - Changed `SELECT c.*, l.*` to `SELECT l.*, c.*`
   - Added explanatory comment

## Security Review

- ✅ Code review: No issues found
- ✅ Security scan: No vulnerabilities detected
- ✅ No SQL injection risks (still uses prepared statements)
- ✅ No authentication/authorization changes

## Deployment Notes

This fix can be deployed immediately:
1. No database migrations required
2. No configuration changes needed
3. No service restart required (PHP processes changes on next request)
4. Safe to deploy during business hours

## Monitoring

After deployment, verify:
- Contract links with future expiration dates work correctly
- Debug logs show correct contract status (`en_attente` for unsigned contracts)
- No increase in expiration-related support tickets
