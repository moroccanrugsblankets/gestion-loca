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
 * État des pièces category includes subcategories (rooms), other categories are flat lists.
 * 
 * @return array Associative array with category names as keys. 
 *               'État des pièces' category contains subcategories (nested arrays),
 *               while other categories contain direct item arrays.
 *               Each item is an array with 'nom' (string) and 'type' (string) keys.
 */
function getStandardInventaireItems() {
    return [
        // 3.1 État des pièces
        'État des pièces' => [
            'Entrée' => [
                ['nom' => 'Porte', 'type' => 'item'],
                ['nom' => 'Sonnette/interphone', 'type' => 'item'],
                ['nom' => 'Mur', 'type' => 'item'],
                ['nom' => 'Sol', 'type' => 'item'],
                ['nom' => 'Vitrage et volets', 'type' => 'item'],
                ['nom' => 'Plafond', 'type' => 'item'],
                ['nom' => 'Éclairage et interrupteurs', 'type' => 'item'],
                ['nom' => 'Prises électriques', 'type' => 'item'],
            ],
            'Séjour/salle à manger' => [
                ['nom' => 'Mur', 'type' => 'item'],
                ['nom' => 'Sol', 'type' => 'item'],
                ['nom' => 'Vitrage', 'type' => 'item'],
                ['nom' => 'Plafond', 'type' => 'item'],
                ['nom' => 'Éclairage et interrupteurs', 'type' => 'item'],
                ['nom' => 'Prises électriques', 'type' => 'item'],
            ],
            'Cuisine' => [
                ['nom' => 'Mur', 'type' => 'item'],
                ['nom' => 'Sol', 'type' => 'item'],
                ['nom' => 'Vitrage et volets', 'type' => 'item'],
                ['nom' => 'Plafond', 'type' => 'item'],
                ['nom' => 'Éclairage et interrupteurs', 'type' => 'item'],
                ['nom' => 'Prises électriques', 'type' => 'item'],
                ['nom' => 'Placards et tiroirs', 'type' => 'item'],
                ['nom' => 'Évier et robinetterie', 'type' => 'item'],
                ['nom' => 'Plaques de cuisson et four', 'type' => 'item'],
                ['nom' => 'Hotte', 'type' => 'item'],
                ['nom' => 'Électroménager', 'type' => 'item'],
            ],
            'Chambre 1' => [
                ['nom' => 'Mur', 'type' => 'item'],
                ['nom' => 'Sol', 'type' => 'item'],
                ['nom' => 'Vitrage et volets', 'type' => 'item'],
                ['nom' => 'Plafond', 'type' => 'item'],
                ['nom' => 'Éclairage et interrupteurs', 'type' => 'item'],
                ['nom' => 'Prises électriques', 'type' => 'item'],
            ],
            'Chambre 2' => [
                ['nom' => 'Mur', 'type' => 'item'],
                ['nom' => 'Sol', 'type' => 'item'],
                ['nom' => 'Vitrage et volets', 'type' => 'item'],
                ['nom' => 'Plafond', 'type' => 'item'],
                ['nom' => 'Éclairage et interrupteurs', 'type' => 'item'],
                ['nom' => 'Prises électriques', 'type' => 'item'],
            ],
            'Chambre 3' => [
                ['nom' => 'Mur', 'type' => 'item'],
                ['nom' => 'Sol', 'type' => 'item'],
                ['nom' => 'Vitrage et volets', 'type' => 'item'],
                ['nom' => 'Plafond', 'type' => 'item'],
                ['nom' => 'Éclairage et interrupteurs', 'type' => 'item'],
                ['nom' => 'Prises électriques', 'type' => 'item'],
            ],
            'Salle de bain 1' => [
                ['nom' => 'Mur', 'type' => 'item'],
                ['nom' => 'Sol', 'type' => 'item'],
                ['nom' => 'Vitrage et volets', 'type' => 'item'],
                ['nom' => 'Plafond', 'type' => 'item'],
                ['nom' => 'Éclairage et interrupteurs', 'type' => 'item'],
                ['nom' => 'Prises électriques', 'type' => 'item'],
                ['nom' => 'Lavabo et robinetterie', 'type' => 'item'],
                ['nom' => 'Baignoire/douche', 'type' => 'item'],
                ['nom' => 'WC', 'type' => 'item'],
            ],
            'Salle de bain 2' => [
                ['nom' => 'Mur', 'type' => 'item'],
                ['nom' => 'Sol', 'type' => 'item'],
                ['nom' => 'Vitrage et volets', 'type' => 'item'],
                ['nom' => 'Plafond', 'type' => 'item'],
                ['nom' => 'Éclairage et interrupteurs', 'type' => 'item'],
                ['nom' => 'Prises électriques', 'type' => 'item'],
                ['nom' => 'Lavabo et robinetterie', 'type' => 'item'],
                ['nom' => 'Baignoire/douche', 'type' => 'item'],
                ['nom' => 'WC', 'type' => 'item'],
            ],
            'WC 1' => [
                ['nom' => 'Mur', 'type' => 'item'],
                ['nom' => 'Sol', 'type' => 'item'],
                ['nom' => 'Vitrage et volets', 'type' => 'item'],
                ['nom' => 'Plafond', 'type' => 'item'],
                ['nom' => 'Éclairage et interrupteurs', 'type' => 'item'],
                ['nom' => 'Prises électriques', 'type' => 'item'],
                ['nom' => 'Lavabo et robinetterie', 'type' => 'item'],
                ['nom' => 'WC', 'type' => 'item'],
            ],
            'WC 2' => [
                ['nom' => 'Mur', 'type' => 'item'],
                ['nom' => 'Sol', 'type' => 'item'],
                ['nom' => 'Vitrage et volets', 'type' => 'item'],
                ['nom' => 'Plafond', 'type' => 'item'],
                ['nom' => 'Éclairage et interrupteurs', 'type' => 'item'],
                ['nom' => 'Prises électriques', 'type' => 'item'],
                ['nom' => 'Lavabo et robinetterie', 'type' => 'item'],
                ['nom' => 'WC', 'type' => 'item'],
            ],
            'Autres pièces' => [
                ['nom' => 'Mur', 'type' => 'item'],
                ['nom' => 'Sol', 'type' => 'item'],
                ['nom' => 'Vitrage et volets', 'type' => 'item'],
                ['nom' => 'Plafond', 'type' => 'item'],
                ['nom' => 'Éclairage et interrupteurs', 'type' => 'item'],
                ['nom' => 'Prises électriques', 'type' => 'item'],
            ],
        ],
        
        // 3.2 Inventaire et état des meubles
        'Meubles' => [
            ['nom' => 'Chaises (séjour)', 'type' => 'countable'],
            ['nom' => 'Chaises (chambres)', 'type' => 'countable'],
            ['nom' => 'Chaises (cuisine)', 'type' => 'countable'],
            ['nom' => 'Chaises (autres)', 'type' => 'countable'],
            ['nom' => 'Tabourets', 'type' => 'countable'],
            ['nom' => 'Canapés', 'type' => 'countable'],
            ['nom' => 'Fauteuils', 'type' => 'countable'],
            ['nom' => 'Tables (séjour)', 'type' => 'countable'],
            ['nom' => 'Tables (chambres)', 'type' => 'countable'],
            ['nom' => 'Tables (cuisine)', 'type' => 'countable'],
            ['nom' => 'Tables de nuit', 'type' => 'countable'],
            ['nom' => 'Tables (autres)', 'type' => 'countable'],
            ['nom' => 'Bureaux', 'type' => 'countable'],
            ['nom' => 'Commodes', 'type' => 'countable'],
            ['nom' => 'Armoires', 'type' => 'countable'],
            ['nom' => 'Buffets', 'type' => 'countable'],
            ['nom' => 'Lits simples', 'type' => 'countable'],
            ['nom' => 'Lits doubles', 'type' => 'countable'],
            ['nom' => 'Placards', 'type' => 'countable'],
            ['nom' => 'Lustres/plafonniers', 'type' => 'countable'],
            ['nom' => 'Lampes/appliques', 'type' => 'countable'],
        ],
        
        // 3.3 Électroménager
        'Électroménager' => [
            ['nom' => 'Réfrigérateur', 'type' => 'countable'],
            ['nom' => 'Congélateur', 'type' => 'countable'],
            ['nom' => 'Cuisinière', 'type' => 'countable'],
            ['nom' => 'Four', 'type' => 'countable'],
            ['nom' => 'Four micro-ondes', 'type' => 'countable'],
            ['nom' => 'Grille-pain', 'type' => 'countable'],
            ['nom' => 'Bouilloire', 'type' => 'countable'],
            ['nom' => 'Cafetière', 'type' => 'countable'],
            ['nom' => 'Lave-vaisselle', 'type' => 'countable'],
            ['nom' => 'Lave-linge', 'type' => 'countable'],
            ['nom' => 'Sèche-linge', 'type' => 'countable'],
            ['nom' => 'Télévision', 'type' => 'countable'],
            ['nom' => 'Lecteur DVD', 'type' => 'countable'],
            ['nom' => 'Vidéoprojecteur', 'type' => 'countable'],
            ['nom' => 'Chaîne Hi-fi', 'type' => 'countable'],
            ['nom' => 'Fer à repasser', 'type' => 'countable'],
            ['nom' => 'Aspirateur', 'type' => 'countable'],
        ],
        
        // 3.4 Équipements divers - Vaisselle
        'Vaisselle' => [
            ['nom' => 'Grandes assiettes', 'type' => 'countable'],
            ['nom' => 'Assiettes à dessert', 'type' => 'countable'],
            ['nom' => 'Assiettes creuses', 'type' => 'countable'],
            ['nom' => 'Autres assiettes', 'type' => 'countable'],
            ['nom' => 'Verres à pied', 'type' => 'countable'],
            ['nom' => 'Autres verres', 'type' => 'countable'],
            ['nom' => 'Bols', 'type' => 'countable'],
            ['nom' => 'Tasses', 'type' => 'countable'],
            ['nom' => 'Soucoupes', 'type' => 'countable'],
            ['nom' => 'Saladiers', 'type' => 'countable'],
            ['nom' => 'Plats', 'type' => 'countable'],
            ['nom' => 'Carafes', 'type' => 'countable'],
        ],
        
        // 3.4 Équipements divers - Couverts
        'Couverts' => [
            ['nom' => 'Fourchettes', 'type' => 'countable'],
            ['nom' => 'Petites cuillères', 'type' => 'countable'],
            ['nom' => 'Grandes cuillères', 'type' => 'countable'],
            ['nom' => 'Couteaux de table', 'type' => 'countable'],
            ['nom' => 'Couteaux de cuisine', 'type' => 'countable'],
            ['nom' => 'Couteaux à pain', 'type' => 'countable'],
            ['nom' => 'Couverts de service', 'type' => 'countable'],
            ['nom' => 'Tire-bouchon', 'type' => 'countable'],
            ['nom' => 'Décapsuleur', 'type' => 'countable'],
            ['nom' => 'Ouvre-boîtes', 'type' => 'countable'],
        ],
        
        // 3.4 Équipements divers - Ustensiles
        'Ustensiles' => [
            ['nom' => 'Pelles', 'type' => 'countable'],
            ['nom' => 'Seaux', 'type' => 'countable'],
            ['nom' => 'Torchons', 'type' => 'countable'],
            ['nom' => 'Planches à découper', 'type' => 'countable'],
            ['nom' => 'Passoires', 'type' => 'countable'],
            ['nom' => 'Poêles', 'type' => 'countable'],
            ['nom' => 'Casseroles', 'type' => 'countable'],
            ['nom' => 'Égouttoir', 'type' => 'countable'],
            ['nom' => 'Balais/balayettes', 'type' => 'countable'],
        ],
        
        // 3.4 Équipements divers - Literie et linge
        'Literie et linge' => [
            ['nom' => 'Matelas', 'type' => 'countable'],
            ['nom' => 'Traversins', 'type' => 'countable'],
            ['nom' => 'Taies de traversin', 'type' => 'countable'],
            ['nom' => 'Oreillers', 'type' => 'countable'],
            ['nom' => "Taies d'oreiller", 'type' => 'countable'],
            ['nom' => 'Draps du dessous', 'type' => 'countable'],
            ['nom' => 'Draps', 'type' => 'countable'],
            ['nom' => 'Couettes', 'type' => 'countable'],
            ['nom' => 'Housses de couette', 'type' => 'countable'],
            ['nom' => 'Couvertures', 'type' => 'countable'],
            ['nom' => 'Alaises', 'type' => 'countable'],
            ['nom' => 'Couvre-lits', 'type' => 'countable'],
        ],
        
        // 3.4 Équipements divers - Salle de bain
        'Linge de salle de bain' => [
            ['nom' => 'Peignoirs de bain', 'type' => 'countable'],
            ['nom' => 'Serviettes de bain', 'type' => 'countable'],
            ['nom' => 'Serviettes de toilette', 'type' => 'countable'],
            ['nom' => 'Gants de toilette', 'type' => 'countable'],
        ],
        
        // 3.4 Équipements divers - Linge de maison
        'Linge de maison' => [
            ['nom' => 'Nappes', 'type' => 'countable'],
            ['nom' => 'Serviettes de table', 'type' => 'countable'],
        ],
        
        // 3.4 Équipements divers - Divers
        'Divers' => [
            ['nom' => 'Coussins', 'type' => 'countable'],
        ],
    ];
}

/**
 * Generate initial inventory data structure from standard items
 * @return array Formatted data for JSON storage with both entry and exit fields initialized
 */
function generateStandardInventoryData() {
    $items = getStandardInventaireItems();
    $data = [];
    $itemIndex = 0;
    
    foreach ($items as $categoryName => $categoryContent) {
        // Check if category has subcategories (like État des pièces)
        if ($categoryName === 'État des pièces') {
            foreach ($categoryContent as $subcategoryName => $subcategoryItems) {
                foreach ($subcategoryItems as $item) {
                    $data[] = [
                        'id' => ++$itemIndex,
                        'categorie' => $categoryName,
                        'sous_categorie' => $subcategoryName,
                        'nom' => $item['nom'],
                        'type' => $item['type'],
                        'entree' => [
                            'nombre' => null,
                            'bon' => false,
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
        } else {
            // Simple category (flat list of items)
            foreach ($categoryContent as $item) {
                $data[] = [
                    'id' => ++$itemIndex,
                    'categorie' => $categoryName,
                    'sous_categorie' => null,
                    'nom' => $item['nom'],
                    'type' => $item['type'],
                    'entree' => [
                        'nombre' => null,
                        'bon' => false,
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
    }
    
    return $data;
}
