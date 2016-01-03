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

use Silex ;
use Silex\ControllerProviderInterface ;

use Symfony\Component\HttpFoundation\Request ;
use Symfony\Component\HttpFoundation\Response ;
use Symfony\Component\HttpFoundation\JsonResponse ;
use Symfony\Component\Finder\Finder ;

class LmvProjects implements ControllerProviderInterface {

	public function connect (Silex\Application $app) {
		$results =$app ['controllers_factory'] ;
		
		// List local buckets since we cannot list server buckets
		$results->get ('/buckets', 'ADN\Extract\LmvProjects::buckets') ;
		// Get the progress on translating the bucket/identifier
		$results->get ('/{identifier}/progress', 'ADN\Extract\LmvProjects::progress') ;
		// Get details on the bucket/identifier item
		// identifier can be the filename
		$results->get ('/{identifier}', 'ADN\Extract\LmvProjects::details') ;
		// Get details on the bucket
		$results->get ('/', 'ADN\Extract\LmvProjects::bucketDetails') ;
		// Submit a new bucket/identifier for translation
		$results->post ('/', 'ADN\Extract\LmvProjects::submitProject') ;
		
		return ($results) ;
	}
	
	// List local buckets since we cannot list server buckets
	public function buckets (Silex\Application $app, Request $request) {
		$finder =new Finder () ;
		$finder->name ('/(.*)\\.bucket\\.json/')->in ($app->dataDir ()) ;
		$results =array () ;
		foreach ( $finder as $file ) {
			$path =$file->getRealpath () ;
			// $content =$file->getContents () ;
			// $data =(object)json_decode ($content, true) ;
			preg_match ('/^.*[\\/\\\\]{1}(.*)\\.bucket\\.json$/', $path, $regex) ;
			array_push ($results, $regex [1]) ;
		}
		
		// $response =new Response () ;
		// $response->setStatusCode (Response::HTTP_OK) ;
		// $response->headers->set ('Content-Type', 'application/json') ;
		// $response->setContent (json_encode (array ( 'name' => 'cyrille' ))) ;
		/*return (new Response (
		 json_encode ($results),
		 Response::HTTP_OK,
		 [ 'Content-Type' => 'application/json' ]
		 )) ;*/
		return (new JsonResponse ($results, Response::HTTP_OK)) ;
	}
	
	// Get the progress on translating the bucket/identifier
	public function progress (Silex\Application $app, $identifier) {
		$bucket =lmv::getDefaultBucket () ;
		$lmv =new lmv ($bucket) ;
		$urn =$lmv->getURN ($identifier) ;
		if ( $urn == '' ) {
			// Ok, we might be uploading to oss - we will try to return a file upload progress
			$path =$app->dataDir ("/$identifier.json", true) ;
			if ( $path === false ) {
				// No luck, let's return a default answer
				return (new JsonResponse ((object)array (
					'guid' => '',
					'progress' => 'uploading to oss',
					'startedAt' => gmdate (DATE_RFC2822),
					'status' => 'requested',
					'success' => '0%',
					'urn' => ''
				), Response::HTTP_OK)) ;
			}
			$content =file_get_contents ($path) ;
			$data =json_decode ($content) ;
			$connections =null ;
			
			try {
				$path =$app->dataDir ("/$identifier.dependencies.json", true) ;
				$content =file_get_contents ($path) ;
				$connections =json_decode ($content) ;
				$size =0 ;
				$uploaded =0 ;
				foreach ( $connections as $item ) {
					$path =$app->dataDir ("/$item.json", true) ;
					if ( $path === false ) {
						utils::log ("Something wrong happened during upload - $item") ;
						continue ;
					}
					$content =file_get_contents ($path) ;
					$data2 =json_decode ($content) ;
					$size +=(data2.hasOwnProperty ('size') ? parseInt (data2.size) : data2.objects [0].size) ;
					$uploaded +=(data2.hasOwnProperty ('bytesPosted') ? parseInt (data2.bytesPosted) : data2.objects [0].size) ;
				}
				$pct =0 ;
				if ( $size != 0 )
					$pct =intval (floor (100 * $uploaded / $size)) ;
				return (new JsonResponse ((object)array (
					'guid' => '',
					'progress' => 'uploading to oss',
					'startedAt' => gmdate (DATE_RFC2822),
					'status' => 'requested',
					'success' => "$pct%",
					'urn' => ''
				), Response::HTTP_OK)) ;
			} catch ( Exception $ex ) {
				$connections =null ;
				$pct =0 ;
				if ( $data->size != 0 )
					$pct =intval (floor (100 * $data->bytesPosted / $data->size)) ;
				return (new JsonResponse ((object)array (
					'guid' => '',
					'progress' => 'uploading to oss',
					'startedAt' => gmdate (DATE_RFC2822),
					'status' => 'requested',
					'success' => "$pct%",
					'urn' => ''
				), Response::HTTP_OK)) ;
			}
			return ;
		}
		
		$response =$lmv->status ($urn) ;
		if ( $response === null )
			return (new Response ('', Response::HTTP_NOT_FOUND, [ 'Content-Type' => 'text/plain' ])) ;
		if ( $response->progress == 'complete' ) {
			$path =$app->dataDir ("/$identifier.resultdb.json") ;
			file_put_contents($path, json_encode ($response)) ;
		}
		return (new JsonResponse ($response, Response::HTTP_OK)) ;
	}
	
