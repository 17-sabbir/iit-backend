<?php

return [
	'default' => env('MAIL_MAILER', 'log'),
	'mailers' => [
		'smtp' => [
			'transport' => 'smtp',
			'scheme' => env('MAIL_SCHEME'),
			'url' => env('MAIL_URL'),
			'host' => env('MAIL_HOST', '127.0.0.1'),
			'port' => (int) env('MAIL_PORT', 2525),
			'username' => env('MAIL_USERNAME'),
			'password' => env('MAIL_PASSWORD'),
			'timeout' => null,
			'local_domain' => env('MAIL_EHLO_DOMAIN'),
		],
		'log' => [
			'transport' => 'log',
			'channel' => env('MAIL_LOG_CHANNEL'),
		],
		'array' => [
			'transport' => 'array',
		],
	],
	'from' => [
		'address' => env('MAIL_FROM_ADDRESS', 'noreply@iitshelf.edu'),
		'name' => env('MAIL_FROM_NAME', 'IIT Shelf Library'),
	],
];
