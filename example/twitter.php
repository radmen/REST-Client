<?php

use Rest\Client;

require '../src/autoload.php';

$client = new Client('http://api.twitter.com/');
$status_list = $client->get('/1/statuses/public_timeline.json', array(
  'trim_user' => true,
  'include_entities' => false,
));

print_r($status_list);