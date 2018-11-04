<?php

use Slim\Http\Request;
use Slim\Http\Response;

// Routes

$app->get("/student/get", function(Request $request, Response $response, $args) {
	if(!($admno = $request->getQueryParam("admno"))) {
		$handler = $this->notFoundHandler;
		return $handler($request, $response);
	}
	
	$stmt = $this->db->prepare("SELECT * FROM gcek_student_info WHERE admission_no=?");
	$stmt->execute(array($admno));

	$data = $stmt->fetch(PDO::FETCH_ASSOC);
  $status = "success";
	if(!$data) {
		$status = "failed";
	}

	return $response->withJSON( array("status" => $status, "data" => $data));
});

$app->post("/user/create", function(Request $request, Response $response, $args) {
	$username = $request->getParsedBodyParam("username");
	$email = $request->getParsedBodyParam("email");
	$password = $request->getParsedBodyParam("password");
	$confirmPassword = $request->getParsedBodyParam("confirmPassword");

	if(!$username || !$email || !$password || !$confirmPassword) {
		$handler = $this->notFoundHandler;
		return $handler($request, $response);
	}

	if($password != $confirmPassword) {
		return $response->withJSON(["errMsg" => "Password not matched"]);
	}

	$stmt = $this->db->prepare(
		"INSERT INTO users(login_id, password, email, email_verify_token) 
		 VALUES (:login_id, :password, :email, :email_verify_token)");

	$email_verify_token = rand(10000,99999);

	try {
		$stmt->execute(array(
			":login_id" => $username,
			":password" => password_hash($password, PASSWORD_DEFAULT),
			":email" => $email,
			":email_verify_token" => $email_verify_token
		));
	} catch(PDOException $e) {
		return $response->withJSON($e);
	}

	$stmt = $this->db->prepare("SELECT * FROM gcek_student_info WHERE admission_no=?");
	try {
		$stmt->execute(array($username));
	} catch(PDOException $e) {
		return $response->withJSON($e);
	}

	$userInfo = $stmt->fetch(PDO::FETCH_ASSOC);

	try {
		//Recipients
		$this->mail->addAddress($email, $userInfo["name"]);    

		//Content
		$this->mail->isHTML(true);                                  // Set email format to HTML
		$this->mail->Subject = 'Verify your email address with gcekfb';
		$this->mail->Body    = "Here is your verification code <br> <b>$email_verify_token</b>";

		$this->mail->send();

		return $response->withJSON(["status" => "success", "msg" => "Verification mail is send"]);
		
	} catch (Exception $e) {
		return $response->withJSON(["status" => "failed", "msg" => "Verification mail cannot be send"]);
	}

}); 


$app->post("/user/login", function(Request $request, Response $response, $args) {
	$username = $request->getParsedBodyParam("username");
	$password = $request->getParsedBodyParam("password");
	
	if(!$username || !$password) {
		$handler = $this->notFoundHandler;
		return $handler($request, $response);
	}

	// check whether the username exists
	$stmt = $this->db->prepare("SELECT login_id, password, verified, blocked from users WHERE login_id=:username");

	$stmt->execute(array(":username" => $username));

	$user = $stmt->fetch(PDO::FETCH_ASSOC);

	if(!$user) {
		return $response->withJSON(["status" => "failed", "msg" => "User with login id $username not found"]);
	}

	if(!$user["verified"]) {
		return $response->withJSON(["status" => "failed", "msg" => "User with login id $username not verified"]);
	}

	if(!$user["blocked"]) {
		return $response->withJSON(["status" => "failed", "msg" => "User with login id $username blocked"]);
	}

	$hashedPassword = $user["password"];
	
	if(password_verify($password, $hashedPassword)) {
		return $response->withJSON(["status" => "logged in"]);
	}

});

