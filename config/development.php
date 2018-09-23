<?php

  return [
    "app" => [
      "url" => "localhost",
      "hash" => [
        "algo" => PASSWORD_BCRYPT,
        "cost" => 1000
      ]
    ],

    "db" => [
      "host" => "127.0.0.1",
      "dbname" => "homestead",
      "user" => "homestead",
      "password" => "secret",
      "port" => ""
    ]
  ];