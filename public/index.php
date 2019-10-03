<?php

// Quick and dirty static file server using php built in server
if (php_sapi_name() == 'cli-server') {
    $extensions = array('php', 'jpg', 'css', 'js', 'otf', 'ttf', 'woff', 'woff2');
    $path = parse_url($_SERVER["REQUEST_URI"], PHP_URL_PATH);
    $ext = pathinfo($path, PATHINFO_EXTENSION);

    if (in_array($ext, $extensions)) {
        return false;
    }
}

session_start();

$appRootDir = dirname(__DIR__);

require_once "{$appRootDir}/vendor/autoload.php";

require_once "{$appRootDir}/app/utils/LayoutView.php";

$app = new \Slim\Slim([
    'templates.path' => "{$appRootDir}/app/views",
    'view'           => new LayoutView("{$appRootDir}/app/views/base_layout.html"),
]);

$app->rootDir = $appRootDir;
$app->rootUrl = $app->request->getUrl() . dirname($app->request->getRootUri());

$app->container->singleton('db', function() use ($app, $appRootDir) {
    $config = require "{$appRootDir}/app/config.php";
    // usa parametri di connessione diversi, con relativi permessi diversi sugli
    // oggetti del DB, a seconda del ruolo dell'utente connesso (utente o ristoratore)
    $role = (isset($_SESSION['user']) && $_SESSION['user']['is_admin']) ? 'admin' : 'user';
    $args = $config[$role];

    try {
        return new PDO("pgsql:dbname={$args['dbname']};host={$args['host']};port={$args['port']};user={$args['user']};password={$args['password']}");
    } catch(PDOException $e) {
        echo $e;
    }
});

$app->view->appendData([
    'user'     => isset($_SESSION['user']) ? $_SESSION['user'] : null,
    'root_url' => $app->rootUrl,
    'url_for'  => function($name, $params = []) use ($app) {
        return $app->urlFor($name, $params);
    },
]);


/**
 * Homepage
 * --------
 */
$app->get('/', function() use ($app) {
    $app->render('index.html', [
        'page_title' => 'Homepage',
    ]);
})->name('index');

require_once "{$appRootDir}/app/routes/error.php";
require_once "{$appRootDir}/app/routes/auth.php";
require_once "{$appRootDir}/app/routes/user.php";

if (isset($_SESSION['user']) && $_SESSION['user']['is_admin']) {
    require_once "{$appRootDir}/app/routes/admin.php";
}

$app->run();
