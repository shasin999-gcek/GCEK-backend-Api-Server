<?php

use Slim\Http\Request;
use Slim\Http\Response;
use \Firebase\JWT\JWT;

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
	$stmt = $this->db->prepare("SELECT id, login_id, password, verified, blocked from users WHERE login_id=:username");

	$stmt->execute(array(":username" => $username));

	$user = $stmt->fetch(PDO::FETCH_ASSOC);

	if(!$user) {
		return $response->withJSON(["status" => "failed", "msg" => "User with login id $username not found"]);
	}

	if(!$user["verified"]) {
		return $response->withJSON(["status" => "failed", "msg" => "User with login id $username not verified"]);
	}

	if($user["blocked"]) {
		return $response->withJSON(["status" => "failed", "msg" => "User with login id $username blocked"]);
	}

	$hashedPassword = $user["password"];
	
	if(password_verify($password, $hashedPassword)) {

		$tokenId    = base64_encode(rand(10000,99999));
		$issuedAt   = time();
		$notBefore  = $issuedAt + 10;             //Adding 10 seconds
		$expire     = $notBefore + 60 * 60 * 24 * 30;            // Adding 60 seconds
		// $serverName = $this->config->get('serverName'); // Retrieve the server name from config file
		
		/*
		* Create the token as an array
		*/
		$data = [
			'iat'  => $issuedAt,         // Issued at: time when the token was generated
			'jti'  => $tokenId,          // Json Token Id: an unique identifier for the token
			'iss'  => $serverName,       // Issuer
			'nbf'  => $notBefore,        // Not before
			'exp'  => $expire,           // Expire
			'data' => [                  // Data related to the signer user
				'userId'   => $user["id"], // userid from the users table
				'userName' => $username, // User name
			]
		];
		
		$secretKey = base64_decode($this->config->get("app.secretKey"));

		$jwt = JWT::encode(
			$data,      //Data to be encoded in the JWT
			$secretKey, // The signing key
			'HS512'    
			);

		return $response->withJSON(["status" => "success", "access_token" => $jwt]);	

	}

	return $response->withJSON(["status" => "failed", "msg" => "Username and Password are Incorrect"]);
});


$app->post("/user/verify", function(Request $request, Response $response, $args) {
	$username = $request->getParsedBodyParam('username');
	$verify_code = $request->getParsedBodyParam("verify_code");

	$stmt = $this->db->prepare("SELECT * FROM users WHERE login_id=?");
	$stmt->execute(array($username));
	$resultset = $stmt->fetch(PDO::FETCH_ASSOC);

	if($resultset["email_verify_token"] == $verify_code) {
		$stmt = $this->db->prepare("UPDATE users SET verified=:isVerified WHERE login_id=:login_id");
		$stmt->execute(array("isVerified" => "1", "login_id" => $username));

		$stmt = $this->db->prepare("SELECT * FROM gcek_student_info WHERE admission_no=?");
		$stmt->execute(array($username));
		$studentInfo = $stmt->fetch(PDO::FETCH_ASSOC);

		$stmt = $this->db
			->prepare("INSERT INTO user_profiles(user_id, username, fullname, batch, branch) VALUES (:user_id, :username, :fullname, :batch, :branch)");

		$stmt->execute(array(
			":user_id" => $resultset["id"],
			":username" => strtolower(str_replace(" ", "-", trim($studentInfo["name"]))),
			":fullname" => $studentInfo["name"],
			":batch" => $studentInfo["batch"],
			":branch" => $studentInfo["branch"]
		));

		return $response->withJSON(["status" => "success", "msg" => "Email Verified Successfully"]);
	}

  return $response->withJSON(["status" => "failed", "msg" => "Email Verification"]);
});


$app->get('/user/profile', function(Request $request, Response $response) {
	return $response->withJSON($request->getAttribute("token"));
})->add($auth);