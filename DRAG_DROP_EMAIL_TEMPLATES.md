# Drag & Drop Feature for Email Templates

## Overview
This feature adds drag & drop functionality to the `/admin-v2/email-templates.php` page, allowing administrators to easily reorganize email templates by dragging and dropping them.

## Changes Made

### 1. Database Migration
**File:** `migrations/043_add_ordre_to_email_templates.sql`
- Added `ordre` column to the `email_templates` table
- Initialized order values based on existing IDs
- Added an index on the `ordre` column for better performance

### 2. Backend Updates
**File:** `admin-v2/email-templates.php`

#### PHP Changes:
- Added new AJAX endpoint `update_order` to handle order updates
- Modified the SQL query to order templates by `ordre ASC, id ASC`
- Added transaction support for safe batch updates

#### Frontend Changes:
- Integrated **SortableJS** library (v1.15.0) from CDN
- Added visual drag handle (grip icon) to each template card
- Added CSS classes for drag states:
  - `sortable-ghost`: Shows ghost element during drag
  - `sortable-drag`: Shows the dragging element
  - `drag-handle`: Custom cursor for the grip icon
- Implemented AJAX save functionality with success/error notifications

### 3. UI Improvements
- Added "Glissez-déposez pour réorganiser" hint in the header
- Added drag handle icon (`bi-grip-vertical`) to each template card
- Added visual feedback during drag operations
- Template cards now have `cursor: move` to indicate they're draggable

## How It Works

### User Experience:
1. Administrator views the email templates list
2. Clicks and holds the grip icon (⋮⋮) on any template card
3. Drags the card to the desired position
4. Releases to drop the card in the new position
5. Order is automatically saved via AJAX
6. Success notification confirms the save

### Technical Flow:
1. **SortableJS** initializes on the templates list container
2. User drags a template card
3. On drop, JavaScript collects the new order of template IDs
4. AJAX POST request sends the order to the server
5. Server validates and updates the `ordre` field for each template
6. Success/error response is shown to the user

## Visual States

### Drag States:
- **Normal State**: Template card with subtle shadow
- **Hover State**: Card lifts slightly with increased shadow
- **Dragging State**: Card becomes semi-transparent and rotates slightly
- **Ghost State**: Placeholder showing where the card will be dropped

### CSS Classes Added:
```css
.template-card {
    cursor: move;  /* Indicates the card is draggable */
}

.drag-handle {
    cursor: grab;  /* Grab cursor on the handle */
}

.drag-handle:active {
    cursor: grabbing;  /* Grabbing cursor when dragging */
}

.template-card.sortable-ghost {
    opacity: 0.4;  /* Ghost placeholder */
    background: #f0f0f0;
}

.template-card.sortable-drag {
    opacity: 0.8;  /* Dragged element */
    transform: rotate(2deg);
}
```

## API Endpoint

### POST /admin-v2/email-templates.php
**Action:** `update_order`

**Request:**
```
action: "update_order"
order: "[1, 3, 2, 4, 5]"  // JSON array of template IDs in new order
```

**Response:**
```json
{
    "success": true
}
```

Or in case of error:
```json
{
    "success": false,
    "error": "Error message"
}
```

## Benefits

1. **User-Friendly**: Intuitive drag & drop interface
2. **Fast**: No page reload required
3. **Safe**: Transaction-based updates ensure data consistency
4. **Visual Feedback**: Clear indicators of drag state and save status
5. **Accessible**: Works with mouse and touch devices

## Browser Compatibility
- Chrome/Edge: ✅ Full support
- Firefox: ✅ Full support
- Safari: ✅ Full support
- Mobile browsers: ✅ Touch-enabled drag & drop

## Future Enhancements
- Add undo/redo functionality
- Add keyboard shortcuts for reordering
- Add bulk reorder operations
- Add visual grouping of templates by category
