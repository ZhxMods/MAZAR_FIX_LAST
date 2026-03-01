<?php
$errorCode    = 503;
$errorTitle   = 'Service Indisponible';
$errorSubtitle = 'Service Unavailable';
$errorMsg     = 'MAZAR est temporairement en maintenance. Nous revenons très bientôt !';
$errorEmoji   = '🛠️';
$errorHint    = 'Nos équipes travaillent activement pour rétablir le service. Merci de votre patience.';
$isMaintenance = true;
include __DIR__ . '/_error_layout.php';
