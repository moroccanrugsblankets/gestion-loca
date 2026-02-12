<?php
/**
 * Migration 046: Populate Complete Inventaire Items
 * 
 * Populates the inventaire_equipements template with all items specified
 * in the requirements document (Cahier des charges)
 * 
 * This creates templates that will be used when creating new inventories
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';

try {
    echo "=== Migration 046: Populate Complete Inventaire Items ===\n\n";
    
    // Create a template structure for default equipment items
    // These will be stored in a parametres table entry for reference
    
    $equipment_template = [
        // 3.1 État des pièces
        'État des pièces' => [
            'Entrée' => [
                'Porte' => ['type' => 'item', 'description' => ''],
                'Sonnette/interphone' => ['type' => 'item', 'description' => ''],
                'Mur' => ['type' => 'item', 'description' => ''],
                'Sol' => ['type' => 'item', 'description' => ''],
                'Vitrage et volets' => ['type' => 'item', 'description' => ''],
                'Plafond' => ['type' => 'item', 'description' => ''],
                'Éclairage et interrupteurs' => ['type' => 'item', 'description' => ''],
                'Prises électriques' => ['type' => 'item', 'description' => ''],
            ],
            'Séjour/salle à manger' => [
                'Mur' => ['type' => 'item', 'description' => ''],
                'Sol' => ['type' => 'item', 'description' => ''],
                'Vitrage' => ['type' => 'item', 'description' => ''],
                'Plafond' => ['type' => 'item', 'description' => ''],
                'Éclairage et interrupteurs' => ['type' => 'item', 'description' => ''],
                'Prises électriques' => ['type' => 'item', 'description' => ''],
            ],
            'Cuisine' => [
                'Mur' => ['type' => 'item', 'description' => ''],
                'Sol' => ['type' => 'item', 'description' => ''],
                'Vitrage et volets' => ['type' => 'item', 'description' => ''],
                'Plafond' => ['type' => 'item', 'description' => ''],
                'Éclairage et interrupteurs' => ['type' => 'item', 'description' => ''],
                'Prises électriques' => ['type' => 'item', 'description' => ''],
                'Placards et tiroirs' => ['type' => 'item', 'description' => ''],
                'Évier et robinetterie' => ['type' => 'item', 'description' => ''],
                'Plaques de cuisson et four' => ['type' => 'item', 'description' => ''],
                'Hotte' => ['type' => 'item', 'description' => ''],
                'Électroménager' => ['type' => 'item', 'description' => ''],
            ],
            'Chambre 1' => [
                'Mur' => ['type' => 'item', 'description' => ''],
                'Sol' => ['type' => 'item', 'description' => ''],
                'Vitrage et volets' => ['type' => 'item', 'description' => ''],
                'Plafond' => ['type' => 'item', 'description' => ''],
                'Éclairage et interrupteurs' => ['type' => 'item', 'description' => ''],
                'Prises électriques' => ['type' => 'item', 'description' => ''],
            ],
            'Chambre 2' => [
                'Mur' => ['type' => 'item', 'description' => ''],
                'Sol' => ['type' => 'item', 'description' => ''],
                'Vitrage et volets' => ['type' => 'item', 'description' => ''],
                'Plafond' => ['type' => 'item', 'description' => ''],
                'Éclairage et interrupteurs' => ['type' => 'item', 'description' => ''],
                'Prises électriques' => ['type' => 'item', 'description' => ''],
            ],
            'Chambre 3' => [
                'Mur' => ['type' => 'item', 'description' => ''],
                'Sol' => ['type' => 'item', 'description' => ''],
                'Vitrage et volets' => ['type' => 'item', 'description' => ''],
                'Plafond' => ['type' => 'item', 'description' => ''],
                'Éclairage et interrupteurs' => ['type' => 'item', 'description' => ''],
                'Prises électriques' => ['type' => 'item', 'description' => ''],
            ],
            'Salle de bain 1' => [
                'Mur' => ['type' => 'item', 'description' => ''],
                'Sol' => ['type' => 'item', 'description' => ''],
                'Vitrage et volets' => ['type' => 'item', 'description' => ''],
                'Plafond' => ['type' => 'item', 'description' => ''],
                'Éclairage et interrupteurs' => ['type' => 'item', 'description' => ''],
                'Prises électriques' => ['type' => 'item', 'description' => ''],
                'Lavabo et robinetterie' => ['type' => 'item', 'description' => ''],
                'Baignoire/douche' => ['type' => 'item', 'description' => ''],
                'WC' => ['type' => 'item', 'description' => ''],
            ],
            'Salle de bain 2' => [
                'Mur' => ['type' => 'item', 'description' => ''],
                'Sol' => ['type' => 'item', 'description' => ''],
                'Vitrage et volets' => ['type' => 'item', 'description' => ''],
                'Plafond' => ['type' => 'item', 'description' => ''],
                'Éclairage et interrupteurs' => ['type' => 'item', 'description' => ''],
                'Prises électriques' => ['type' => 'item', 'description' => ''],
                'Lavabo et robinetterie' => ['type' => 'item', 'description' => ''],
                'Baignoire/douche' => ['type' => 'item', 'description' => ''],
                'WC' => ['type' => 'item', 'description' => ''],
            ],
            'WC 1' => [
                'Mur' => ['type' => 'item', 'description' => ''],
                'Sol' => ['type' => 'item', 'description' => ''],
                'Vitrage et volets' => ['type' => 'item', 'description' => ''],
                'Plafond' => ['type' => 'item', 'description' => ''],
                'Éclairage et interrupteurs' => ['type' => 'item', 'description' => ''],
                'Prises électriques' => ['type' => 'item', 'description' => ''],
                'Lavabo et robinetterie' => ['type' => 'item', 'description' => ''],
                'WC' => ['type' => 'item', 'description' => ''],
            ],
            'WC 2' => [
                'Mur' => ['type' => 'item', 'description' => ''],
                'Sol' => ['type' => 'item', 'description' => ''],
                'Vitrage et volets' => ['type' => 'item', 'description' => ''],
                'Plafond' => ['type' => 'item', 'description' => ''],
                'Éclairage et interrupteurs' => ['type' => 'item', 'description' => ''],
                'Prises électriques' => ['type' => 'item', 'description' => ''],
                'Lavabo et robinetterie' => ['type' => 'item', 'description' => ''],
                'WC' => ['type' => 'item', 'description' => ''],
            ],
            'Autres pièces' => [
                'Mur' => ['type' => 'item', 'description' => ''],
                'Sol' => ['type' => 'item', 'description' => ''],
                'Vitrage et volets' => ['type' => 'item', 'description' => ''],
                'Plafond' => ['type' => 'item', 'description' => ''],
                'Éclairage et interrupteurs' => ['type' => 'item', 'description' => ''],
                'Prises électriques' => ['type' => 'item', 'description' => ''],
            ],
        ],
        
        // 3.2 Inventaire et état des meubles
        'Meubles' => [
            'Chaises (séjour)' => ['type' => 'countable', 'description' => ''],
            'Chaises (chambres)' => ['type' => 'countable', 'description' => ''],
            'Chaises (cuisine)' => ['type' => 'countable', 'description' => ''],
            'Chaises (autres)' => ['type' => 'countable', 'description' => ''],
            'Tabourets' => ['type' => 'countable', 'description' => ''],
            'Canapés' => ['type' => 'countable', 'description' => ''],
            'Fauteuils' => ['type' => 'countable', 'description' => ''],
            'Tables (séjour)' => ['type' => 'countable', 'description' => ''],
            'Tables (chambres)' => ['type' => 'countable', 'description' => ''],
            'Tables (cuisine)' => ['type' => 'countable', 'description' => ''],
            'Tables de nuit' => ['type' => 'countable', 'description' => ''],
            'Tables (autres)' => ['type' => 'countable', 'description' => ''],
            'Bureaux' => ['type' => 'countable', 'description' => ''],
            'Commodes' => ['type' => 'countable', 'description' => ''],
            'Armoires' => ['type' => 'countable', 'description' => ''],
            'Buffets' => ['type' => 'countable', 'description' => ''],
            'Lits simples' => ['type' => 'countable', 'description' => ''],
            'Lits doubles' => ['type' => 'countable', 'description' => ''],
            'Placards' => ['type' => 'countable', 'description' => ''],
            'Lustres/plafonniers' => ['type' => 'countable', 'description' => ''],
            'Lampes/appliques' => ['type' => 'countable', 'description' => ''],
        ],
        
        // 3.3 Électroménager
        'Électroménager' => [
            'Réfrigérateur' => ['type' => 'countable', 'description' => ''],
            'Congélateur' => ['type' => 'countable', 'description' => ''],
            'Cuisinière' => ['type' => 'countable', 'description' => ''],
            'Four' => ['type' => 'countable', 'description' => ''],
            'Four micro-ondes' => ['type' => 'countable', 'description' => ''],
            'Grille-pain' => ['type' => 'countable', 'description' => ''],
            'Bouilloire' => ['type' => 'countable', 'description' => ''],
            'Cafetière' => ['type' => 'countable', 'description' => ''],
            'Lave-vaisselle' => ['type' => 'countable', 'description' => ''],
            'Lave-linge' => ['type' => 'countable', 'description' => ''],
            'Sèche-linge' => ['type' => 'countable', 'description' => ''],
            'Télévision' => ['type' => 'countable', 'description' => ''],
            'Lecteur DVD' => ['type' => 'countable', 'description' => ''],
            'Vidéoprojecteur' => ['type' => 'countable', 'description' => ''],
            'Chaîne Hi-fi' => ['type' => 'countable', 'description' => ''],
            'Fer à repasser' => ['type' => 'countable', 'description' => ''],
            'Aspirateur' => ['type' => 'countable', 'description' => ''],
        ],
        
        // 3.4 Équipements divers - Vaisselle
        'Vaisselle' => [
            'Grandes assiettes' => ['type' => 'countable', 'description' => ''],
            'Assiettes à dessert' => ['type' => 'countable', 'description' => ''],
            'Assiettes creuses' => ['type' => 'countable', 'description' => ''],
            'Autres assiettes' => ['type' => 'countable', 'description' => ''],
            'Verres à pied' => ['type' => 'countable', 'description' => ''],
            'Autres verres' => ['type' => 'countable', 'description' => ''],
            'Bols' => ['type' => 'countable', 'description' => ''],
            'Tasses' => ['type' => 'countable', 'description' => ''],
            'Soucoupes' => ['type' => 'countable', 'description' => ''],
            'Saladiers' => ['type' => 'countable', 'description' => ''],
            'Plats' => ['type' => 'countable', 'description' => ''],
            'Carafes' => ['type' => 'countable', 'description' => ''],
        ],
        
        // 3.4 Équipements divers - Couverts
        'Couverts' => [
            'Fourchettes' => ['type' => 'countable', 'description' => ''],
            'Petites cuillères' => ['type' => 'countable', 'description' => ''],
            'Grandes cuillères' => ['type' => 'countable', 'description' => ''],
            'Couteaux de table' => ['type' => 'countable', 'description' => ''],
            'Couteaux de cuisine' => ['type' => 'countable', 'description' => ''],
            'Couteaux à pain' => ['type' => 'countable', 'description' => ''],
            'Couverts de service' => ['type' => 'countable', 'description' => ''],
            'Tire-bouchon' => ['type' => 'countable', 'description' => ''],
            'Décapsuleur' => ['type' => 'countable', 'description' => ''],
            'Ouvre-boîtes' => ['type' => 'countable', 'description' => ''],
        ],
        
        // 3.4 Équipements divers - Ustensiles
        'Ustensiles' => [
            'Pelles' => ['type' => 'countable', 'description' => ''],
            'Seaux' => ['type' => 'countable', 'description' => ''],
            'Torchons' => ['type' => 'countable', 'description' => ''],
            'Planches à découper' => ['type' => 'countable', 'description' => ''],
            'Passoires' => ['type' => 'countable', 'description' => ''],
            'Poêles' => ['type' => 'countable', 'description' => ''],
            'Casseroles' => ['type' => 'countable', 'description' => ''],
            'Égouttoir' => ['type' => 'countable', 'description' => ''],
            'Balais/balayettes' => ['type' => 'countable', 'description' => ''],
        ],
        
        // 3.4 Équipements divers - Literie et linge
        'Literie et linge' => [
            'Matelas' => ['type' => 'countable', 'description' => ''],
            'Traversins' => ['type' => 'countable', 'description' => ''],
            'Taies de traversin' => ['type' => 'countable', 'description' => ''],
            'Oreillers' => ['type' => 'countable', 'description' => ''],
            'Taies d\'oreiller' => ['type' => 'countable', 'description' => ''],
            'Draps du dessous' => ['type' => 'countable', 'description' => ''],
            'Draps' => ['type' => 'countable', 'description' => ''],
            'Couettes' => ['type' => 'countable', 'description' => ''],
            'Housses de couette' => ['type' => 'countable', 'description' => ''],
            'Couvertures' => ['type' => 'countable', 'description' => ''],
            'Alaises' => ['type' => 'countable', 'description' => ''],
            'Couvre-lits' => ['type' => 'countable', 'description' => ''],
        ],
        
        // 3.4 Équipements divers - Salle de bain
        'Linge de salle de bain' => [
            'Peignoirs de bain' => ['type' => 'countable', 'description' => ''],
            'Serviettes de bain' => ['type' => 'countable', 'description' => ''],
            'Serviettes de toilette' => ['type' => 'countable', 'description' => ''],
            'Gants de toilette' => ['type' => 'countable', 'description' => ''],
        ],
        
        // 3.4 Équipements divers - Linge de maison
        'Linge de maison' => [
            'Nappes' => ['type' => 'countable', 'description' => ''],
            'Serviettes de table' => ['type' => 'countable', 'description' => ''],
        ],
        
        // 3.4 Équipements divers - Divers
        'Divers' => [
            'Coussins' => ['type' => 'countable', 'description' => ''],
        ],
    ];
    
    // Store the template in parametres table
    $stmt = $pdo->prepare("
        INSERT INTO parametres (cle, valeur, description, type)
        VALUES ('inventaire_items_template', ?, 'Template des éléments d\'inventaire selon cahier des charges', 'json')
        ON DUPLICATE KEY UPDATE valeur = VALUES(valeur), description = VALUES(description)
    ");
    
    $stmt->execute([json_encode($equipment_template, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)]);
    
    echo "✓ Template d'inventaire complet créé dans parametres\n";
    echo "  - " . count($equipment_template) . " catégories principales\n";
    
    $totalItems = 0;
    foreach ($equipment_template as $category => $items) {
        if (is_array($items)) {
            // If items is an array with subcategories (like État des pièces)
            foreach ($items as $subcategory => $subitems) {
                if (is_array($subitems)) {
                    $totalItems += count($subitems);
                }
            }
        }
    }
    echo "  - Environ $totalItems éléments au total\n\n";
    
    echo "✓ Migration 046 terminée avec succès\n";
    echo "\nNote: Ce template sera utilisé lors de la création d'inventaires.\n";
    echo "Les administrateurs peuvent personnaliser les équipements par logement.\n";
    
} catch (Exception $e) {
    echo "✗ Erreur lors de la migration: " . $e->getMessage() . "\n";
    error_log("Migration 046 failed: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    exit(1);
}
