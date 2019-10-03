<?php

/**
 * Pagine di errore
 * ----------------
 */

$app->notFound(function () use ($app) {
    $app->render('404.html', [
        'page_title' => '404 Page Not Found',
    ]);
});
