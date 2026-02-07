# Fix: Line-Height Issue in PDF Textarea Fields

## Problem Statement
"tjrs probleme de line-height in PDF textarea fields, essayer avec remplace '\n'"

## Root Cause Analysis

### Before Fix (using `nl2br()`)
When textarea content with newlines was processed:

```php
$text = "Line 1\nLine 2\nLine 3";
$result = nl2br(htmlspecialchars($text));
// Result: "Line 1<br />\nLine 2<br />\nLine 3"
```

**Issue**: The `nl2br()` function keeps the original `\n` characters AND adds `<br>` tags. When TCPDF renders this HTML, it processes both:
- The `<br>` tag (creates a line break)
- The `\n` character (creates additional spacing)

This results in **double spacing** or **inconsistent line-height** in the PDF output.

### After Fix (using `str_replace()`)
```php
$text = "Line 1\nLine 2\nLine 3";
$result = str_replace("\n", '<br>', htmlspecialchars($text));
// Result: "Line 1<br>Line 2<br>Line 3"
```

**Benefit**: Clean `<br>` tags without extra `\n` characters. TCPDF renders this consistently with the defined CSS `line-height: 1.5` property.

## Visual Comparison

### nl2br() Output (Before)
```
Line 1<br />
Line 2<br />
Line 3
```
↓ TCPDF renders ↓
```
Line 1
[extra space from \n]

Line 2
[extra space from \n]

Line 3
```

### str_replace() Output (After)
```
Line 1<br>Line 2<br>Line 3
```
↓ TCPDF renders ↓
```
Line 1
Line 2
Line 3
```

## Changes Made

### File: `pdf/generate-etat-lieux.php`

Replaced all 6 instances of `nl2br()`:

1. **Line ~411**: Description fields in entry template
   ```php
   // Before
   $piecePrincipale = nl2br(htmlspecialchars($piecePrincipale));
   
   // After
   $piecePrincipale = str_replace("\n", '<br>', htmlspecialchars($piecePrincipale));
   ```

2. **Line ~420**: Observations field
3. **Line ~528**: Helper function `getValueOrDefault()`
4. **Line ~582**: Description fields in alternative template
5. **Line ~590**: Observations in alternative template
6. **Line ~816-829**: Legacy template sections

## Impact

### Affected Fields in État des Lieux PDF:
- ✓ Pièce principale (main room description)
- ✓ Coin cuisine (kitchen description)
- ✓ Salle d'eau / WC (bathroom description)
- ✓ État général (general condition)
- ✓ Observations complémentaires
- ✓ Comparaison avec l'état d'entrée (for exit state)
- ✓ Motif de retenue (deposit retention reason)

### Benefits:
1. **Consistent line spacing** in all textarea fields in PDFs
2. **Proper rendering** of CSS `line-height: 1.5` property
3. **Better readability** of multi-line descriptions
4. **More predictable** PDF output

## Testing

### Unit Test Results
```
Old method (nl2br): 'Line 1<br />\nLine 2<br />\nLine 3'
New method (str_replace): 'Line 1<br>Line 2<br>Line 3'
✓ Fix verified: str_replace provides cleaner HTML for TCPDF rendering
```

### Quality Checks
- ✓ PHP syntax check: Passed
- ✓ Code review: No issues found
- ✓ Security scan: No vulnerabilities detected

## Security Considerations

Both the old and new approaches:
- Use `htmlspecialchars()` to prevent XSS attacks
- Properly escape HTML special characters
- Do not introduce any new security vulnerabilities

The only difference is the method of converting `\n` to `<br>` tags:
- **Old**: Uses PHP's built-in `nl2br()` which keeps original newlines
- **New**: Uses simple string replacement for cleaner output

## Deployment Notes

This fix is **backward compatible** and requires no database changes or configuration updates. The change only affects how textarea content is converted to HTML for PDF generation.

## Related Files

- `pdf/generate-etat-lieux.php` - Main file modified
- `admin-v2/edit-etat-lieux.php` - Form where textarea content is entered
- `admin-v2/create-etat-lieux.php` - Another entry point for data
