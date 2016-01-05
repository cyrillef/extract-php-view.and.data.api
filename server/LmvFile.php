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
use Unirest ;

class LmvFile implements ControllerProviderInterface {
	private $config ;

	public function connect (Silex\Application $app) {
		$this->config =new \Flow\Config () ;
		$this->config->setTempDir (utils::realpath (__DIR__ . '/../tmp')) ;
		
		$results =$app ['controllers_factory'] ;

 		$results->post ('/file', 'ADN\Extract\LmvFile::file') ;
 		$results->options ('/file', 'ADN\Extract\LmvFile::fileOptions') ;
		// Handle status checks on chunks through Flow.js
 		$results->get ('/file', 'ADN\Extract\LmvFile::fileGet') ;
 		$results->get ('/file/{identifier}/details', 'ADN\Extract\LmvFile::details') ;
 		$results->get ('/file/{identifier}', 'ADN\Extract\LmvFile::id') ;

 		$results->post ('/uri', 'ADN\Extract\LmvFile::uri') ;
 		$results->options ('/uri', 'ADN\Extract\LmvFile::uriOptions') ;
 		
		return ($results) ;
	}
	
	public function file (Silex\Application $app, Request $request) {
		$i =0 ;
	}
	
	public function fileOptions (Silex\Application $app, Request $request) {
	
	}
	
	public function fileGet (Silex\Application $app/*, Request $request*/) {
		$request =new \Flow\Request () ;
		$chunkNumber =$request->getCurrentChunkNumber () ? : 0 ;
		$chunkSize =$request->getDefaultChunkSize () ? : 0 ;
		$totalSize =$request->getTotalSize () ? : 0 ;
		$identifier =$request->getIdentifier () ? : '' ;
		$filename =$request->getFileName () ? : '' ;
		if ( $this->validateRequest ($chunkNumber, $chunkSize, $totalSize, $identifier, $filename) == 'valid' ) {
			$chunkFilename =$this->getChunkFilename ($chunkNumber, $identifier) ;
			if ( realpath ($chunkFilename) !== false ) {
				utils::log ($request->param ('REQUEST_METHOD') . 'found') ;
				return (new Response ('', Response::HTTP_OK, [ 'Content-Type' => 'text/plain' ])) ;
			}
		}
		return (new Response ('', Response::HTTP_NOT_FOUND, [ 'Content-Type' => 'text/plain' ])) ;
	}
	
	public function details (Silex\Application $app/*, Request $request*/, $identifier) {
		//$request =new \Flow\Request () ;
		$path =utils::realpath (__DIR__ . "/../data/$identifier.json") ;
		if ( $path === false )
			return (new Response ('', Response::HTTP_NOT_FOUND, [ 'Content-Type' => 'text/plain' ])) ;
		$content =file_get_contents ($path) ;
		$data =json_decode ($content) ;
		return (new JsonResponse ($data, Response::HTTP_OK)) ;
	}
	
	public function id (Silex\Application $app/*, Request $request*/, $identifier) {
		$request =new \Flow\Request () ;
		$path =utils::realpath (__DIR__ . "/../data/$identifier.json") ;
		if ( $path === false )
			return (new Response ('', Response::HTTP_NOT_FOUND, [ 'Content-Type' => 'text/plain' ])) ;
		$content =file_get_contents ($path) ;
		$data =json_decode ($content) ;
		$serverFile =utils::realpath ($this->config->getTempDir () . $data->name) ;
		if ( $serverFile === false )
			return (new Response ('', Response::HTTP_NOT_FOUND, [ 'Content-Type' => 'text/plain' ])) ;
		
		$content =file_get_contents ($serverFile) ;
		return (new Response (
			$content,
			Response::HTTP_OK,
			[
				'Cache-Control', 'private',
				'Content-Type' => 'application/octet-stream',
				'Content-Length' => strlen ($content),
				'Content-Disposition', "attachment; filename=\"$data->name\""
			]
		)) ;
		// Send headers before outputting anything?
		//$response->sendHeaders () ;
	}
	
