<?php

use Slim\Http\Request;
use Slim\Http\Response;

// Routes

$app->get("/", function(Request $request, Response $response, $args) {
	$stmt = $this->db->prepare("select * from gcek_student_info where branch='CS' and batch='2K16'");
	$stmt->execute();

	return $response->withJson($stmt->fetchAll(PDO::FETCH_ASSOC));
});

