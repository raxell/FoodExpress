<?php

$requireAuthentication = function() use ($app) {
    if (!isset($_SESSION['user']) || !$_SESSION['user']['logged_in']) {
        $app->redirect($app->urlFor('login'));
    }
};

/**
 * Modifica password
 * -----------------
 */

$app->get('/user/change-password', $requireAuthentication, function() use ($app) {
    $app->render('change-password.html', [
        'page_title' => 'Modifica password',
    ]);
})->name('change_password');

$app->post('/user/change-password', $requireAuthentication, function() use ($app) {
    $stmt = $app->db->prepare('
        SELECT modifica_password(:id, :old_password, :new_password);
    ');
    $stmt->execute([
        'id' => $_SESSION['user']['id'],
        'old_password' => $app->request->post('old_password'),
        'new_password' => $app->request->post('new_password'),
    ]);
    $res = $stmt->fetch(PDO::FETCH_ASSOC)['modifica_password'];

    $app->render('change-password.html', [
        'page_title'      => 'Modifica password',
        'error_message'   => !$res ? 'Password errata' : null,
        'success_message' => $res ? 'Password modificata con successo' : null,
    ]);
});


/**
 * Esecuzione ordini
 * -----------------
 */

$app->get('/order', $requireAuthentication, function() use ($app) {
    $stmt = $app->db->query('
        SELECT P.piatto_id, P.titolo, P.descrizione, P.foto, PG.prezzo, PG.quantita
        FROM piatto_giorno PG
            JOIN piatto P on PG.piatto = P.piatto_id
        WHERE PG.giorno = CURRENT_DATE AND quantita > 0
        ORDER BY piatto_id
    ');

    $app->render('order.html', [
        'page_title' => 'Ordina piatti',
        'dishes'     => (array) $stmt->fetchAll(PDO::FETCH_ASSOC),
    ]);
})->name('order');

$app->post('/order', $requireAuthentication, function() use ($app) {
    $errorMessage = '';
    $selectedDishes = (array) $app->request->post('selected');
    $dishes = $app->request->post('dish');

    if (count($selectedDishes) === 0) $error = true; // nessun piatto selezionato

    // la creazione dell'ordine viene gestita come un'unica transazione, se
    // qualcosa va storto non ha senso mantenere solo una parte dell'ordine
    $app->db->beginTransaction();

    // indirizzo
    $stmt = $app->db->prepare('
        INSERT INTO indirizzo(via, cap)
            VALUES (:via, :cap)
        ON CONFLICT(via) DO UPDATE SET cap = :cap
    ');
    if (!$stmt->execute([
        'via' => $app->request->post('address'),
        'cap' => $app->request->post('cap'),
    ])) {
        $errorMessage = 'Non è prevista consegna per l\'indirizzo specificato';
    }

    // ordine
    $stmt = $app->db->prepare('
        INSERT INTO ordine(utente, nome, cognome, telefono, ora_consegna, indirizzo)
            VALUES (:utente, :nome, :cognome, :telefono, :ora_consegna, :indirizzo)
        RETURNING ordine_id
    ');
    if (!$stmt->execute([
        'utente'       => $_SESSION['user']['id'],
        'nome'         => $app->request->post('first_name'),
        'cognome'      => $app->request->post('last_name'),
        'telefono'     => $app->request->post('phone'),
        'ora_consegna' => (($h = $app->request->post('time')) !== '') ? $h : null,
        'indirizzo'    => $app->request->post('address'),
    ])) {
        $errorMessage = $errorMessage ?: 'Generalità inserite non valide';
    }

    $orderId = $stmt->fetch(PDO::FETCH_ASSOC)['ordine_id'];

    // fattura
    if ($app->request->post('invoice') !== null) {
        $stmt = $app->db->prepare('
            INSERT INTO fattura(ordine, codice_fiscale, partita_iva, societa, indirizzo_fiscale)
                VALUES (:ordine, :codice_fiscale, :partita_iva, :societa, :indirizzo_fiscale)
        ');
        if (!$stmt->execute([
            'ordine'            => $orderId,
            'codice_fiscale'    => $app->request->post('ssn'),
            'partita_iva'       => (($h = $app->request->post('p_iva')) !== '') ? $h : null,
            'societa'           => (($h = $app->request->post('company')) !== '') ? $h : null,
            'indirizzo_fiscale' => (($h = $app->request->post('tax_address')) !== '') ? $h : null,
        ])) {
            $errorMessage = $errorMessage ?: 'Dati di fatturazione non validi';
        }
    }

    // piatti ordine
    $stmt = $app->db->prepare("
        INSERT INTO preparazione(ordine, piatto, linea, giorno, progressivo_piatto)
            VALUES (:ordine, :piatto, linea_meno_carica(), CURRENT_DATE, :progressivo_piatto)
    ");

    foreach ($selectedDishes as $id) {
        for ($i = 1; $i <= $dishes[$id]['amount']; $i++) {
            if (!$stmt->execute([
                'ordine'             => $orderId,
                'piatto'             => $id,
                'progressivo_piatto' => $i,
            ])) {
                $errorMessage = $errorMessage ?: 'Impossibile creare l\'ordine, verificare i dati inseriti.<br> Se i dati risultano corretti potrebbero non esserci linee di preparazione attive per preparare l\'ordine.';
            }
        }
    }

    if ($errorMessage) {
        $app->db->rollBack();
        $app->flash('error_message', $errorMessage);
    } else {
        $app->db->commit();

        $stmt = $app->db->prepare('SELECT calcola_ora_consegna(:ordine)');
        $stmt->execute(['ordine' => $orderId]);
        $time = $stmt->fetch(PDO::FETCH_ASSOC)['calcola_ora_consegna'];

        // consegna l'ordine appena è pronto se non è stato specificato un orario
        if ($app->request->post('time') === '') {
            $app->flash('success_message',
                'Ordine eseguito correttamente. Sarà consegnato alle ore: ' . date('H:i', strtotime($time)));
        } else {
            $userTime = strtotime($app->request->post('time'));
            $computedTime = strtotime($time);

            // se c'è più di un minuto di differenza dall'ora indicata chiedi conferma
            if ($computedTime - $userTime > 60) {
                $_SESSION['order'] = [
                    'id'            => $orderId,
                    'computed_time' => $time,
                ];
            } else {
                $app->flash('success_message', 'Ordine eseguito correttamente. Sarà consegnato all\'ora indicata');
            }
        }
    }

    $app->redirect($app->urlFor('order'));
});

$app->post('/order-confirm', $requireAuthentication, function() use ($app) {
    $orderId = $_SESSION['order']['id'];
    $_SESSION['order'] = null;

    if ($app->request->post('confirm') === 'no') {
        $stmt = $app->db->prepare('
            DELETE FROM preparazione WHERE ordine = :ordine
        ');
        $stmt->execute(['ordine' => $orderId]);

        $stmt = $app->db->prepare('
            DELETE FROM ordine WHERE ordine_id = :ordine
        ');
        $stmt->execute(['ordine' => $orderId]);

        $app->flash('error_message', 'Ordine annullato');
    } else {
        $app->flash('success_message', 'Ordine confermato');
    }

    $app->redirect($app->urlFor('order'));
})->name('order_confirm');


/**
 * Ordini effettuati
 * -----------------
 */

$app->get('/user/order-history', $requireAuthentication, function() use ($app) {
    $stmt = $app->db->prepare("
        SELECT ordine_id AS id, to_char(istante_ordine, 'DD/MM/YYYY HH24:MI') AS data
        FROM ordine
        WHERE utente = :utente
        ORDER BY istante_ordine DESC
    ");
    $stmt->execute(['utente' => $_SESSION['user']['id']]);
    $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $stmt = $app->db->prepare('
        SELECT O.ordine_id, O.istante_ordine, D.titolo, PG.prezzo, COUNT(*) AS quantita
        FROM preparazione P
            JOIN ordine O ON P.ordine = O.ordine_id
            JOIN piatto D ON P.piatto = D.piatto_id
            JOIN piatto_giorno PG ON P.piatto = PG.piatto AND P.giorno = PG.giorno
        WHERE utente = :utente
        GROUP BY O.ordine_id, O.istante_ordine, D.titolo, PG.prezzo
    ');
    $stmt->execute(['utente' => $_SESSION['user']['id']]);
    $dishes = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $app->db->exec('REFRESH MATERIALIZED VIEW coda_preparazione');
    $stmt = $app->db->query('SELECT DISTINCT ordine FROM coda_preparazione');
    $pendingOrders = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $app->render('order-history.html', [
        'page_title'     => 'Storico ordini effettuati',
        'orders'         => $orders,
        'pending_orders' => $pendingOrders,
        'dishes'         => $dishes,
    ]);
})->name('order_history');


/**
 * Ordini in attesa
 * ----------------
 */

$app->get('/user/pending-orders', $requireAuthentication, function() use ($app) {
    $stmt = $app->db->prepare("
        SELECT O.ordine_id, P.titolo, to_char(O.istante_ordine, 'DD/MM/YYYY HH24:MI') AS data,
            ceil(extract(EPOCH FROM (Q.fine_preparazione - CURRENT_TIMESTAMP)) / 60) AS minuti_mancanti
        FROM coda_preparazione Q
            JOIN piatto P ON Q.piatto = P.piatto_id
            JOIN ordine O ON Q.ordine = O.ordine_id
        WHERE O.utente = :utente AND ceil(extract(EPOCH FROM (Q.fine_preparazione - CURRENT_TIMESTAMP)) / 60) > 0
    ");
    $stmt->execute(['utente' => $_SESSION['user']['id']]);
    $res = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $app->render('pending-orders.html', [
        'page_title'  => 'Ordini in attesa',
        'order_ids'   => array_unique(array_column($res, 'ordine_id')),
        'orders_data' => $res,
    ]);
})->name('pending_orders');
