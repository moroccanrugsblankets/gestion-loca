# Fix: Logo Display in PDF Contract Templates

## Problem

When adding a logo to the PDF contract template via the admin interface, the image does not appear in the generated PDF. This occurs because:

1. **Relative paths are saved**: When users insert images in the template editor (e.g., `../assets/logo.png` or `assets/logo.png`), these relative paths are stored in the database.

2. **TCPDF cannot resolve relative paths**: The TCPDF library, used to generate PDFs, cannot properly resolve relative paths because it doesn't know the web root context.

3. **Automatic path conversion**: Even when users try to use absolute paths, the template editor automatically converts them to relative paths after saving.

## Solution

Added a `convertRelativeImagePathsToAbsolute()` function that:

1. **Intercepts all image tags** in the HTML template using `preg_replace_callback()`
2. **Converts relative paths to absolute URLs** by prepending the site's base URL
3. **Preserves special cases**:
   - Data URIs (base64 encoded images) are left unchanged
   - Already absolute URLs (http://, https://) are left unchanged
4. **Handles all path formats**:
   - `../path/to/image.png` → `http://site.com/path/to/image.png`
   - `./path/to/image.png` → `http://site.com/path/to/image.png`
   - `/path/to/image.png` → `http://site.com/path/to/image.png`
   - `path/to/image.png` → `http://site.com/path/to/image.png`

## Implementation

**File Modified**: `pdf/generate-contrat-pdf.php`

**New Function** (lines 103-159):
```php
function convertRelativeImagePathsToAbsolute($html, $config) {
    // Converts all <img src="..."> paths to absolute URLs
    // This ensures TCPDF can load images properly
}
```

**Integration Point**: The function is called in `replaceContratTemplateVariables()` (line 211) after variable replacement and before returning the HTML.

## Testing

Created a comprehensive test suite (`test-image-path-conversion.php`) that verifies:

- ✓ Relative paths with `../` are converted correctly
- ✓ Relative paths with `./` are converted correctly
- ✓ Absolute paths starting with `/` are converted correctly
- ✓ Simple relative paths (no leading slash) are converted correctly
- ✓ Data URIs are preserved (not converted)
- ✓ Absolute URLs (http://, https://) are preserved (not converted)
- ✓ Multiple `../` in paths are handled correctly
- ✓ Images with additional HTML attributes are processed correctly

**All 8 tests passed successfully.**

## Usage

Users can now:

1. Go to the contract configuration page (`admin-v2/contrat-configuration.php`)
2. Edit the HTML template
3. Insert images using any path format:
   - `<img src="../assets/logo.png">`
   - `<img src="./images/logo.png">`
   - `<img src="/uploads/logo.jpg">`
   - `<img src="assets/logo.png">`
4. The images will automatically be converted to absolute URLs when generating PDFs
5. The logo will now display correctly in the generated PDF

## Technical Details

- **Regex Pattern**: `/<img([^>]*?)src=["']([^"']+)["']([^>]*?)>/i`
- **Base URL**: Retrieved from `$config['SITE_URL']`
- **Backward Compatibility**: Existing templates with absolute URLs or data URIs continue to work
- **Performance**: Minimal overhead - single pass through HTML using efficient regex callback

## Security

- No new security vulnerabilities introduced
- Paths are not executed, only converted to URLs
- HTML special characters in URLs are preserved
- No file system access during conversion

## Future Enhancements

Potential improvements for future versions:

1. **File path validation**: Check if image files exist before adding to PDF
2. **Image optimization**: Resize or compress images for smaller PDF file sizes
3. **Cache converted URLs**: Store converted templates to avoid repeated conversions
4. **Preview mode**: Show how images will appear in the PDF before generating
