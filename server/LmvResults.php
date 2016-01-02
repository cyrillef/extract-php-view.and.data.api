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

class LmvResults implements ControllerProviderInterface {

	public function connect (Silex\Application $app) {
		$results =$app ['controllers_factory'] ;

		// List translated projects
		$results->get ('/', 'ADN\Extract\LmvResults::index') ;
		// Download thumbnail from a bucket/identifier pair
 		$results->get ('/{identifier}/thumbnail', 'ADN\Extract\LmvResults::thumbnail') ;
 		// Get the bucket/identifier viewable data
 		$results->get ('/{identifier}', 'ADN\\Extract\\LmvResults::viewable') ;
 		// Delete the project from the website
 		$results->delete ('/{identifier}', 'ADN\\Extract\\LmvResults::deleteProject') ;
 		// Get the bucket/identifier viewable data as a zip file containing all resources
 		$results->get ('/{identifier}/project', 'ADN\\Extract\\LmvResults::project') ;
		// Get the bucket/identifier viewable data creation progress
 		$results->get ('/{identifier}/project/progress', 'ADN\\Extract\\LmvResults::projectProgress') ;
 		// Download a single file from its bucket/identifier/fragment pair
 		$results->get ('/file/{identifier}/{fragment}', 'ADN\\Extract\\LmvResults::dl') ;
 		
		return ($results) ;
	}
	
