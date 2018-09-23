<?php
// DIC configuration

$container = $app->getContainer();

// monolog
$container['logger'] = function ($c) {
    $settings = $c->get('settings')['logger'];
    $logger = new Monolog\Logger($settings['name']);
    $logger->pushProcessor(new Monolog\Processor\UidProcessor());
    $logger->pushHandler(new Monolog\Handler\StreamHandler($settings['path'], $settings['level']));
    return $logger;
};

// database

$container['db'] = function($c) {
    $db = $c->config->get("db");
    $pdo = new PDO('mysql:host=' . $db['host'] . ';dbname=' . $db['dbname'], $db['user'], $db['password']);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    return $pdo;
};

//not found handler

$container["notFoundHandler"] = function($c) {
	return function($request, $response) use ($c) {
		return $c["response"]
			->withStatus(404)
            ->withHeader('Content-Type', 'text/html')
            ->write('Page not found');
	};
};


