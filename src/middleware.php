<?php
// Application middleware

// e.g: $app->add(new \Slim\Csrf\Guard);
use \Firebase\JWT\JWT;

$auth = function ($request, $response, $next) {
    if ($request->hasHeader('Authorization')) {
        $jwt_token = $request->getHeaderLine('Authorization');

        $secretKey = base64_decode($this->config->get("app.secretKey"));


        $token = JWT::decode($jwt_token, $secretKey, array('HS512'));

        $request = $request->withAttribute('token', $token);
    }

    return $next($request, $response);
};