	public function uri (Silex\Application $app, Request $request) {
		$bodyParams =json_decode ($request->getContent ()) ;
		$uri =$bodyParams->uri ;
		$identifier =$bodyParams->identifier ;
		$original_filename =$bodyParams->name ? : str_replace ('/.*\//', '', str_replace ('/[\?#].*$/', '', urldecode ($uri))) ;

		Unirest\Request::verifyPeer (false) ;
		//$response =Unirest\Request::head ($uri, [], null) ;
		
		$http =new \ADN\Extract\HttpRequest ($uri, [], null, null) ;
		$response =$http->head () ;
		
		if ( !$request || $response->code != Response::HTTP_OK )
			return (new Response ('', $response->code, [ 'Content-Type' => 'text/plain' ])) ;
		
		$length =utils::findKey ($response->headers, 'Content-Length') ? : -1 ;
		$data =(object)array (
			'key' => $identifier,
			'name' => $original_filename,
			'uri' => $uri,
			'size' => $length,
			'bytesRead' => 0,
			'bytesPosted' => 0
		) ;
		$path =utils::normalize (__DIR__ . "/../data/$identifier.json") ;
		if ( file_put_contents ($path, json_encode ($data)) === false )
			return (new Response ('', Response::HTTP_INTERNAL_SERVER_ERROR, [ 'Content-Type' => 'text/plain' ])) ;
			
		$result =utils::executeScript ("/dl.php lmv:dl $identifier") ;
		if ( $result === false )
  			return (new Response ('', Response::HTTP_INTERNAL_SERVER_ERROR, [ 'Content-Type' => 'text/plain' ])) ;
		$data =(object)array ( 'status' => $identifier ) ;
		return (new JsonResponse ($data, Response::HTTP_OK)) ;
	}
	
	public function uriOptions (Silex\Application $app, Request $request) {
		$bodyParams =json_decode ($request->getContent ()) ;
		$identifier =$bodyParams->identifier ;
		$path =utils::normalize (__DIR__ . "/../data/$identifier.json") ;
		try {
			$content =file_get_contents ($path) ;
			if ( $content === false ) {
				//return (new Response ('', Response::HTTP_INTERNAL_SERVER_ERROR, [ 'Content-Type' => 'text/plain' ])) ;
				throw new Exception ('Cannot access file') ;
			}
			$data =json_decode ($content) ;
			if ( $data->size == -1 ) {
				throw new Exception ('error') ;
			}
			$progress =intval (floor (100.0 * $data->bytesRead / $data->size)) ;
			return (new JsonResponse (
				(object)array ( 'status' => $identifier, 'progress' => $progress ),
				Response::HTTP_OK
			)) ;
		} catch ( Exception $ex ) {
			return (new JsonResponse (
				(object)array ( 'status' => $identifier, 'progress' => -1 ),
				Response::HTTP_OK
			)) ;
		}
	}
	
	protected function cleanIdentifier ($identifier) {
		return (str_replace ('/[^0-9A-Za-z_-]/g', '', $identifier)) ;
	}
	
	protected function validateRequest ($chunkNumber, $chunkSize, $totalSize, $identifier, $filename, $fileSize =null) {
		$identifier =$this->cleanIdentifier ($identifier) ; // Clean up the identifier
		// Check if the request is sane
		if ( $chunkNumber == 0 || $chunkSize == 0 || $totalSize == 0 || strlen ($identifier) == 0 || strlen ($filename) == 0 )
			return ('non_flow_request') ;
		$numberOfChunks =max (intval (floor ($totalSize / ($chunkSize * 1.0))), 1) ;
		if ( $chunkNumber > $numberOfChunks )
			return ('invalid_flow_request1') ;
		if ( $maxFileSize && $totalSize > $maxFileSize )
			return ('invalid_flow_request2') ; // The file is too big
		if ( $fileSize !== null ) {
			if ( $chunkNumber < $numberOfChunks && $fileSize != $chunkSize )
				return ('invalid_flow_request3') ; // The chunk in the POST request isn't the correct size
			if ( $numberOfChunks > 1 && $chunkNumber == $numberOfChunks && $fileSize != (($totalSize % $chunkSize) + intval ($chunkSize)) )
				return ('invalid_flow_request4') ; // The chunks in the POST is the last one, and the fil is not the correct size
			if ( $numberOfChunks == 1 && $fileSize != $totalSize )
				return ('invalid_flow_request5') ; // The file is only a single chunk, and the data size does not fit
		}
		return ('valid') ;
	}
	
	protected function getChunkFilename ($chunkNumber, $identifier) {
		// Clean up the identifier
		$identifier =$this->cleanIdentifier ($identifier) ;
		// What would the file name be?
		return (utils::normalize ($this->config->getTempDir () . "/flow-$identifier.$chunkNumber")) ;
	}
	
}
