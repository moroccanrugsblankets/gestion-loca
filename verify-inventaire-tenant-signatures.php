<?php
/**
 * Verification script for Inventaire Tenant Signatures
 * Tests the fix for duplicate canvas ID issue
 * 
 * Usage: php verify-inventaire-tenant-signatures.php [inventaire_id]
 */

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';

// Get inventaire ID from command line or use default
$inventaire_id = isset($argv[1]) ? (int)$argv[1] : 3;

echo "╔══════════════════════════════════════════════════════════════════╗\n";
echo "║  Inventaire Tenant Signature Verification                       ║\n";
echo "╚══════════════════════════════════════════════════════════════════╝\n";
echo "\n";

// Fetch inventaire details
$stmt = $pdo->prepare("
    SELECT inv.*, 
           l.reference as logement_reference,
           l.type as logement_type,
           l.adresse as logement_adresse
    FROM inventaires inv
    INNER JOIN logements l ON inv.logement_id = l.id
    WHERE inv.id = ?
");
$stmt->execute([$inventaire_id]);
$inventaire = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$inventaire) {
    echo "❌ ERROR: Inventaire with ID $inventaire_id not found\n";
    exit(1);
}

echo "📋 Inventaire Information:\n";
echo "   ID: {$inventaire['id']}\n";
echo "   Reference: {$inventaire['reference_unique']}\n";
echo "   Type: {$inventaire['type']}\n";
echo "   Logement: {$inventaire['logement_reference']} - {$inventaire['logement_adresse']}\n";
echo "   Date: {$inventaire['date_inventaire']}\n";
echo "\n";

// Fetch tenants for this inventaire
$stmt = $pdo->prepare("SELECT * FROM inventaire_locataires WHERE inventaire_id = ? ORDER BY id ASC");
$stmt->execute([$inventaire_id]);
$tenants = $stmt->fetchAll(PDO::FETCH_ASSOC);

$tenant_count = count($tenants);
echo "👥 Tenants: $tenant_count\n";
echo str_repeat("─", 70) . "\n";

if ($tenant_count === 0) {
    echo "⚠️  No tenants found for this inventaire\n";
    echo "\n";
    echo "This could mean:\n";
    echo "  1. Tenants haven't been linked yet (will auto-populate on first edit)\n";
    echo "  2. The inventaire's contract has no tenants\n";
    echo "\n";
    exit(0);
}

$has_issues = false;
$tenant_ids = [];
$canvas_ids = [];

foreach ($tenants as $index => $tenant) {
    $num = $index + 1;
    $tenant_id = $tenant['id'];
    $locataire_id = $tenant['locataire_id'] ?? 'NULL';
    $name = trim(($tenant['prenom'] ?? '') . ' ' . ($tenant['nom'] ?? ''));
    $email = $tenant['email'] ?? 'N/A';
    $has_signature = !empty($tenant['signature']);
    $signed_date = $tenant['date_signature'] ?? null;
    $certifie_exact = !empty($tenant['certifie_exact']);
    
    // Track IDs for duplicate detection
    $tenant_ids[] = $tenant_id;
    $canvas_id = "tenantCanvas_{$tenant_id}";
    $canvas_ids[] = $canvas_id;
    
    echo "\nTenant $num:\n";
    echo "  ├─ DB ID (inventaire_locataires.id): $tenant_id\n";
    echo "  ├─ Locataire ID (FK): $locataire_id\n";
    echo "  ├─ Name: $name\n";
    echo "  ├─ Email: $email\n";
    echo "  ├─ Canvas ID: $canvas_id\n";
    echo "  ├─ Hidden Field ID: tenantSignature_{$tenant_id}\n";
    echo "  ├─ Form Array Key: tenants[{$tenant_id}]\n";
    
    if ($has_signature) {
        $sig_path = $tenant['signature'];
        $sig_type = (strpos($sig_path, 'data:image') === 0) ? 'base64' : 'file';
        echo "  ├─ Has Signature: YES ($sig_type)\n";
        
        if ($sig_type === 'file') {
            echo "  ├─ Signature File: $sig_path\n";
            $full_path = __DIR__ . '/' . $sig_path;
            if (file_exists($full_path)) {
                $file_size = filesize($full_path);
                echo "  ├─ File Status: EXISTS (" . number_format($file_size) . " bytes)\n";
            } else {
                echo "  ├─ File Status: ❌ NOT FOUND\n";
                $has_issues = true;
            }
        }
        
        if ($signed_date) {
            echo "  ├─ Signed Date: " . date('d/m/Y H:i:s', strtotime($signed_date)) . "\n";
        }
    } else {
        echo "  ├─ Has Signature: NO\n";
    }
    
    echo "  └─ Certifié Exact: " . ($certifie_exact ? 'YES ✓' : 'NO') . "\n";
}

