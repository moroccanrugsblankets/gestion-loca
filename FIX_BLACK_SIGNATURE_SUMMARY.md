# Fix for Black Signature Images in État des Lieux

## Problem Statement
Client signatures in état des lieux were appearing as completely black images instead of showing the actual signature.

**Example URL:** `https://contrat.myinvest-immobilier.com/uploads/signatures/etat_lieux_tenant_1_1_1770367561.jpg`

## Root Cause

### Why Signatures Were Black
1. Canvas elements have a **transparent background** by default
2. When drawing on canvas, the signature strokes are drawn in black on this transparent background
3. The `saveTenantSignature()` function was converting the canvas directly to JPEG format
4. **JPEG format does not support transparency** - transparent pixels are rendered as **black**
5. Result: The entire signature image became black

### Code Before Fix
```javascript
function saveTenantSignature(id) {
    const canvas = document.getElementById(`tenantCanvas_${id}`);
    const signatureData = canvas.toDataURL('image/jpeg'); // ❌ WRONG: transparent→black
    document.getElementById(`tenantSignature_${id}`).value = signatureData;
}
```

## Solution

### How the Fix Works
1. Create a **temporary canvas** with the same dimensions
2. Fill the temporary canvas with a **white background** (#FFFFFF)
3. Draw the signature canvas **on top** of the white background
4. Convert the temporary canvas to JPEG

This ensures:
- White background instead of transparent
- Black signature strokes remain visible
- Proper contrast and readability

### Code After Fix
```javascript
function saveTenantSignature(id) {
    const canvas = document.getElementById(`tenantCanvas_${id}`);
    
    // Create a temporary canvas to add white background before JPEG conversion
    // JPEG doesn't support transparency, so we need to fill with white
    const tempCanvas = document.createElement('canvas');
    tempCanvas.width = canvas.width;
    tempCanvas.height = canvas.height;
    const tempCtx = tempCanvas.getContext('2d');
    
    // Fill with white background (JPEG doesn't support transparency)
    tempCtx.fillStyle = '#FFFFFF';
    tempCtx.fillRect(0, 0, tempCanvas.width, tempCanvas.height);
    
    // Draw the signature on top of the white background
    tempCtx.drawImage(canvas, 0, 0);
    
    // Convert to JPEG with white background
    const signatureData = tempCanvas.toDataURL('image/jpeg', 0.95);
    document.getElementById(`tenantSignature_${id}`).value = signatureData;
}
```

## Additional Improvements

### Canvas Drawing Context
Also added proper drawing style settings to ensure consistent signature appearance:

```javascript
function initTenantSignature(id) {
    const canvas = document.getElementById(`tenantCanvas_${id}`);
    if (!canvas) return;
    
    const ctx = canvas.getContext('2d');
    
    // Set drawing style for black signature lines
    ctx.strokeStyle = '#000000';  // Black color
    ctx.lineWidth = 2;            // 2px width
    ctx.lineCap = 'round';        // Rounded line ends
    ctx.lineJoin = 'round';       // Rounded line joins
    
    // ... event listeners ...
}
```

## Files Modified
- `admin-v2/edit-etat-lieux.php`:
  - Updated `initTenantSignature()` function (lines 1132-1187)
  - Updated `saveTenantSignature()` function (lines 1189-1209)

## Verification

### Before Fix
- Signature images: Completely black
- No visible signature strokes
- Image appears as a solid black rectangle

### After Fix
- Signature images: White background with black signature
- Clear, visible signature strokes
- Proper contrast and readability

## Technical Notes

### Why This Pattern?
This fix mirrors the approach already used in `assets/js/signature.js` for contract signatures:
- The `canvasToJPEGWithWhiteBackground()` function uses the same technique
- Ensures consistency across all signature capture in the application

### JPEG Quality
- Using quality factor of 0.95 (95%)
- Balances file size with image quality
- Matches the quality setting in `assets/js/signature.js`

### Browser Compatibility
- `canvas.toDataURL()` is supported in all modern browsers
- `drawImage()` is a standard canvas API
- No compatibility issues expected

## Testing Recommendations

1. **Create New État des Lieux:**
   - Draw a signature on the canvas
   - Submit the form
   - Check the saved signature file in `uploads/signatures/`
   - Verify the image has white background with black signature

2. **Verify in PDF:**
   - Generate a PDF for the état des lieux
   - Confirm signatures appear correctly in the PDF
   - Check that there are no black rectangles

3. **Test on Different Devices:**
   - Desktop browser (mouse input)
   - Mobile browser (touch input)
   - Tablet (touch input)

## Security Analysis
✅ No security vulnerabilities introduced
✅ Code review passed with no issues
✅ CodeQL security scan completed successfully
