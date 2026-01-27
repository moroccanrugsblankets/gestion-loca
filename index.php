<?php
/**
 * Page d'accueil de l'application
 * My Invest Immobilier
 */
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Signature de bail en ligne - MY Invest Immobilier</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <div class="container mt-5">
        <div class="text-center mb-5">
            <img src="assets/images/logo.png" alt="MY Invest Immobilier" class="logo mb-3" 
                 onerror="this.style.display='none'">
            <h1 class="display-4">Signature de Bail en Ligne</h1>
            <p class="lead text-muted">MY Invest Immobilier</p>
        </div>

        <div class="row justify-content-center">
            <div class="col-md-10">
                <div class="row g-4">
                    <!-- Administration -->
                    <div class="col-md-6">
                        <div class="card h-100 shadow">
                            <div class="card-body text-center p-5">
                                <div class="mb-4">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="64" height="64" fill="currentColor" 
                                         class="bi bi-gear-fill text-primary" viewBox="0 0 16 16">
                                        <path d="M9.405 1.05c-.413-1.4-2.397-1.4-2.81 0l-.1.34a1.464 1.464 0 0 1-2.105.872l-.31-.17c-1.283-.698-2.686.705-1.987 1.987l.169.311c.446.82.023 1.841-.872 2.105l-.34.1c-1.4.413-1.4 2.397 0 2.81l.34.1a1.464 1.464 0 0 1 .872 2.105l-.17.31c-.698 1.283.705 2.686 1.987 1.987l.311-.169a1.464 1.464 0 0 1 2.105.872l.1.34c.413 1.4 2.397 1.4 2.81 0l.1-.34a1.464 1.464 0 0 1 2.105-.872l.31.17c1.283.698 2.686-.705 1.987-1.987l-.169-.311a1.464 1.464 0 0 1 .872-2.105l.34-.1c1.4-.413 1.4-2.397 0-2.81l-.34-.1a1.464 1.464 0 0 1-.872-2.105l.17-.31c.698-1.283-.705-2.686-1.987-1.987l-.311.169a1.464 1.464 0 0 1-2.105-.872l-.1-.34zM8 10.93a2.929 2.929 0 1 1 0-5.86 2.929 2.929 0 0 1 0 5.858z"/>
                                    </svg>
                                </div>
                                <h3 class="card-title h4 mb-3">Administration</h3>
                                <p class="card-text text-muted mb-4">
                                    Interface de gestion pour générer des liens de signature et suivre les contrats.
                                </p>
                                <a href="admin/" class="btn btn-primary btn-lg">
                                    Accéder à l'administration
                                </a>
                            </div>
                        </div>
                    </div>

                    <!-- Espace locataire -->
                    <div class="col-md-6">
                        <div class="card h-100 shadow">
                            <div class="card-body text-center p-5">
                                <div class="mb-4">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="64" height="64" fill="currentColor" 
                                         class="bi bi-pencil-square text-success" viewBox="0 0 16 16">
                                        <path d="M15.502 1.94a.5.5 0 0 1 0 .706L14.459 3.69l-2-2L13.502.646a.5.5 0 0 1 .707 0l1.293 1.293zm-1.75 2.456-2-2L4.939 9.21a.5.5 0 0 0-.121.196l-.805 2.414a.25.25 0 0 0 .316.316l2.414-.805a.5.5 0 0 0 .196-.12l6.813-6.814z"/>
                                        <path fill-rule="evenodd" d="M1 13.5A1.5 1.5 0 0 0 2.5 15h11a1.5 1.5 0 0 0 1.5-1.5v-6a.5.5 0 0 0-1 0v6a.5.5 0 0 1-.5.5h-11a.5.5 0 0 1-.5-.5v-11a.5.5 0 0 1 .5-.5H9a.5.5 0 0 0 0-1H2.5A1.5 1.5 0 0 0 1 2.5v11z"/>
                                    </svg>
                                </div>
                                <h3 class="card-title h4 mb-3">Signer mon bail</h3>
                                <p class="card-text text-muted mb-4">
                                    Vous avez reçu un lien par email ? Cliquez dessus pour commencer la signature.
                                </p>
                                <div class="alert alert-info text-start">
                                    <small>
                                        <strong>Note :</strong> Si vous avez reçu un email avec un lien de signature, 
                                        cliquez directement sur le lien fourni dans l'email.
                                    </small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Informations -->
                <div class="card mt-4 shadow">
                    <div class="card-body">
                        <h4 class="card-title">À propos de cette application</h4>
                        <p class="card-text">
                            Cette application permet la signature électronique de contrats de bail en ligne 
                            de manière sécurisée et conforme aux exigences légales.
                        </p>
                        <h5 class="mt-4">Fonctionnalités principales :</h5>
                        <ul>
                            <li>✅ Génération de liens sécurisés avec expiration 24h</li>
                            <li>✅ Parcours de signature pour 1 ou 2 locataires</li>
                            <li>✅ Signature électronique sur canvas HTML5</li>
                            <li>✅ Upload sécurisé de pièces d'identité</li>
                            <li>✅ Génération automatique de PDF du bail signé</li>
                            <li>✅ Envoi d'emails automatiques</li>
                            <li>✅ Suivi des contrats en temps réel</li>
                            <li>✅ Traçabilité complète (IP, horodatage)</li>
                        </ul>
                        <div class="mt-4">
                            <p class="mb-1"><strong>Contact :</strong></p>
                            <p class="mb-0">
                                <a href="mailto:contact@myinvest-immobilier.com">contact@myinvest-immobilier.com</a>
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <footer class="mt-5 py-4 bg-light">
        <div class="container text-center">
            <p class="text-muted mb-0">
                &copy; <?= date('Y') ?> MY Invest Immobilier - Tous droits réservés
            </p>
        </div>
    </footer>
</body>
</html>