echo "\n";
echo str_repeat("─", 70) . "\n";
echo "🔍 Validation Checks:\n";
echo str_repeat("─", 70) . "\n";

// Check for duplicate DB IDs
$unique_ids = array_unique($tenant_ids);
if (count($tenant_ids) !== count($unique_ids)) {
    echo "❌ CRITICAL: Duplicate tenant DB IDs detected!\n";
    echo "   Tenant IDs: " . implode(', ', $tenant_ids) . "\n";
    echo "   Unique IDs: " . implode(', ', $unique_ids) . "\n";
    echo "   This WILL cause canvas ID collision and prevent Tenant 2 from signing!\n";
    $has_issues = true;
} else {
    echo "✅ All tenant DB IDs are unique\n";
    echo "   IDs: " . implode(', ', $tenant_ids) . "\n";
}

echo "\n";

// Check for duplicate canvas IDs
$unique_canvas_ids = array_unique($canvas_ids);
if (count($canvas_ids) !== count($unique_canvas_ids)) {
    echo "❌ CRITICAL: Duplicate canvas IDs detected!\n";
    echo "   Canvas IDs: " . implode(', ', $canvas_ids) . "\n";
    echo "   This prevents independent signature capture!\n";
    $has_issues = true;
} else {
    echo "✅ All canvas IDs are unique\n";
    echo "   Canvas IDs: " . implode(', ', $canvas_ids) . "\n";
}

echo "\n";

// Check signature file paths
$signature_files = [];
foreach ($tenants as $tenant) {
    if (!empty($tenant['signature']) && strpos($tenant['signature'], 'uploads/signatures/') === 0) {
        $signature_files[] = $tenant['signature'];
    }
}

if (!empty($signature_files)) {
    $unique_files = array_unique($signature_files);
    if (count($signature_files) !== count($unique_files)) {
        echo "❌ WARNING: Duplicate signature file paths detected!\n";
        echo "   This could indicate file collision issues\n";
        $has_issues = true;
    } else {
        echo "✅ All signature files have unique paths\n";
    }
}

echo "\n";
echo str_repeat("═", 70) . "\n";

if ($has_issues) {
    echo "❌ ISSUES DETECTED - See warnings above\n";
    echo "\n";
    echo "🔧 Recommended Actions:\n";
    echo "  1. Check database for duplicate records in inventaire_locataires table\n";
    echo "  2. Run: SELECT * FROM inventaire_locataires WHERE inventaire_id = $inventaire_id;\n";
    echo "  3. Remove duplicate records keeping the oldest (lowest ID)\n";
    echo "  4. Verify the fix by reloading the edit page\n";
    echo "\n";
    exit(1);
} else {
    echo "✅ ALL CHECKS PASSED\n";
    echo "\n";
    echo "Expected Behavior:\n";
    foreach ($tenants as $index => $tenant) {
        $num = $index + 1;
        echo "  • Tenant $num (DB ID {$tenant['id']}) → Canvas: tenantCanvas_{$tenant['id']}\n";
    }
    echo "\n";
    echo "Each tenant should be able to sign independently.\n";
    echo "Signatures will be saved to unique file paths.\n";
    echo "\n";
    exit(0);
}
