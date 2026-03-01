<?php
$errorCode    = 401;
$errorTitle   = 'Non Autorisé';
$errorSubtitle = 'Unauthorized';
$errorMsg     = 'Vous devez être connecté pour accéder à cette ressource.';
$errorEmoji   = '🔐';
$errorHint    = 'Connectez-vous à votre compte MAZAR pour continuer.';
$showLogin    = true;
include __DIR__ . '/_error_layout.php';