	// List translated projects
	public function index (Silex\Application $app, Request $request) {
		$finder =new Finder () ;
		$finder->name ('/(.*)\\.resultdb\\.json/')->in ($app->dataDir ()) ;
		$results =array () ;
		foreach ( $finder as $file ) {
			$path =$file->getRealpath () ;
			$content =$file->getContents () ;
			$data =(object)json_decode ($content, true) ;
			if ( $data->progress == 'failed' || $data->status == 'failed' )
				continue ;
			preg_match ('/^.*[\\/\\\\]{1}(.*)\\.resultdb\\.json$/', $path, $regex) ;
			$out =(object)array (
				'name' => $regex [1],
				'urn' =>$data->urn,
				'date' => $data->startedAt,
				'hasThumbnail' => isset ($data->hasThumbnail)? $data->hasThumbnail : false,
				'status' => $data->status,
				'success' => $data->success,
				'progress' => $data->progress
			) ;
			array_push ($results, $out) ;
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
	
	// Download thumbnail from a bucket/identifier pair
	public function thumbnail (Silex\Application $app, $identifier) {
		$bucket =lmv::getDefaultBucket () ;
		$lmv =new lmv ($bucket) ;
		$urn =$lmv->getURN ($identifier) ;
		if ( $urn == '' )
			return (new JsonResponse ((object)[ 'progress' => 0 ], Response::HTTP_OK)) ;

		$data =$lmv->thumbnail ($urn, 215, 146) ;
		if ( $data == null )
			return (new Response ('', Response::HTTP_NOT_FOUND, [ 'Content-Type' => 'text/plain' ])) ;
			
		$path =$app->extractDir ("/$identifier.png") ;	
		if ( file_put_contents ($path, $data) === false )
			utils::log ("Could not save image to disk! - $path") ;
		
		//header ('Content-Type: image/png') ;
		//echo $data ;
		return (new Response (
			$data,
			Response::HTTP_OK,
			[ 'Content-Type' => 'image/png' ]
		)) ;
	}

	// Get the bucket/identifier viewable data
	public function viewable (Silex\Application $app, $identifier) {
		$bucket =lmv::getDefaultBucket () ;
		$lmv =new lmv ($bucket) ;
		$urn =$lmv->getURN ($identifier) ;
		if ( $urn == '' )
			return (new Response ('', Response::HTTP_NOT_FOUND, [ 'Content-Type' => 'text/plain' ])) ;

		$data =$lmv->all ($urn) ;
		if ( $data == null )
			return (new Response ('', Response::HTTP_NOT_FOUND, [ 'Content-Type' => 'text/plain' ])) ;
		
		if ( $data->progress == 'complete' ) {
			$path =$app->dataDir ("/$identifier.resultdb.json") ;
			if ( file_put_contents ($path, json_encode ($data)) === false )
				utils::log ("Could not save resultdb to disk! - $path") ;
		}

		return (new JsonResponse ($data, Response::HTTP_OK)) ;
	}
	
	// Delete the project from the website
	public function deleteProject (Silex\Application $app, $identifier) {
		$bucket =lmv::getDefaultBucket () ;
		$lmv =new lmv ($bucket) ;
		$urn =$lmv->getURN ($identifier) ;
		if ( $urn == '' )
			return (new Response ('', Response::HTTP_NOT_FOUND, [ 'Content-Type' => 'text/plain' ])) ;
		
		$path =$app->dataDir ("/$identifier.resultdb.json", true) ;
		if ( $path )
			unlink ($path) ;
		$path =$app->extractDir ("/$identifier.png", true) ;
		if ( $path )
			unlink ($path) ;
		$path =$app->extractDir ("/$identifier.zip", true) ;
		if ( $path )
			unlink ($path) ;
		return (new Response ('', Response::HTTP, [ 'Content-Type' => 'text/plain' ])) ;
	}
	
	// Get the bucket/identifier viewable data as a zip file containing all resources
	public function project (Silex\Application $app, Request $request, $identifier) {
		$bucket =lmv::getDefaultBucket () ;
		$lmv =new lmv ($bucket) ;
		$urn =$lmv->getURN ($identifier) ;
		if ( $urn == '' )
			return (new Response ('', Response::HTTP_NOT_FOUND, [ 'Content-Type' => 'text/plain' ])) ;
		
		$path =$app->extractDir ("/$identifier.zip", true) ;
		if ( $path )
			return (new Response ('', Response::HTTP_OK, [ 'Content-Type' => 'text/plain' ])) ;
		
		$lock =$app->dataDir ("/$identifier.lock") ;
		$email =$request->query->get ('email') ;
		if ( realpath ($lock) ) {
			if ( !empty ($email) ) {
				$data =json_decode (file_get_contents ($lock)) ;
				$data->emails [] =$email ;
				if ( file_put_contents ($lock, json_encode ($data)) === false )
					utils::log ("Could not create lock file - $lock") ;
			}
			return (new Response ('', Response::HTTP_OK, [ 'Content-Type' => 'text/plain' ])) ;
		} else {
			$list =!empty ($email) ? [ $email ] : [] ;
			$data =(object)array ( 'progress' => 0, 'emails' => $list ) ;
			if ( file_put_contents ($lock, json_encode ($data)) === false )
				utils::log ("Could not create lock file - $lock") ;
		}
		
		$bWindows =substr (php_uname (), 0, 7) == 'Windows' ;
		$cmd =$bWindows ? '"C:/Program Files/PHP.5.6.16/php.exe" ' : 'php ' ;
		$cmd .=__DIR__ . "/extract.php lmv:extractor $identifier" ;
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
		//return (new Response ('ok', Response::HTTP_OK, [ 'Content-Type' => 'text/plain' ])) ;
		echo 'ok' ;
		exit ;
	}
	
	public function projectProgress (Silex\Application $app, $identifier) {
		$mgr =new ExtractorProgressMgr ($identifier) ;
		$progress =$mgr->progress () ;
		
		if ( $app->extractDir ("/$identifier.zip", true) )
			$progress =100 ;
		return (new JsonResponse (
			(object)array ( 'progress' => $progress ),
			Response::HTTP_OK
		)) ;
	}
	
	// Download a single file from its bucket/identifier/fragment pair
	public function dl (Silex\Application $app, $identifier, $fragment) {
		$bucket =lmv::getDefaultBucket () ;
		$path =$app->dataDir ("/$identifier.resultdb.json", true) ;
		if ( !$path )
			return (new Response ('', Response::HTTP_NOT_FOUND, [ 'Content-Type' => 'text/plain' ])) ;
		
		$content =file_get_contents ($path) ;
		$data =json_decode ($content) ;
		$guid =$data->urn ;

		$urn ="urn:adsk.viewing:fs.file:$guid/output/$fragment" ;
		$lmv =new lmv ($bucket) ;
		$response =$lmv->downloadItem ($urn) ;
		if ( is_int ($reponse) )
			return (new Response ('', Response::HTTP_NOT_FOUND, [ 'Content-Type' => 'text/plain' ])) ;
		
		$filename =basename ($fragment) ;
		return (new Response (
			$response,
			Response::HTTP_OK,
			[
				'Cache-Control', 'private',
				'Content-Type' => 'application/octet-stream',
				'Content-Length' => strlen ($reponse),
				'Content-Disposition', "attachment; filename=\"$filename\""
			]
		)) ;
		// Send headers before outputting anything?
		//$response->sendHeaders () ;
	}
	
}
