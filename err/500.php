<?php
$errorCode    = 500;
$errorTitle   = 'Erreur Serveur';
$errorSubtitle = 'Internal Server Error';
$errorMsg     = 'Une erreur interne s\'est produite. Nos équipes techniques ont été notifiées.';
$errorEmoji   = '⚙️';
$errorHint    = 'Veuillez réessayer dans quelques minutes. Si le problème persiste, contactez le support.';
include __DIR__ . '/_error_layout.php';
