<?php
defined('BASEPATH') OR exit('No direct script access allowed');

$config["woowup"] = [
"debug" => true,
"sandbox" => true,
"ftp" => ["host"=>'200.61.255.14',
		  "user" => "lamartina",
		  "pass" => "AccesO0207",
		  "remote_folder" => "/lamartina/Woowup/"
		  ],
"apikey" => ["sandbox" => "75f45f0cbbc27892d79b131a1e2178fd6cbade4f09b71cf6ea0db6e1690df3ad",
			 "production"=> "set_key_here"
			],
"folders" => ["sandbox" => ['pending' => "/home/vagrant/htdocs/lamartina/dowloads/pending", "processed"=>"/home/vagrant/htdocs/lamartina/dowloads/processed"],
			  "production" => ['pending' => "set_pending_folder_here", "processed"=>"set_processed_folder_here"]
		     ],
"entities" => ["customers" => "users", "products" => "products", "orders"=> "orders_"],
"file" => ["format"=> "csv", "extension" => "txt", "delimiter"=> ";"]
];
