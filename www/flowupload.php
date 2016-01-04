<?php
//
// Copyright (c) Autodesk, Inc. All rights reserved
//
// Large Model Viewer Extractor
// by Cyrille Fauvel - Autodesk Developer Network (ADN)
// January 2015
//
// Permission to use, copy, modify, and distribute this software in
// object code form for any purpose and without fee is hereby granted,
// provided that the above copyright notice appears in all copies and
// that both that copyright notice and the limited warranty and
// restricted rights notice below appear in all supporting
// documentation.
//
// AUTODESK PROVIDES THIS PROGRAM "AS IS" AND WITH ALL FAULTS.
// AUTODESK SPECIFICALLY DISCLAIMS ANY IMPLIED WARRANTY OF
// MERCHANTABILITY OR FITNESS FOR A PARTICULAR USE.  AUTODESK, INC.
// DOES NOT WARRANT THAT THE OPERATION OF THE PROGRAM WILL BE
// UNINTERRUPTED OR ERROR FREE.
//
ini_set ('display_errors', E_ALL) ;
require_once __DIR__ . '/../vendor/autoload.php' ;
date_default_timezone_set ('UTC') ;
mb_internal_encoding ('UTF-8') ;
mb_http_output ('UTF-8') ;

use ADN\Extract\utils ;

Symfony\Component\Debug\Debug::enable () ;

$config =new \Flow\Config () ;
$config->setTempDir ('../tmp') ;
$request =new \Flow\Request () ;
$identifier =$request->getIdentifier () ;
if ( \Flow\Basic::save ("../tmp/$identifier", $config, $request) ) {
	// File saved successfully and can be accessed at './final_file_destination'
	utils::log ("POST {$request->getIdentifier} ") ;
	$data =(object)array (
		'key' => $identifier,
		'name' => $request->getFileName (),
		"size" => $request->getTotalSize (),
		"bytesRead" => $request->getTotalSize (),
		"bytesPosted" => 0
	) ;
	$path =utils::normalize (__DIR__ . "/../data/$identifier.json") ;
	if ( file_put_contents ($path, json_encode ($data)) === false )
		utils::log ("Coud not save - $path") ;
} else {
	// This is not a final chunk or request is invalid, continue to upload.
}
