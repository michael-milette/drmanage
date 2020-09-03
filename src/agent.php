<?php

$input = '';

while ($rec = fgets(STDIN)) {
  $input .= $rec;
}

$json = json_decode($input);

$options = [
  'http' => [ // use 'http' even if you send the request to https
    'header'  => "Content-type: application/x-www-form-urlencoded\r\n",
    'method'  => 'POST',
    'content' => http_build_query($json->postdata),
    'timeout' => 1000,
  ]
];

$context  = stream_context_create($options);
$result = file_get_contents($json->url, false, $context);

echo $result;
