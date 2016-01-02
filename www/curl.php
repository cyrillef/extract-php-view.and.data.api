<?php
$url = "http://www.thinkgeek.com";
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL,$url);
curl_setopt($ch, CURLOPT_VERBOSE, 1);
curl_setopt($ch, CURLOPT_POST, 0);
curl_setopt($ch, CURLOPT_RETURNTRANSFER,1);
$returned = curl_exec($ch);
curl_close ($ch);
echo $returned;
