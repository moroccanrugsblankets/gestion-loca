<?php
/**
 * Admin - Détails d'un contrat (AJAX)
 * My Invest Immobilier
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';

$contractId = (int)($_GET['id'] ?? 0);

if ($contractId === 0) {
    echo '<p class="text-danger">ID de contrat invalide.</p>';
    exit;
}

$contrat = fetchOne("SELECT l.*, c.* FROM contrats c INNER JOIN logements l ON c.logement_id = l.id WHERE c.id = ?", [$contractId]);

if (!$contrat) {
    echo '<p class="text-danger">Contrat non trouvé.</p>';
    exit;
}

$locataires = getTenantsByContract($contractId);
?>

<div class="row">
    <div class="col-md-6">
        <h6>Informations du logement</h6>
        <ul class="list-unstyled">
            <li><strong>Référence:</strong> <?= htmlspecialchars($contrat['reference']) ?></li>
            <li><strong>Adresse:</strong> <?= htmlspecialchars($contrat['adresse']) ?></li>
            <li><strong>Appartement:</strong> <?= htmlspecialchars($contrat['appartement']) ?></li>
            <li><strong>Type:</strong> <?= htmlspecialchars($contrat['type']) ?></li>
            <li><strong>Surface:</strong> <?= htmlspecialchars($contrat['surface']) ?> m²</li>
            <li><strong>Loyer:</strong> <?= formatMontant($contrat['loyer']) ?></li>
            <li><strong>Charges:</strong> <?= formatMontant($contrat['charges']) ?></li>
            <li><strong>Dépôt de garantie:</strong> <?= formatMontant($contrat['depot_garantie']) ?></li>
        </ul>
    </div>
    <div class="col-md-6">
        <h6>Informations du contrat</h6>
        <ul class="list-unstyled">
            <li><strong>Statut:</strong> <?= htmlspecialchars($contrat['statut']) ?></li>
            <li><strong>Date de création:</strong> <?= formatDateFr($contrat['date_creation'], 'd/m/Y H:i') ?></li>
            <li><strong>Date d'expiration:</strong> <?= formatDateFr($contrat['date_expiration'], 'd/m/Y H:i') ?></li>
            <?php if ($contrat['date_signature']): ?>
                <li><strong>Date de signature:</strong> <?= formatDateFr($contrat['date_signature'], 'd/m/Y H:i') ?></li>
            <?php endif; ?>
            <li><strong>Nombre de locataires:</strong> <?= $contrat['nb_locataires'] ?></li>
        </ul>
    </div>
</div>

<hr>

<h6>Locataires</h6>
<?php if (empty($locataires)): ?>
    <p class="text-muted">Aucun locataire enregistré.</p>
<?php else: ?>
    <?php foreach ($locataires as $locataire): ?>
        <div class="card mb-3">
            <div class="card-body">
                <h6>Locataire <?= $locataire['ordre'] ?></h6>
                <ul class="list-unstyled mb-0">
                    <li><strong>Nom:</strong> <?= htmlspecialchars($locataire['nom']) ?></li>
                    <li><strong>Prénom:</strong> <?= htmlspecialchars($locataire['prenom']) ?></li>
                    <li><strong>Date de naissance:</strong> <?= formatDateFr($locataire['date_naissance']) ?></li>
                    <li><strong>Email:</strong> <?= htmlspecialchars($locataire['email']) ?></li>
                    
                    <?php if ($locataire['signature_timestamp']): ?>
                        <li><strong>Signature:</strong> ✅ Signé le <?= formatDateFr($locataire['signature_timestamp'], 'd/m/Y H:i') ?></li>
                        <li><strong>IP de signature:</strong> <?= htmlspecialchars($locataire['signature_ip']) ?></li>
                    <?php else: ?>
                        <li><strong>Signature:</strong> ❌ Non signé</li>
                    <?php endif; ?>
                    
                    <?php if ($locataire['piece_identite_recto']): ?>
                        <li><strong>Pièce d'identité:</strong> ✅ Uploadée</li>
                    <?php else: ?>
                        <li><strong>Pièce d'identité:</strong> ❌ Non uploadée</li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    <?php endforeach; ?>
<?php endif; ?>
