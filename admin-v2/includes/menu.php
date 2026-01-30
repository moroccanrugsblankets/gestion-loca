<?php
/**
 * Unified menu for all admin-v2 pages
 * My Invest Immobilier
 */

// Get the current page to highlight active menu item
$current_page = basename($_SERVER['PHP_SELF']);

// Map detail pages to their parent menu items
$page_to_menu_map = [
    'candidature-detail.php' => 'candidatures.php',
    'candidature-actions.php' => 'candidatures.php',
    'add-note-candidature.php' => 'candidatures.php',
    'send-email-candidature.php' => 'candidatures.php',
    'change-status.php' => 'candidatures.php',
    'generer-contrat.php' => 'contrats.php',
    'envoyer-signature.php' => 'contrats.php',
    'create-etat-lieux.php' => 'etats-lieux.php',
];

// Check if current page is a detail page, if so use parent menu
$active_menu = $page_to_menu_map[$current_page] ?? $current_page;
?>
<!-- Sidebar -->
<div class="sidebar">
    <div class="logo">
        <i class="bi bi-building" style="font-size: 2rem;"></i>
        <h4>MY Invest</h4>
        <small>Immobilier</small>
    </div>
    <ul class="nav flex-column mt-4">
        <li class="nav-item">
            <a class="nav-link <?php echo $active_menu === 'index.php' ? 'active' : ''; ?>" href="index.php">
                <i class="bi bi-speedometer2"></i> Tableau de bord
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?php echo $active_menu === 'candidatures.php' ? 'active' : ''; ?>" href="candidatures.php">
                <i class="bi bi-file-earmark-text"></i> Candidatures
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?php echo $active_menu === 'logements.php' ? 'active' : ''; ?>" href="logements.php">
                <i class="bi bi-house-door"></i> Logements
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?php echo $active_menu === 'contrats.php' ? 'active' : ''; ?>" href="contrats.php">
                <i class="bi bi-file-earmark-check"></i> Contrats
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?php echo $active_menu === 'parametres.php' ? 'active' : ''; ?>" href="parametres.php">
                <i class="bi bi-gear"></i> Paramètres
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?php echo $active_menu === 'cron-jobs.php' ? 'active' : ''; ?>" href="cron-jobs.php">
                <i class="bi bi-clock-history"></i> Tâches Automatisées
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?php echo $active_menu === 'email-templates.php' ? 'active' : ''; ?>" href="email-templates.php">
                <i class="bi bi-envelope"></i> Templates d'Email
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?php echo $active_menu === 'etats-lieux.php' ? 'active' : ''; ?>" href="etats-lieux.php">
                <i class="bi bi-clipboard-check"></i> États des lieux
            </a>
        </li>
    </ul>
    <a href="logout.php" class="btn btn-outline-light logout-btn">
        <i class="bi bi-box-arrow-right"></i> Déconnexion
    </a>
</div>
