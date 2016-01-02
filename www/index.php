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

use ADN\Extract\Application ;

Symfony\Component\Debug\Debug::enable () ;
$app =new Application ('dev') ;
$app ['debug'] =true ;
$app->run () ;

//$app =new App\Application ('prod') ;
//$app ['http_cache']->run () ;
