# Visual Comparison: Line-Height Fix

## Example: État des Lieux Description Field

### Input (from textarea in form)
```
• Revêtement de sol : parquet très bon état d'usage
• Murs : peintures très bon état
• Plafond : peintures très bon état
• Installations électriques et plomberie : fonctionnelles
```

## BEFORE FIX (using nl2br)

### HTML Generated
```html
• Revêtement de sol : parquet très bon état d&#039;usage<br />
• Murs : peintures très bon état<br />
• Plafond : peintures très bon état<br />
• Installations électriques et plomberie : fonctionnelles
```

### How TCPDF Renders It
```
• Revêtement de sol : parquet très bon état d'usage
                                                     ← Extra space from \n
• Murs : peintures très bon état
                                                     ← Extra space from \n
• Plafond : peintures très bon état
                                                     ← Extra space from \n
• Installations électriques et plomberie : fonctionnelles
```

**Problem**: Double line spacing! The line-height looks inconsistent and there's too much vertical space between lines.

---

## AFTER FIX (using str_replace)

### HTML Generated
```html
• Revêtement de sol : parquet très bon état d&#039;usage<br>• Murs : peintures très bon état<br>• Plafond : peintures très bon état<br>• Installations électriques et plomberie : fonctionnelles
```

### How TCPDF Renders It
```
• Revêtement de sol : parquet très bon état d'usage
• Murs : peintures très bon état
• Plafond : peintures très bon état
• Installations électriques et plomberie : fonctionnelles
```

**Result**: Clean, consistent line spacing! The CSS `line-height: 1.5` is properly applied.

---

## Side-by-Side Comparison

```
┌─────────────────────────────────────────────────────────────────┐
│ BEFORE (with nl2br)          │ AFTER (with str_replace)         │
├─────────────────────────────────────────────────────────────────┤
│                              │                                  │
│ • Sol : parquet bon état     │ • Sol : parquet bon état         │
│           ↓ extra space      │                                  │
│                              │ • Murs : bon état                │
│ • Murs : bon état            │                                  │
│           ↓ extra space      │ • Plafond : bon état             │
│                              │                                  │
│ • Plafond : bon état         │ • Installations : OK             │
│           ↓ extra space      │                                  │
│                              │                                  │
│ • Installations : OK         │                                  │
│                              │                                  │
└─────────────────────────────────────────────────────────────────┘
```

## Technical Explanation

### Why nl2br() Causes Issues

`nl2br()` is designed for HTML display in web browsers, which ignore the `\n` character in HTML mode. But TCPDF's HTML parser is different - it processes both the `<br>` tag and the `\n` character, leading to:

1. `<br />` creates a line break
2. `\n` (newline) creates additional vertical space
3. Result: Double spacing

### Why str_replace() Works Better

`str_replace("\n", '<br>', $text)` only adds `<br>` tags without keeping the original `\n` characters. This gives TCPDF clean HTML that it can render with consistent line-height based on the CSS rules.

## Impact on All Textarea Fields

This fix applies to all textarea fields in État des Lieux PDFs:

✓ **Pièce principale** - No more excessive spacing between bullet points  
✓ **Coin cuisine** - Clean, readable descriptions  
✓ **Salle d'eau / WC** - Consistent formatting  
✓ **État général** - Professional appearance  
✓ **Observations** - Better readability  
✓ **Comparaison entrée/sortie** - Clear presentation  

## Conclusion

The fix addresses the exact problem stated: "tjrs probleme de line-height in PDF textarea fields". By replacing `\n` with `<br>` using `str_replace()` instead of `nl2br()`, we achieve:

- ✓ Consistent line-height in PDFs
- ✓ Professional appearance
- ✓ Better readability
- ✓ TCPDF renders properly with CSS line-height rules
