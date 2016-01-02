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
namespace ADN\Extract ;

class utils {
	
	public static function log ($msg) {
		file_put_contents ('php://stderr', $msg . "\n") ;
	}
	
	public static function realpath ($path) {
		$rpath =realpath ($path) ;
		if ( $rpath != false )
			$rpath =str_replace ('\\', '/', $rpath) ;
		return ($rpath) ;
	}
	
	public static function normalize ($path) {
		$rpath =utils::realpath ($path) ;
		if ( $rpath != false )
			return ($rpath) ;
		
		$path =str_replace ('\\', '/', $path) ;
		$root =($path [0] === '/') ? '/' : '' ;
	
		$segments =explode ('/', trim ($path, '/')) ;
		$ret =array () ;
		foreach ( $segments as $segment ){
			if ( ($segment == '.') || empty ($segment) )
				continue ;
			if ( $segment == '..' )
				array_pop ($ret) ;
			else
				array_push ($ret, $segment) ;
		}
		return ($root . implode ('/', $ret)) ;
	}
	
	public static function buildStringFromArray ($arr, $pairGlue ='=', $lineGlue ='&', $bEncode =true) {
		return (join ($lineGlue,
			array_map (
				function ($v, $k, $glue, $bEncode) { return ($k . $glue . ($bEncode ? urlencode ($v) : $v)) ; },
				$arr,
				array_keys ($arr),
				array_fill (0, count ($arr), $pairGlue),
				array_fill (0, count ($arr), $bEncode)
			)
		)) ;
	}
	
	static function postStr ($needle, $st) {
		list (, $rest) =explode ($needle, $st, 2) ;
		return ($rest) ;
	}
	
}
