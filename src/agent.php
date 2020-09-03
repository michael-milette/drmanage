<?php
/**
 * This program is an agent which handles a remote-management request that is expected to take @author duncan
 * long time (more than 30 seconds). It is designed to operate through a pipe. It reads the JSON request from STDIN
 * and POSTs a request to the remote server, then waits for a response. The remote server will keep the connection
 * open by sending newlines regularly. When the operation is complete, the results are returned back to the caller.
 */

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
echo trim(file_get_contents($json->url, false, $context));
