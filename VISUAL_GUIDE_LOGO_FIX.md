# Visual Guide: Logo Fix in PDF Contracts

## Before the Fix ❌

### Template HTML (stored in database)
```html
<!DOCTYPE html>
<html lang="fr">
<head>
    <title>Contrat de Bail</title>
</head>
<body>
    <div class="header" style="text-align: center;">
        <img src="../assets/images/logo.png" alt="MY Invest Immobilier" style="width: 120px;">
        <h1>MY INVEST IMMOBILIER</h1>
    </div>
    <h2>CONTRAT DE BAIL</h2>
    <p>Entre les soussignés...</p>
</body>
</html>
```

### What TCPDF Received
```html
<img src="../assets/images/logo.png" alt="MY Invest Immobilier">
```

### Result
```
[Broken Image Icon] 
MY INVEST IMMOBILIER
CONTRAT DE BAIL
Entre les soussignés...
```

**Problem**: TCPDF couldn't resolve the relative path `../assets/images/logo.png`

---

## After the Fix ✅

### Template HTML (same as before)
```html
<!DOCTYPE html>
<html lang="fr">
<head>
    <title>Contrat de Bail</title>
</head>
<body>
    <div class="header" style="text-align: center;">
        <img src="../assets/images/logo.png" alt="MY Invest Immobilier" style="width: 120px;">
        <h1>MY INVEST IMMOBILIER</h1>
    </div>
    <h2>CONTRAT DE BAIL</h2>
    <p>Entre les soussignés...</p>
</body>
</html>
```

### What TCPDF Receives (After Conversion)
```html
<img src="http://localhost/contrat-bail/assets/images/logo.png" alt="MY Invest Immobilier">
```

### Result
```
[MY Invest Logo Image]
MY INVEST IMMOBILIER
CONTRAT DE BAIL
Entre les soussignés...
```

**Success**: TCPDF can now load the image from the absolute URL!

---

## How the Conversion Works

### Step-by-Step Process

1. **User edits template** in admin interface with relative path:
   ```html
   <img src="../assets/images/logo.png">
   ```

2. **Template saved** to database (unchanged):
   ```sql
   INSERT INTO parametres (cle, valeur) 
   VALUES ('contrat_template_html', '<img src="../assets/images/logo.png">');
   ```

3. **PDF generation requested** for a contract

4. **Function `convertRelativeImagePathsToAbsolute()` processes HTML**:
   ```
   Input:  <img src="../assets/images/logo.png">
   
   Process:
   - Detect: src="../assets/images/logo.png"
   - Remove: "../" prefix
   - Add: base URL "http://localhost/contrat-bail/"
   - Result: "http://localhost/contrat-bail/assets/images/logo.png"
   
   Output: <img src="http://localhost/contrat-bail/assets/images/logo.png">
   ```

5. **TCPDF generates PDF** with properly loaded images

---

## Supported Path Formats

### Example Conversions

| Input (Template) | Output (PDF) | Status |
|------------------|--------------|--------|
| `<img src="../assets/logo.png">` | `<img src="http://site.com/assets/logo.png">` | ✅ Converted |
| `<img src="./images/logo.png">` | `<img src="http://site.com/images/logo.png">` | ✅ Converted |
| `<img src="/uploads/logo.png">` | `<img src="http://site.com/uploads/logo.png">` | ✅ Converted |
| `<img src="assets/logo.png">` | `<img src="http://site.com/assets/logo.png">` | ✅ Converted |
| `<img src="https://cdn.com/logo.png">` | `<img src="https://cdn.com/logo.png">` | ✅ Unchanged |
| `<img src="data:image/png;base64,...">` | `<img src="data:image/png;base64,...">` | ✅ Unchanged |

---

## Real-World Example

### Template with Logo and Company Signature

```html
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Contrat de Bail</title>
    <style>
        .header { text-align: center; margin-bottom: 30px; }
        .logo { width: 150px; margin-bottom: 10px; }
        .signature { width: 100px; }
    </style>
</head>
<body>
    <!-- Header with logo -->
    <div class="header">
        <img src="../assets/images/logo-my-invest.png" alt="MY Invest" class="logo">
        <h1>MY INVEST IMMOBILIER</h1>
    </div>
    
    <!-- Contract content -->
    <h2>CONTRAT DE BAIL</h2>
    <p>Entre les soussignés :</p>
    <p><strong>Bailleur :</strong> MY Invest Immobilier</p>
    <p><strong>Locataire :</strong> {{locataires_info}}</p>
    
    <!-- Footer with signature -->
    <div class="footer">
        <p>Le bailleur</p>
        <img src="./uploads/signatures/company-signature.png" alt="Signature" class="signature">
        <p>Maxime Alexandre</p>
    </div>
</body>
</html>
```

### Before Fix ❌
```
[Broken Image]
MY INVEST IMMOBILIER
CONTRAT DE BAIL
...
[Broken Image]
Maxime Alexandre
```

### After Fix ✅
```
[MY Invest Logo]
MY INVEST IMMOBILIER
CONTRAT DE BAIL
...
[Signature Image]
Maxime Alexandre
```

---

## Developer Notes

### Function Signature
```php
function convertRelativeImagePathsToAbsolute($html, $config)
```

### Usage in Code
```php
// In replaceContratTemplateVariables() function
$html = str_replace(array_keys($vars), array_values($vars), $template);

// Convert image paths (NEW)
$html = convertRelativeImagePathsToAbsolute($html, $config);

return $html;
```

### Regex Pattern
```php
'/<img([^>]*?)src=["\']([^"\']+)["\']([^>]*?)>/i'
```

**Captures**:
- Group 1: Attributes before `src`
- Group 2: The `src` value (the path to convert)
- Group 3: Attributes after `src`

### Edge Cases Handled
- ✅ Single quotes in src: `<img src='logo.png'>`
- ✅ Double quotes in src: `<img src="logo.png">`
- ✅ Multiple attributes: `<img class="logo" src="logo.png" width="100">`
- ✅ No space before src: `<img src="logo.png">`
- ✅ Multiple images: `<img src="a.png"><img src="b.png">`

---

**Created by**: GitHub Copilot Coding Agent  
**Date**: February 9, 2026  
**Status**: Complete ✅
