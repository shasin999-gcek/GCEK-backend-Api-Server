<?php

use PHPMailer\PHPMailer\PHPMailer;

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
            ->withJSON(array("status" => "failed", "msg" => "Not Found"));
	};
};

// email handler

$container["mail"] = function($c) {
    $mail = new PHPMailer(true);

    //Server settings                            // Enable verbose debug output
    $mail->isSMTP();                                      // Set mailer to use SMTP
    $mail->Host = 'smtp.gmail.com';  // Specify main and backup SMTP servers
    $mail->SMTPAuth = true;                               // Enable SMTP authentication
    $mail->Username = 'bugsmasher2k18@gmail.com';                 // SMTP username
    $mail->Password = 'bugs2solve';                           // SMTP password
    $mail->SMTPSecure = 'tls';                            // Enable TLS encryption, `ssl` also accepted
    $mail->Port = 587;                                    // TCP port to connect to

    $mail->setFrom('no-reply@gcekfb.com', 'GCEK Facebook');
    $mail->addReplyTo('no-reply@gcekfb.com', 'You cannot replied to this mail');

    return $mail;
};


