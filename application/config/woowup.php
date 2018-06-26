<?php
defined('BASEPATH') OR exit('No direct script access allowed');

$config["woowup"] = [
"debug" => true,
"sandbox" => false,
"ftp" => ["host"=>'200.61.255.14',
		  "user" => "lamartina",
		  "pass" => "AccesO0207",
		  "remote_folder" => "/lamartina/Woowup/"
		  ],
"apikey" => ["sandbox" => "75f45f0cbbc27892d79b131a1e2178fd6cbade4f09b71cf6ea0db6e1690df3ad",
			 "production"=> "c1fd7f3a5f5dda8e8617850d8cefca72be1ee0cd0f606907586cd7623f649aa6"
			],
"folders" => ["sandbox" => ['pending' => "/home/vagrant/htdocs/lamartina/dowloads/pending", "processed"=>"/home/vagrant/htdocs/lamartina/dowloads/processed"],
			  "production" => ['pending' => "/home/lamartina/pending", "processed"=>"/home/lamartina/processed"]
		     ],
"entities" => ["customers" => "users_", "products" => "products", "orders"=> "orders_"],
"file" => ["format"=> "csv", "extension" => "txt", "delimiter"=> ";"]
];
