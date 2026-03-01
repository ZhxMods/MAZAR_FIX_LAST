<?php
$errorCode    = 400;
$errorTitle   = 'Requête Incorrecte';
$errorSubtitle = 'Bad Request';
$errorMsg     = 'Votre navigateur a envoyé une requête que notre serveur ne peut pas comprendre.';
$errorEmoji   = '⚠️';
$errorHint    = 'Vérifiez l\'URL ou rechargez la page.';
include __DIR__ . '/_error_layout.php';