	// Get details on the bucket/identifier item
	// identifier can be the filename
	public function details (Silex\Application $app, $identifier) {
		$bucket =lmv::getDefaultBucket () ;
		$filename ='' ;
		$idData =(object)[] ;
		$path =$app->dataDir ("/$identifier.json") ;
		try {
			if ( $path === false )
				throw new Exception () ;
			$idData =file_get_contents ($path) ;
			$idData =json_decode ($idData) ;
			if ( isset ($idData->name) )
				$filename =$idData->name ;
			else if ( isset ($idData->objects) && isset ($idData->objects [0]->key) )
				$filename =$idData->objects [0]->key ;
			else
				throw new Exception () ;
		} catch ( Exception $ex ) {
			$filename =str_replace ('/^[0-9]*\-/', '', $identifier) ;
			$position =strlen ($filename) - 3 ;
			$filename =implode ('', [ substr ($filename, 0, $position), '.', substr ($filename, $position) ]) ;
		}
	
		// GET /oss/{apiversion}/buckets/{bucketkey}/objects/{objectKey}/details
		// would work as well, but since we saved it locally, use the local version
		if ( $path === false ) {
			$response =$lmv->checkObjectDetails ($filename) ;
			if ( $response == null )
				return (new Response ('', Response::HTTP_NOT_FOUND, [ 'Content-Type' => 'text/plain' ])) ;
			return (new JsonResponse ($response, Response::HTTP_OK)) ;
		} else {
			return (new JsonResponse ($idData, Response::HTTP_OK)) ;
		}
	}
	
	// Get details on the bucket
	public function bucketDetails (Silex\Application $app) {
		$bucket =lmv::getDefaultBucket () ;
		// GET /oss/{api version}/buckets/{bucket key}/details
		// would work as well, but since we saved it locally, use the local version
		$path =$app->dataDir ("/$bucket.bucket.json", true ) ;
		if ( $path ) {
			$data =file_get_contents ($path) ;
			$data =json_decode ($data) ;
			return (new JsonResponse ($data, Response::HTTP_OK)) ;
		}
		
		$lmv =new lmv ($bucket) ;
		$response =$lmv->checkBucket () ;
		if ( $response == false )
			return (new Response ('', Response::HTTP_NOT_FOUND, [ 'Content-Type' => 'text/plain' ])) ;
		
		return (new JsonResponse ($response, Response::HTTP_OK)) ;
	}
	
	// Submit a new bucket/identifier for translation
	public function submitProject (Silex\Application $app, Request $request) {
		$bucket =lmv::getDefaultBucket () ;
		$policy ='transient' ;
		$connections =$request->body ;
	
// 			function traverseConnections (conn) {
// 				var items =[] ;
// 				for ( var i =0 ; i < conn.length ; i++ ) {
// 					items.push (conn [i].uniqueIdentifier) ;
// 					items =items.concat (traverseConnections (conn [i].children)) ;
// 				}
// 				return (items) ;
// 			}
		utils::log ("master: {$connections->uniqueIdentifier}") ;
		$items =[ $connections->uniqueIdentifier ] ;
		$items =array_merge ($items, traverseConnections ($connections->children)) ;
		
		// This is to help the upload progress bar to be more precise
		$path =$app->dataDir ("/{$connections->uniqueIdentifier}.dependencies.json") ;
		if ( file_put_contents ($path, json_encode ($items)) === false ) {
			utils::log ('ERROR: project dependencies not saved :(') ;
			return (new Response ('', Response::HTTP_NOT_FOUND, [ 'Content-Type' => 'text/plain' ])) ;
		}
		
		$bWindows =substr (php_uname (), 0, 7) == 'Windows' ;
		$cmd =$bWindows ? '"C:/Program Files/PHP.5.6.16/php.exe" ' : 'php ' ;
		$cmd .=__DIR__ . "/translate.php lmv:transltor {$connections->uniqueIdentifier}" ;
		utils::log ("Launching command: $cmd") ;
		$result =null ;
		if ( $bWindows )
			$result =pclose (popen ("start /B \"\" $cmd", 'w')) ;
			//$result =exec ("start /B \"\" $cmd") ;
		else
			$result =exec ("$cmd > /dev/null 2>&1 &") ;
		utils::log ("Command returned: $result") ;
	
		if ( $result == -1 )
			return (new Response ('', Response::HTTP_INTERNAL_SERVER_ERROR, [ 'Content-Type' => 'text/plain' ])) ;
		// We submitted, no clue if it was successful or if it will fail.
		$response =(object)array ( 'status' => 'submitted' ) ;
		return (new JsonResponse ($response, Response::HTTP_OK /*Response::HTTP_ACCEPTED*/)) ; //- 202 Accepted
	}
	
}
