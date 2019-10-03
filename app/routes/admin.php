<?php

$requireRoleAdmin = function() use ($app) {
    if (!isset($_SESSION['user']) || !$_SESSION['user']['is_admin']) {
        $app->redirect($app->urlFor('login'));
    }
};

/**
 * Lista piatti
 * ------------
 */

$app->get('/admin/dishes-list', $requireRoleAdmin, function() use ($app) {
    $stmt = $app->db->query('
        SELECT titolo, descrizione, foto, tempo_preparazione
        FROM piatto
    ');

    $app->render('dish-list.html', [
        'page_title' => 'Nuovo piatto',
        'dishes'     => (array) $stmt->fetchAll(PDO::FETCH_ASSOC),
    ]);
})->name('dish_list');

$app->post('/admin/add-dish', $requireRoleAdmin, function() use ($app) {
    if (
        isset($_FILES['picture']) &&
        move_uploaded_file($_FILES['picture']['tmp_name'], "{$app->rootDir}/public/images/{$_FILES['picture']['name']}")
    ) {
        $stmt = $app->db->prepare('
            INSERT INTO piatto(titolo, descrizione, foto, tempo_preparazione, complessita)
                VALUES (:titolo, :descrizione, :foto, :tempo_preparazione, :complessita)
        ');
        $res = $stmt->execute([
            'titolo'             => $app->request->post('title'),
            'descrizione'        => $app->request->post('desc'),
            'foto'               => $_FILES['picture']['name'],
            'tempo_preparazione' => $app->request->post('prep_time'),
            'complessita'        => $app->request->post('complexity'),
        ]);

        if (!$res) {
            $app->flash('error_message', 'Dati inseriti non validi');
        } else {
            $app->flash('success_message', 'Piatto aggiunto correttamente');
        }
    } else {
        $app->flash('error_message', 'Dati inseriti non validi');
    }

    $app->redirect($app->urlFor('dish_list'));
})->name('add_dish');


/**
 * Selezione piatti menu
 * ---------------------
 */
$app->get('/admin/set-menu-dishes', $requireRoleAdmin, function() use ($app) {
    $stmt = $app->db->query('
        SELECT piatto_id, titolo, descrizione, foto, tempo_preparazione
        FROM piatto
    ');

    $app->render('set-menu-dishes.html', [
        'page_title' => 'Imposta piatti menu',
        'dishes'     => (array) $stmt->fetchAll(PDO::FETCH_ASSOC),
    ]);
})->name('set_menu_dishes');

$app->post('/admin/set-menu-dishes', $requireRoleAdmin, function() use ($app) {
    $date = $app->request->post('date');
    $dish = $app->request->post('dish');
    $error = [];

    $stmt = $app->db->prepare('
        INSERT INTO piatto_giorno(piatto, giorno, quantita, prezzo)
            VALUES (:piatto, :giorno, :quantita, :prezzo)
    ');

    foreach ((array) $app->request->post('selected') as $id) {
        if (!$stmt->execute([
            'piatto'    => $id,
            'giorno'    => $date,
            'quantita'  => $dish[$id]['amount'],
            'prezzo'    => $dish[$id]['price'],
        ])) {
            $error[] = $id;
        }
    }

    // $error contiene gli id dei piatti il cui inserimento non è andato a buon fine, può
    // quindi essere usato per mostrare all'utente i piatti che POTREBBERO dover essere reinseriti.
    // NB: l'errore nell'inserimento potrebbe significare anche che il piatto è già presente,
    // in tal caso provare a reinserirlo continuerebbe a generare l'errore
    if (count($error) > 0) {
        $app->flash('menu_dishes_error', $error);
    } elseif (count((array) $app->request->post('selected')) > 0) {
        $app->flash('success_message', "Menu per il giorno {$date} creato correttamente");
    }

    $app->redirect($app->urlFor('set_menu_dishes'));
});


/**
 * Gestione aree consegna
 * ----------------------
 */

$app->get('/admin/set-delivery-areas', $requireRoleAdmin, function() use ($app) {
    $stmt = $app->db->query('
        SELECT cap FROM area_consegna
    ');

    $app->render('set-delivery-areas.html', [
        'page_title' => 'Imposta aree consegna',
        'areas'      => (array) $stmt->fetchAll(PDO::FETCH_ASSOC),
    ]);
})->name('set_delivery_areas');

$app->post('/admin/set-delivery-areas', $requireRoleAdmin, function() use ($app) {
    $stmt = $app->db->prepare('
        INSERT INTO area_consegna(cap)
            VALUES (:cap)
    ');

    if (!$stmt->execute(['cap' => $app->request->post('cap')])) {
        $app->flash('error_message', 'CAP invalido');
    } else {
        $app->flash('success_message', 'Area di consegna aggiunta correttamente');
    }

    $app->redirect($app->urlFor('set_delivery_areas'));
});


/**
 * Gestione linee preparazione
 * ---------------------------
 */

$app->get('/admin/set-workers', $requireRoleAdmin, function() use ($app) {
    $stmt = $app->db->query('
        SELECT lavoratore_id, nome, cognome
        FROM lavoratore
    ');

    $app->render('set-workers.html', [
        'page_title' => 'Imposta linee preparazione',
        'workers'    => (array) $stmt->fetchAll(PDO::FETCH_ASSOC),
    ]);
})->name('set_workers');

$app->post('/admin/set-workers', $requireRoleAdmin, function() use ($app) {
    $stmt = $app->db->prepare('
        INSERT INTO linea_preparazione(lavoratore, giorno)
            VALUES (:lavoratore, :giorno)
    ');

    foreach ((array) $app->request->post('worker') as $worker) {
        $stmt->execute([
            'lavoratore' => $worker,
            'giorno'     => $app->request->post('date'),
        ]);
    }

    $app->flash('success_message', 'Linee di preparazione impostate correttamente');

    $app->redirect($app->urlFor('set_workers'));
});


/**
 * Statistiche linee preparazione
 * ------------------------------
 */

$app->get('/admin/worker-stats', $requireRoleAdmin, function() use ($app) {
    $stmt = $app->db->query('
        SELECT S.*, L.nome, L.cognome FROM statistiche_linea S
            JOIN lavoratore L ON S.linea = L.lavoratore_id
        ORDER BY S.giorno ASC, S.linea
    ');

    $app->render('worker-stats.html', [
        'page_title' => 'Statistiche linee preparazione',
        'stats'      => $stmt->fetchAll(PDO::FETCH_ASSOC),
    ]);
})->name('worker_stats');
