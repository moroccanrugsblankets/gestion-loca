<?php
/**
 * Standardized Inventory Items Template
 * Based on specifications - Fixed list for all properties
 * 
 * Structure: Each item has Entry/Exit columns with:
 * - Nombre (quantity number)
 * - Bon (Good condition checkbox)
 * - D'usage (Used condition checkbox)
 * - Mauvais (Bad condition checkbox)
 * - Commentaires (comments text field)
 */

/**
 * Get standardized inventory items template
 * 
 * Returns the complete structure of inventory items organized by category.
 * NEW SIMPLIFIED STRUCTURE (February 2026) - No subcategories, flat list per category.
 * Equipment is property-specific based on reference codes.
 * 
 * @param string $logement_reference Property reference code (e.g., 'RC-01', 'RF-03') for filtering
 * @return array Associative array with category names as keys. 
 *               Each item is an array with 'nom' (string), 'type' (string), and 'quantite' (int) keys.
 */
function getStandardInventaireItems($logement_reference = '') {
    // Normalize reference for matching (extract prefix)
    $ref_prefix = '';
    if (preg_match('/^(RC|RF|RP)/', $logement_reference, $matches)) {
        $ref_prefix = $matches[1];
    }
    
    // Check if this is a specific logement that gets extra items
    $is_rc01_02_rp07 = in_array($logement_reference, ['RC-01', 'RC-02', 'RP-07']);
    $is_rc_or_rf = in_array($ref_prefix, ['RC', 'RF']);
    
    $items = [
        // 🪑 MEUBLES (all set to Bon État / Good condition)
        'Meubles' => [
            ['nom' => 'Chaises', 'type' => 'countable', 'quantite' => 2, 'default_etat' => 'bon'],
            ['nom' => 'Canapé', 'type' => 'countable', 'quantite' => 1, 'default_etat' => 'bon'],
            ['nom' => 'Table à manger', 'type' => 'countable', 'quantite' => 1, 'default_etat' => 'bon'],
            ['nom' => 'Table basse', 'type' => 'countable', 'quantite' => 1, 'default_etat' => 'bon'],
            ['nom' => 'Placards intégrées', 'type' => 'item', 'quantite' => 1, 'default_etat' => 'bon'],
        ],
        
        // 🔌 ÉLECTROMÉNAGER
        'Électroménager' => [
            ['nom' => 'Réfrigérateur', 'type' => 'countable', 'quantite' => 1],
            ['nom' => 'Machine à laver séchante', 'type' => 'countable', 'quantite' => 1],
            ['nom' => 'Télévision', 'type' => 'countable', 'quantite' => 1],
            ['nom' => 'Fire Stick', 'type' => 'countable', 'quantite' => 1],
            ['nom' => 'Plaque de cuisson', 'type' => 'countable', 'quantite' => 1],
        ],
        
        // 🍽 ÉQUIPEMENT 1 (Cuisine / Vaisselle)
        'Équipement 1 (Cuisine / Vaisselle)' => [
            ['nom' => 'Grandes assiettes', 'type' => 'countable', 'quantite' => 4],
            ['nom' => 'Assiettes à dessert', 'type' => 'countable', 'quantite' => 4],
            ['nom' => 'Assiettes creuses', 'type' => 'countable', 'quantite' => 4],
            ['nom' => 'Fourchettes', 'type' => 'countable', 'quantite' => 4],
            ['nom' => 'Petites cuillères', 'type' => 'countable', 'quantite' => 4],
            ['nom' => 'Grandes cuillères', 'type' => 'countable', 'quantite' => 4],
            ['nom' => 'Couteaux de table', 'type' => 'countable', 'quantite' => 4],
            ['nom' => 'Verres', 'type' => 'countable', 'quantite' => 4],
            ['nom' => 'Bols', 'type' => 'countable', 'quantite' => 4],
            ['nom' => 'Tasses', 'type' => 'countable', 'quantite' => 4],
            ['nom' => 'Saladier', 'type' => 'countable', 'quantite' => 1],
            ['nom' => 'Poêle', 'type' => 'countable', 'quantite' => 1],
            ['nom' => 'Casserole', 'type' => 'countable', 'quantite' => 1],
            ['nom' => 'Planche à découper', 'type' => 'countable', 'quantite' => 1],
        ],
        
        // 🛏 ÉQUIPEMENT 2 (Linge / Entretien)
        'Équipement 2 (Linge / Entretien)' => [
            ['nom' => 'Matelas', 'type' => 'countable', 'quantite' => 1],
            ['nom' => 'Oreillers', 'type' => 'countable', 'quantite' => 2],
            ['nom' => 'Taies d\'oreiller', 'type' => 'countable', 'quantite' => 2],
            ['nom' => 'Draps du dessous', 'type' => 'countable', 'quantite' => 1],
            ['nom' => 'Couette', 'type' => 'countable', 'quantite' => 1],
            ['nom' => 'Housse de couette', 'type' => 'countable', 'quantite' => 1],
            ['nom' => 'Alaise', 'type' => 'countable', 'quantite' => 1],
            ['nom' => 'Plaid', 'type' => 'countable', 'quantite' => 1],
        ],
    ];
    
    // Add property-specific items for RC-01, RC-02, RP-07
    if ($is_rc01_02_rp07) {
        $items['Meubles'][] = ['nom' => 'Lit double', 'type' => 'countable', 'quantite' => 1, 'default_etat' => 'bon'];
        $items['Meubles'][] = ['nom' => 'Tables de chevets', 'type' => 'countable', 'quantite' => 2, 'default_etat' => 'bon'];
    }
    
    // Add property-specific items for RC and RF prefixes
    if ($is_rc_or_rf) {
        $items['Meubles'][] = ['nom' => 'Lustres / Plafonniers', 'type' => 'countable', 'quantite' => 1, 'default_etat' => 'bon'];
        $items['Meubles'][] = ['nom' => 'Lampadaire', 'type' => 'countable', 'quantite' => 1, 'default_etat' => 'bon'];
        $items['Électroménager'][] = ['nom' => 'Four grill / micro-ondes', 'type' => 'countable', 'quantite' => 1];
        $items['Électroménager'][] = ['nom' => 'Aspirateur', 'type' => 'countable', 'quantite' => 1];
    }
    
    return $items;
}

/**
 * Generate initial inventory data structure from standard items
 * @param string $logement_reference Property reference for property-specific equipment
 * @return array Formatted data for JSON storage with both entry and exit fields initialized
 */
function generateStandardInventoryData($logement_reference = '') {
    $items = getStandardInventaireItems($logement_reference);
    $data = [];
    $itemIndex = 0;
    
    foreach ($items as $categoryName => $categoryItems) {
        // New simplified structure - no subcategories, flat list
        foreach ($categoryItems as $item) {
            $data[] = [
                'id' => ++$itemIndex,
                'categorie' => $categoryName,
                'sous_categorie' => null, // No subcategories in new structure
                'nom' => $item['nom'],
                'type' => $item['type'],
                'entree' => [
                    'nombre' => $item['quantite'] ?? 0,
                    'bon' => isset($item['default_etat']) && $item['default_etat'] === 'bon',
                    'usage' => false,
                    'mauvais' => false,
                ],
                'sortie' => [
                    'nombre' => null,
                    'bon' => false,
                    'usage' => false,
                    'mauvais' => false,
                ],
                'commentaires' => ''
            ];
        }
    }
    
    return $data;
}
