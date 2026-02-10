# Visual Guide: Email Templates Drag & Drop

## Before Implementation

### Header
```
┌────────────────────────────────────────────────────────────┐
│ Templates d'Email                                          │
│ Gérer les modèles d'emails automatiques                   │
└────────────────────────────────────────────────────────────┘
```

### Template Card (Old)
```
┌────────────────────────────────────────────┐
│ Accusé de réception de candidature  [Actif]│
│ ID: candidature_recue                      │
│ Email envoyé au candidat dès la soumission │
│                                            │
│ Sujet:                                     │
│ Votre candidature a bien été reçue         │
│                                            │
│ 📅 10/02/2026              [📝 Modifier]   │
└────────────────────────────────────────────┘
```

**Limitations:**
- No way to reorder templates
- Order was fixed by database `identifiant` column
- Had to manually edit database to change order

---

## After Implementation

### Header
```
┌────────────────────────────────────────────────────────────┐
│ Templates d'Email                     ⇔ Glissez-déposez   │
│ Gérer les modèles d'emails automatiques  pour réorganiser │
└────────────────────────────────────────────────────────────┘
```

### Template Card (New)
```
┌────────────────────────────────────────────┐
│⋮⋮ Accusé de réception de candidature [Actif]│  ← Drag Handle
│   ID: candidature_recue                    │
│   Email envoyé au candidat dès la soumission│
│                                            │
│   Sujet:                                   │
│   Votre candidature a bien été reçue       │
│                                            │
│   📅 10/02/2026              [📝 Modifier] │
└────────────────────────────────────────────┘
     ↑
  Grip icon indicates draggable
```

### Drag States

#### 1. Normal State
```
┌────────────────────────────────────────────┐
│⋮⋮ Template Name                      [Actif]│
│   ...content...                            │
└────────────────────────────────────────────┘
Cursor: move (hand cursor)
```

#### 2. Hover State
```
  ┌────────────────────────────────────────┐
  │⋮⋮ Template Name                  [Actif]│ ← Lifted slightly
  │   ...content...                        │
  └────────────────────────────────────────┘
     Shadow: Enhanced (0 4px 8px)
```

#### 3. Dragging State
```
    ┌─────────────────────────────────────┐
   │⋮⋮ Template Name              [Actif]│  ← Rotated 2deg
   │   ...content... (semi-transparent)  │     Opacity: 0.8
   └─────────────────────────────────────┘
```

#### 4. Ghost Placeholder (where card will drop)
```
┌────────────────────────────────────────────┐
│                                            │
│          (empty placeholder)               │ ← Light gray bg
│                                            │    Opacity: 0.4
└────────────────────────────────────────────┘
```

---

## Interaction Flow

### Step 1: Initial View
```
┌─────────────────┐  ┌─────────────────┐  ┌─────────────────┐
│⋮⋮ Template 1   │  │⋮⋮ Template 2   │  │⋮⋮ Template 3   │
│ Position: 1     │  │ Position: 2     │  │ Position: 3     │
└─────────────────┘  └─────────────────┘  └─────────────────┘
```

### Step 2: User Drags Template 3
```
┌─────────────────┐  ┌─────────────────┐     ┌─────────────┐
│⋮⋮ Template 1   │  │⋮⋮ Template 2   │    │⋮⋮ Template 3│ ← Dragging
│ Position: 1     │  │ Position: 2     │    │ (floating)  │
└─────────────────┘  └─────────────────┘    └─────────────┘
                           ▲
                      Ghost placeholder
                     ┌───────────────┐
                     │               │
                     └───────────────┘
```

### Step 3: Drop Between Template 1 and 2
```
┌─────────────────┐  ┌─────────────────┐  ┌─────────────────┐
│⋮⋮ Template 1   │  │⋮⋮ Template 3   │  │⋮⋮ Template 2   │
│ Position: 1     │  │ Position: 2     │  │ Position: 3     │
└─────────────────┘  └─────────────────┘  └─────────────────┘
                           ▲
                    ✓ Ordre sauvegardé avec succès
```

---

## Technical Details

### Database Change
```sql
-- Old schema
CREATE TABLE email_templates (
    id INT AUTO_INCREMENT PRIMARY KEY,
    identifiant VARCHAR(100),
    nom VARCHAR(255),
    ...
);

-- Old query
SELECT * FROM email_templates ORDER BY identifiant;
```

```sql
-- New schema
CREATE TABLE email_templates (
    id INT AUTO_INCREMENT PRIMARY KEY,
    identifiant VARCHAR(100),
    nom VARCHAR(255),
    ...
    ordre INT NOT NULL DEFAULT 0,  ← New column
    INDEX idx_ordre (ordre)
);

-- New query
SELECT * FROM email_templates ORDER BY ordre ASC, id ASC;
```

### JavaScript Integration
```javascript
// Initialize SortableJS
Sortable.create(templatesList, {
    animation: 150,
    handle: '.drag-handle',  // Only drag from grip icon
    ghostClass: 'sortable-ghost',
    dragClass: 'sortable-drag',
    onEnd: function(evt) {
        // Save new order via AJAX
        saveTemplateOrder(newOrder);
    }
});
```

### AJAX Request
```javascript
// Request
POST /admin-v2/email-templates.php
{
    action: "update_order",
    order: "[3, 1, 2, 4, 5, ...]"
}

// Response
{
    "success": true
}
```

---

## User Benefits

✅ **No page refresh** - Instant reordering
✅ **Visual feedback** - See exactly where the card will be placed
✅ **Undo-friendly** - Just drag it back if needed
✅ **Touch-friendly** - Works on tablets and mobile devices
✅ **Safe** - Auto-saves with error handling
✅ **Intuitive** - No training required

## Accessibility

- **Keyboard**: Currently mouse/touch only (keyboard support could be future enhancement)
- **Visual**: Clear drag handle and visual states
- **Feedback**: Success/error notifications after save
