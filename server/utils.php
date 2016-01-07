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
	
	public static function log ($msg, $ending ="\n") {
		file_put_contents ('php://stderr', "{$msg}{$ending}") ;
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
	
	public static function postStr ($needle, $st) {
		list (, $rest) =explode ($needle, $st, 2) ;
		return ($rest) ;
	}
	
	public static function findKey ($arr, $name) {
		$name =strtolower ($name) ;
		$filtered =array_filter (
			$arr,
			function ($v, $k) use ($name) {
				return (strtolower ($k) == $name) ;
			},
			ARRAY_FILTER_USE_BOTH
		) ;
		if ( count ($filtered) == 0 )
			return (null) ;
		reset ($filtered) ;
		$first_key =key ($filtered) ;
		return ($filtered [$first_key]) ;
	}
	
	public static function executeScript ($scriptAndArgs, $bPassKeys =false, $scriptDir =__DIR__) {
		$scriptDir =utils::normalize ($scriptDir) ;
		$bWindows =substr (php_uname (), 0, 7) == 'Windows' ;
		$cmd =$bWindows ? '"C:/Program Files/PHP.5.6.16/php.exe" ' : '/usr/bin/php -q ' ;
		$cmd .="{$scriptDir}{$scriptAndArgs}" ;
		if ( $bPassKeys === true ) {
			$config =lmv::config () ;
			$cmd .=" --keys CONSUMERKEY={$config ['credentials'] ['client_id']}"
				 . " --keys CONSUMERSECRET={$config ['credentials'] ['client_secret']}"
				 . " -k MAILJET1={$config ['MAILJET1']}"
				 . " -k MAILJET2={$config ['MAILJET2']}" ;
		} else if ( is_string ($bPassKeys) ) {
			$cmd .=$bPassKeys ;
		}
		utils::log ("Launching command: $cmd") ;
		$result =null ;
		if ( $bWindows ) {
			//$result =pclose (popen ("start /B \"\" $cmd", 'w')) ;
			//$result =exec ("start /B \"\" $cmd") ;
			//$result =shell_exec ("start /B \"\" $cmd") ;
			$com =new \COM ('WScript.shell') ;
			//$cmd .=" > C:/temp/aaa.txt 2>&1" ;
			$com->run ($cmd, 0, false) ; // 0 for prod, 1 for debug
		} else {
			//$result =exec ("$cmd > /dev/null 2>&1 &") ;
			//$result =shell_exec ("$cmd > /dev/null 2>&1 &") ;
			putenv ('SHELL=/bin/bash') ;
			print `echo $cmd | at now 2>&1` ;
		}
		return (true) ;
	}
	
// 	function fork_process ($options) {
// 		$shared_memory_monitor =shmop_open (ftok (__FILE__, chr (0)), 'c', 0644, count ($options ['process'])) ;
// 		$shared_memory_ids =(object)array () ;
// 		for ( $i =1 ; $i <= count ($options ['process']) ; $i++ )
// 			$shared_memory_ids->$i =shmop_open (ftok (__FILE__, chr ($i)), 'c', 0644, $options ['size']) ;
// 		for ( $i =1 ; $i <= count ($options ['process']) ; $i++ ) {
// 			$pid =pcntl_fork () ;
// 			if ( !$pid ) {
// 				if ( $i == 1 )
// 					usleep (100000) ;
// 					$shared_memory_data =$options ['process'] [$i - 1] () ;
// 					shmop_write ($shared_memory_ids->$i, $shared_memory_data, 0) ;
// 					shmop_write ($shared_memory_monitor, '1', $i - 1) ;
// 					exit ($i) ;
// 			}
// 		}
// 	}
	
}
