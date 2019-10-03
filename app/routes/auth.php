<?php

$disallowAuthIfLoggedIn = function() use ($app) {
    if (isset($_SESSION['user']) && $_SESSION['user']['logged_in']) {
        $app->redirect($app->urlFor('index'));
    }
};

/**
 * Registrazione
 * --------------
 */

$app->get('/register', $disallowAuthIfLoggedIn, function() use ($app) {
    $app->render('register.html', [
        'page_title' => 'Registrati',
    ]);
})->name('register');

$app->post('/register', $disallowAuthIfLoggedIn, function() use ($app) {
    $stmt = $app->db->prepare('
        SELECT crea_utente(:email, :password)
    ');
    $res = $stmt->execute([
        'email'    => $app->request->post('email'),
        'password' => $app->request->post('password'),
    ]);

    if ($res) {
        $app->redirect($app->urlFor('register_success'));
    }

    $app->render('register.html', [
        'page_title'    => 'Registrati',
        'error_message' => 'Email non valida',
    ]);
});

$app->get('/register-success', $disallowAuthIfLoggedIn, function() use ($app) {
    if (strpos($app->request->headers->get('REFERER'), $app->urlFor('register')) === false) {
        $app->redirect($app->urlFor('index'));
    }

    $app->render('register-success.html', [
        'page_title' => 'Registrazione effettuata',
    ]);
})->name('register_success');


/**
 * Autenticazione
 * --------------
 */

$app->get('/login', $disallowAuthIfLoggedIn, function() use ($app) {
    $app->render('login.html', [
        'page_title' => 'Accedi',
    ]);
})->name('login');

$app->post('/login', $disallowAuthIfLoggedIn, function() use ($app) {
    $stmt = $app->db->prepare('
        SELECT utente_id, ruolo FROM autentica(:email, :password)
    ');
    $stmt->execute([
        'email'    => $app->request->post('email'),
        'password' => $app->request->post('password'),
    ]);
    $res = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($res['utente_id']) {
        $_SESSION['user'] = [
            'id'        => $res['utente_id'],
            'is_admin'  => (bool) ($res['ruolo'] === 'ristoratore'),
            'logged_in' => true,
        ];

        $app->redirect($app->urlFor('index'));
    }

    $app->render('login.html', [
        'page_title'    => 'Accedi',
        'error_message' => 'Credenziali invalide',
    ]);
});

$app->get('/logout', function() use ($app) {
    if (isset($_SESSION['user']) && !$_SESSION['user']['logged_in']) {
        $app->redirect($app->urlFor('index'));
    }

    $_SESSION['user'] = null;
    $app->redirect($app->urlFor('login'));
})->name('logout');
