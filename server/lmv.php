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

use Unirest ;
//use Symfony\Component\HttpFoundation\Response ;

class lmv {
	private $rootDir ;
	private $bucket ;
	private static $config =null ;
	private static $token =null ;
	
	const HTTP_OK =200 ;
	const HTTP_CREATED =201 ;
	const HTTP_ACCEPTED =202 ;
	const HTTP_NOT_FOUND =404 ;
	
	public function __construct ($bucketName =null) {
		$this->bucket =$bucketName ? : lmv::getDefaultBucket (null, true) ;
		$this->rootDir =utils::realpath (__DIR__ . '/../') ;
	}
	
	public function rootDir ($plus ='', $real =true) {
		if ( $real )
			return (utils::realpath ("{$this->rootDir}{$plus}")) ;
		else
			return (utils::normalize ("{$this->rootDir}{$plus}")) ;
	}
	
	public function dataDir ($plus ='', $real =false) {
		if ( $real )
			return (utils::realpath ("{$this->rootDir}/data{$plus}")) ;
		else
			return (utils::normalize ("{$this->rootDir}/data{$plus}")) ;
	}
	
	public function extractDir ($plus ='', $real =false) {
		if ( $real )
			return (utils::realpath ("{$this->rootDir}/www/extracted{$plus}")) ;
		else
			return (utils::normalize ("{$this->rootDir}/www/extracted{$plus}")) ;
	}
	
	public function tmpDir ($plus ='', $real =false) {
		if ( $real )
			return (utils::realpath ("{$this->rootDir}/tmp{$plus}")) ;
		else
			return (utils::normalize ("{$this->rootDir}/tmp{$plus}")) ;
	}
	
	public function viewsDir ($plus ='', $real =false) {
		if ( $real )
			return (utils::realpath ("{$this->rootDir}/views{$plus}")) ;
		else
			return (utils::normalize ("{$this->rootDir}/views{$plus}")) ;
	}
	
	public static function config () {
		if ( static::$config !== null )
			return (static::$config) ;
		
		$configFile =__DIR__ . '/credentials.php' ;
		if ( !realpath ($configFile) )
			//throw new \RuntimeException (sprintf ('The file "%s" does not exist.', $configFile)) ;
			$configFile =__DIR__ . '/credentials_.php' ;
		static::$config =require realpath ($configFile) ;
		return (static::$config) ;		
	}
	
	protected static function tokenPath ($raw =false) {
		$lmv =new lmv ('test') ;
		return ($lmv->dataDir ('/token.json', !$raw)) ;
	}
	
	public static function tokenObject () {
		$tokenPath =lmv::tokenPath () ;
		$seconds =1000 ; // Service returns 1799 seconds bearer token
		if (   !$tokenPath
        	|| filemtime ($tokenPath) + $seconds < time ()
        )
        	return (static::$token =lmv::refreshToken ()) ;
        if ( static::$token !== null )
        	return (static::$token) ;
        $content =file_get_contents (lmv::tokenPath ()) ;
        return (static::$token =(object)json_decode ($content, true)) ;
	}
	
	// POST /authentication/v1/authenticate
	private static function refreshToken () {
		$config =lmv::config () ;
		
		utils::log ('Refreshing Autodesk Service token') ;
		Unirest\Request::verifyPeer (false) ;
		//Unirest\Request::jsonOpts (true, 512, JSON_NUMERIC_CHECK & JSON_FORCE_OBJECT & JSON_UNESCAPED_SLASHES) ;
		//Unirest\Request::curlOpts( array ( CURLOPT_PROXY => '127.0.0.1:8888' )) ;
		//Unirest\Request::clearCurlOpts () ;
		$response =Unirest\Request::post (
			$config ['AuthenticateEndPoint'],
			array ( 'Accept' => 'application/json',
            		'Content-Type' => 'application/x-www-form-urlencoded'
			),
			//$config ['credentials']
			join ('&',
				array_map (
					function ($v, $k) { return ($k . '=' . urlencode ($v)) ; },
					$config ['credentials'],
					array_keys ($config ['credentials'])
				)
			)
		) ;
		// $response->code;        // HTTP Status code
		// $response->headers;     // Headers
		// $response->body;        // Parsed body
		// $response->raw_body;    // Unparsed body
		if ( $response->code != lmv::HTTP_OK ) {
			utils::log ('Token: ERROR! (' . $response->code . ')') ;
			unlink ($tokenPath) ;
			return (null) ;
		}
		utils::log ('Token: ' . $response->raw_body) ;
		$tokenPath =lmv::tokenPath (true) ;
		file_put_contents ($tokenPath, $response->raw_body) ;
		return ($response->body) ;
	}
	
	public static function bearer () {
		return (lmv::tokenObject ()->access_token) ;
	}
	
	public static function getDefaultBucket ($name =null, $addKey =false) {
		$possible ='abcdefghijklmnopqrstuvwxyz0123456789' ;
		$text ='z' ;
		for ( $i =0 ; $i < 32 ; $i++ ) {
			//try {
				$index =intval (floor (rand (0, 100) * strlen ($possible) / 100)) ;
				$text .="$possible{$index}" ;
			//} catch ( Exception $ex ) {
			//}
		}
		$config =lmv::config () ;
		$name =$name ? : (!empty ($config ['bucket']) ? $config ['bucket'] : $text) ;
		if ( $addKey === true )
			$name .=strtolower ($config ['credentials'] ['client_id']) ;
		return ($name) ;
	}

	// GET /oss/v1/buckets/:bucket/details
	public function checkBucket () {
		$config =lmv::config () ;
		
		Unirest\Request::verifyPeer (false) ;
		$response =Unirest\Request::get (
			sprintf ($config ['getBucketsDetailsEndPoint'], $this->bucket),
			array ( 'Accept' => 'application/json',
					'Content-Type' => 'application/json',
					'Authorization' => ('Bearer ' . lmv::bearer ())
			),
			null
		) ;
		
		if ( $response->code != lmv::HTTP_OK || !isset ($response->body->key) ) {
			utils::log ('checkBucket fail ' . $response->code) ;
			return (null) ;
		}
		
		$path =$this->dataDir ("/{$response->body->key}.bucket.json") ;
		if ( file_put_contents ($path, $response->raw_body) === false )
			utils::log ("Could not save bucket to disk! - $path") ;
		return ($response->body) ;
	}
	
	// POST /oss/v1/buckets
	public function createBucket ($policy ='transient') {
		$config =lmv::config () ;
		
		Unirest\Request::verifyPeer (false) ;
		$response =Unirest\Request::post (
			$config ['postBucketsEndPoint'],
			array ( 'Accept' => 'application/json',
					'Content-Type' => 'application/json',
					'Authorization' => ('Bearer ' . lmv::bearer ())
			),
			json_encode((object)array ( 'bucketKey' => $this->bucket, 'policy' => $policy ))
		) ;

		if ( $response->code != lmv::HTTP_OK || !isset ($response->body->key) ) {
			utils::log ('createBucket fail ' . $response->code) ;
			return (null) ;
		}
		
		$path =$app->dataDir ("/data/{$response->body->key}.bucket.json") ;
		if ( file_put_contents ($path, $response->raw_body) === false )
			utils::log ("Could not save bucket to disk! - $path") ;
		return ($response->body) ;
	}
	
	public function createBucketIfNotExist ($policy ='transient') {
		$response =$this->checkBucket ($policy) ;
		return ($response ? : $this->createBucket ($policy)) ;
	}
	
	// PUT /oss/v1/buckets/:bucket/objects/:filename
	public function uploadFile ($identifier) {
		$config =lmv::config () ;
		
		$path =$this->dataDir ("/$identifier.json", true) ;
		if ( !$path )
			return (null) ;
		$content =file_get_contents ($path) ;
		$data =json_decode ($content, true) ;
		$serverFile =$this->tmpDir ("/{$data->name}", true) ;
		if ( !$serverFile )
			return (null) ;
		
		$total =filesize ($serverFile) ;
		$chunkSize =$config ['fileResumableChunk'] * 1024 * 1024 ;
		if ( $total <= $chunkSize )
			$response =$this->singleUpload ($identifier) ;
		else
			$response =$this->resumableUpload ($identifier) ;
		return ($response) ;
	}
	
	// PUT /oss/v1/buckets/:bucket/objects/:filename
	protected function singleUpload ($identifier) {
		$path =$this->dataDir ("/$identifier.json", true) ;
		if ( !$path )
			return (null) ;
		$content =file_get_contents ($path) ;
		$data =(object)json_decode ($content, true) ;
		$serverFile =$this->tmpDir ("/{$data->name}", true) ;
		if ( !$serverFile )
			return (null) ;
		$localFile =basename ($serverFile) ;
	
		$config =lmv::config () ;
		$endpoint =sprintf ($config ['putFileUploadEndPoint'], $this->bucket, urlencode ($localFile)) ;
		$total =$data->size ;
		$data->bytesPosted =0 ;
		
		$sock =fsockopen (str_replace ('https', 'ssl', $config ['BaseEndPoint']), 443, $errno, $errstr, 30) ;
		if ( !$sock )
			return (null) ;
		fwrite ($sock, 'PUT ' . str_replace ($config ['BaseEndPoint'], '', $endpoint) . " HTTP/1.1\r\n") ;
		fwrite ($sock, 'Host: ' . str_replace ('https://', '', $config ['BaseEndPoint']) . "\r\n") ;
		fwrite ($sock, 'Authorization: Bearer ' . lmv::bearer () . "\r\n") ;
		fwrite ($sock, "Content-Type: application/octet-stream\r\n") ;
		fwrite ($sock, "Accept: application/json\r\n") ;
		fwrite ($sock, 'Content-Length: ' . filesize ($serverFile) . "\r\n") ;
		fwrite ($sock, "Connection: close\r\n") ;
		fwrite ($sock, "\r\n") ;
		
		$file =fopen ($serverFile, 'rb') ;
		while ( !feof ($file) ) {
			$str =fread ($file, 4096) ;
			fwrite ($sock, $str) ;
			$data->bytesPosted =ftell ($file) ;
			file_put_contents ($path, json_encode ($data)) ;
		}
		fclose ($file) ;
		
		$headers ='' ;
		while ( $str =trim (fgets ($sock, 4096)) )
			$headers .="$str\n" ;
		$body ='' ;
		while ( !feof ($sock) )
			$body .=fgets ($sock, 4096) ;
		fclose ($sock) ;
		
		$response =new Unirest\Response (lmv::HTTP_OK, $body, $headers) ;
		preg_match ('/^(HTTP|http)\/([0-9\.]*)\s*([0-9]*)\s*(.*)$/', $response->headers [0], $matches) ;
		$response->code =$matches [3] ;
		
		$path =$this->dataDir ("/$identifier.json") ;
		if ( $response->code != lmv::HTTP_OK || !isset ($response->body->objects [0] ['key']) ) {
			utils::log ('singleUpload fail ' . $response->code) ;
			unlink ($path) ;
			return (null) ;
		}
		
		if ( file_put_contents ($path, $response->raw_body) === false )
			utils::log ("Could not save response to disk! - $path") ;
		return ($response->body) ;
	}
	
	// PUT /oss/v1/buckets/:bucket/objects/:filename/resumable
	protected function resumablepload ($identifier) {
		$path =$this->dataDir ("/$identifier.json", true) ;
		if ( !$path )
			return (null) ;
		$content =file_get_contents ($path) ;
		$data =(object)json_decode ($content, true) ;
		$serverFile =$this->tmpDir ("/{$data->name}", true) ;
		if ( !$serverFile )
			return (null) ;
		$localFile =basename ($serverFile) ;

		$config =lmv::config () ;
		$endpoint =sprintf ($config ['putFileUploadResumableEndPoint'], $this->bucket, urlencode ($localFile)) ;
		$total =$data->size ;
		$data->bytesPosted =0 ;
		$chunkSize =$config ['fileResumableChunk'] * 1024 * 1024 ;
		$nbChunks =intval (floor (0.5 + $total / $chunkSize)) ;
		$sessionId =uniqid ('extract-autodesk-io-', true) ;
		
		$file =fopen ($serverFile, 'rb') ;
		$index =0 ;
		for ( $n =0 ; $n < $chunkSize ; $n++ ) {
			$start =$n * $chunkSize ;
			$end =min ($total, ($n + 1) * $chunkSize) - 1 ;
			$contentRange ='bytes '
				. $start + '-'
				. $end + '/'
				. $total ;
			$sock =fsockopen (str_replace ('https', 'ssl', $config ['BaseEndPoint']), 443, $errno, $errstr, 30) ;
			if ( !$sock ) {
				fclose ($file) ;
				return (null) ;
			}
			fwrite ($sock, 'PUT ' . str_replace ($config ['BaseEndPoint'], '', $endpoint) . " HTTP/1.1\r\n") ;
			fwrite ($sock, 'Host: ' . str_replace ('https://', '', $config ['BaseEndPoint']) . "\r\n") ;
			fwrite ($sock, 'Authorization: Bearer ' . lmv::bearer () . "\r\n") ;
			fwrite ($sock, "Content-Type: application/octet-stream\r\n") ;
			fwrite ($sock, "Accept: application/json\r\n") ;
			fwrite ($sock, 'Content-Range: ' . $contentRange) ;
			fwrite ($sock, 'Session-Id: ' . $sessionId) ;
			fwrite ($sock, "Connection: close\r\n") ;
			fwrite ($sock, "\r\n") ;
			
			$read =0 ;
			while ( $read < $chunkSize ) {
				$toRead =min (4096, $chunkSize - $read) ;
				$str =fread ($file, $toRead) ;
				fwrite ($sock, $str) ;
				$data->bytesPosted =ftell ($file) ;
				file_put_contents ($path, json_encode ($data)) ;
				$read +=$toRead ;
			}
			
			$headers ='' ;
			while ( $str =trim (fgets ($sock, 4096)) )
				$headers .="$str\n" ;
			$body ='' ;
			while ( !feof ($sock) )
				$body .=fgets ($sock, 4096) ;
			fclose ($sock) ;
			
			$response =new Unirest\Response (lmv::HTTP_OK, $body, $headers) ;
			preg_match ('/^(HTTP|http)\/([0-9\.]*)\s*([0-9]*)\s*(.*)$/', $response->headers [0], $matches) ;
			$response->code =$matches [3] ;
			
			$path =$this->dataDir ("/$identifier.json") ;
			if ( $response->code != lmv::HTTP_OK && $response->code != lmv::HTTP_ACCEPTED ) {
				fclose ($file) ;
				utils::log ('resumablepload fail ' . $response->code) ;
				unlink ($path) ;
				return (null) ;
			}
			
			if ( file_put_contents ($path, $response->raw_body) === false )
				utils::log ("Could not save response to disk! - $path") ;
			//return ($response->body) ;
		}
		fclose ($file) ;
		return (lmv::HTTP_OK) ;
	}
	
	public function getURN ($identifier) {
		$path =$this->dataDir ("/$identifier.json", true) ;
		if ( $path ) {
			$content =file_get_contents ($path) ;
			$data =(object)json_decode ($content, true) ;
			return ($data->objects [0] ['id']) ;
		} else {
			$path =$this->dataDir ("/$identifier.resultdb.json", true) ;
			if ( !$path )
				return ('') ;
			$content =file_get_contents ($path) ;
			$data =(object)json_decode ($content, true) ;
			return (base64_decode ($data->urn)) ;
		}
		return ('') ;
	}
	
	static public function getFilename ($identifier) {
		$path =$this->dataDir ("/$identifier.json", true) ;
		if ( !$path )
			return ('') ;
		$content =file_get_contents ($path) ;
		$data =(object)json_decode ($content, true) ;
		return (isset ($data->name) ? $data->name : $data->objects [0] ['key']) ;
	}
	
	// GET /oss/v1/buckets/:bucketkey/objects/:objectKey/details
	public function checkObjectDetails ($filename) {
		$config =lmv::config () ;
		$endpoint =sprintf ($config ['getFileDetailsEndPoint'], $this->bucket, urlencode ($filename)) ;
		
		Unirest\Request::verifyPeer (false) ;
		$response =Unirest\Request::get (
			$endpoint,
			array ( 'Accept' => 'application/json',
					'Content-Type' => 'application/json',
					'Authorization' => ('Bearer ' . lmv::bearer ()) ),
			null
		) ;
		
		if ( $response->code != lmv::HTTP_OK || !isset ($response->body->{'bucket-key'}) ) {
			utils::log ('checkObjectDetails fail ' . $response->code) ;
			return (null) ;
		}
		
		$identifier =$response->body->objects [0] ['size'] . '-' . str_replace ('/[^0-9A-Za-z_-]/g', '', $filename) ;
		$path =$this->dataDir ('/' . $response->body->{'bucket-key'} . $identifier . '.json') ;
		if ( file_put_contents ($path, json_encode ($response->body)) === false )
			utils::log ("Could not save objectDetails response to disk! - $path") ;
		return ($response->body) ;
	}
	
	// POST /references/v1/setreference
	private function traverseConnections ($master, $conn) {
		$items =[] ;
		for ( $i =0 ; $i < count ($conn) ; $i++ ) {
			$items [] =(object)array (
				'file' => $this->getURN ($conn [$i]->uniqueIdentifier),
				'metadata' => (object)array (
					'childPath' => static::getFilename ($conn [$i]->uniqueIdentifier),
					'parentPath' => static::getFilename ($master)
				)
			) ;
			$items =array_merge ($items, traverseConnections ($conn [$i]->uniqueIdentifier, $conn [$i]->children)) ;
		}
		return ($items) ;
	}
	
	public function createDependenciesJson ($connections) {
		if ( $connections == null )
			return (null) ;
		$master =$connections->uniqueIdentifier ;
		$desc =(object)array (
			'master' => $this->getURN ($master),
			'dependencies' => array ()
		) ;
		$desc->dependencies =traverseConnections ($master, $connections->children) ;
		$path =$this->dataDir ("/$master.connections.json") ;
		if ( file_put_contents ($path, json_encode ($desc)) === false )
			utils::log ("Could not save connections file to disk! - $path") ;
		return ($desc) ;
	}
	
	public function setDependencies ($connections) {
		if ( $connections == null )
			return ((object)array (
				'status' => 'ok',
				'statusCode' => lmv::HTTP_OK
			)) ;
		
		$desc =$this->createDependenciesJson ($connections) ;
		
		$config =lmv::config () ;
		Unirest\Request::verifyPeer (false) ;
		$response =Unirest\Request::post (
				$config ['postSetReferencesEndPoint'],
				array ( 'Accept' => 'application/json',
						'Content-Type' => 'application/json',
						'Authorization' => ('Bearer ' . lmv::bearer ())
				),
				json_encode ($desc)
		) ;
		
		if ( $response->code != lmv::HTTP_OK ) {
			utils::log ('setDependencies fail ' . $response->code) ;
			return (null) ;
		}
		return ((object)array (
			'status' => 'ok',
			'statusCode' => lmv::HTTP_OK
		)) ;
	}
	
	// POST /viewingservice/v1/register
	public function register ($connections) {
		$urn =$this->getURN ($connections->uniqueIdentifier) ;
		$desc =(object)array ( 'urn' => base64_encode ($urn) ) ;
	
		$config =lmv::config () ;
		Unirest\Request::verifyPeer (false) ;
		$response =Unirest\Request::post (
			$config ['postRegisterEndPoint'],
			array ( 'Accept' => 'application/json',
					'Content-Type' => 'application/json',
					'Authorization' => ('Bearer ' . lmv::bearer ())
			),
			json_encode ($desc)
		) ;
		
		if ( $response->code != lmv::HTTP_OK && $response->code != lmv::HTTP_CREATED ) {
			utils::log ('register fail ' . $response->code) ;
			return (null) ;
		}
		return ((object)array (
				'status' => 'ok',
				'statusCode' => $response->code
		)) ;
	}
	
	// GET /viewingservice/v1/:encodedURN/status
	// status/all/bubbles params { guid : '067e6162-3b6f-4ae2-a171-2470b63dff12' }
	public function status ($urn, $params =[]) {
		$encodedURN =base64_encode ($urn) ;
	
		$config =lmv::config () ;
		$endpoint =sprintf ($config ['getStatusEndPoint'], $encodedURN) ;
		Unirest\Request::verifyPeer (false) ;
		$response =Unirest\Request::get (
			$endpoint,
			array ( 'Accept' => 'application/json',
					'Content-Type' => 'application/json',
					'Authorization' => ('Bearer ' . lmv::bearer ())
			),
			$params
		) ;
		
		if ( $response->code != lmv::HTTP_OK ) {
			utils::log ('status fail ' . $response->code) ;
			return (null) ;
		}
		
		return ($response->body) ;
	}
	
	// GET /viewingservice/v1/:encodedURN/all
	public function all ($urn, $params =[]) {
		$encodedURN =base64_encode ($urn) ;
		
		$config =lmv::config () ;
		$endpoint =sprintf ($config ['getAllEndPoint'], $encodedURN) ;
		Unirest\Request::verifyPeer (false) ;
		$response =Unirest\Request::get (
			$endpoint,
			array ( 'Accept' => 'application/json',
					'Content-Type' => 'application/json',
					'Authorization' => ('Bearer ' . lmv::bearer ())
			),
			$params
		) ;
		
		if ( $response->code != lmv::HTTP_OK ) {
			utils::log ('all fail ' . $response->code) ;
			return (null) ;
		}
		
		return ($response->body) ;
	}
	
	// GET /viewingservice/v1/:encodedURN
	public function bubbles ($urn, $params =[]) {
		$encodedURN =base64_encode ($urn) ;
		
		$config =lmv::config () ;
		$endpoint =sprintf ($config ['getBubblesEndPoint'], $encodedURN) ;
		Unirest\Request::verifyPeer (false) ;
		$response =Unirest\Request::get (
			$endpoint,
			array ( 'Accept' => 'application/json',
					'Content-Type' => 'application/json',
					'Authorization' => ('Bearer ' . lmv::bearer ())
			),
			$params
		) ;
		
		if ( $response->code != lmv::HTTP_OK ) {
			utils::log ('all fail ' . $response->code) ;
			return (null) ;
		}
		
		return ($response->body) ;
	}
	
	// GET /oss/v1/buckets/:bucket/objects/:filename
	public function download ($identifier) {
		$config =lmv::config () ;
		
		$endpoint ='' ;
		$filename ='default.bin' ;
		$accept ='application/octet-stream' ;
		
		$path =$this->dataDir ("/$identifier.json", true) ;
		if ( $path ) {
			$content =file_get_contents ($path) ;
			$data =(object)json_decode ($content, true) ;

			$endpoint =$data->objects [0] ['location'] ;
			$filename =$data->objects [0] ['key'] ;
			$accept =$data->objects [0] ['content-type'] ;
		} else {
			// Try to rebuild it ourself
			$filename =static::getFilename ($identifier) ;
			if ( $filename == '' )
				return (null) ;
			//endpoint =util.format (config.getputFileUploadEndPoint, self.bucket, filename.replace (/ /g, '+')) ;
			$endpoint =sprintf ($config ['getputFileUploadEndPoint'], $this->bucket, urlencode ($filename)) ;
		}
	
		Unirest\Request::verifyPeer (false) ;
		$response =Unirest\Request::get (
			$endpoint,
			array ( 'Accept' => $accept,
					'Authorization' => ('Bearer ' . lmv::bearer ())
			),
			null
		) ;

		if ( $response->code != lmv::HTTP_OK ) {
			utils::log ('download fail ' . $response->code) ;
			return (null) ;
		}
		
		return ((object)array (
			'body' => $response->body,
			'content-type' => $accept,
			'filename' => $filename
		)) ;
	}
	
	// GET /viewingservice/v1/items/:encodedURN
	public function downloadItem ($urn) { // TODO: range header?
		$encodedURN =urlencode ($urn) ;
		//utils::log ('Downloading: ' . $urn) ;
	
		$config =lmv::config () ;
		$endpoint =sprintf ($config ['getItemsEndPoint'], $encodedURN) ;
		Unirest\Request::verifyPeer (false) ;
		$response =Unirest\Request::get (
			$endpoint,
			array ( 'Authorization' => ('Bearer ' . lmv::bearer ()) ),
			null
		) ;
		
		if ( $response->code != lmv::HTTP_OK ) {
			utils::log ('downloadItem fail ' . $response->code) ;
			return ($response->code) ;
		}
		
		return ($response->raw_body) ;
	}
	
	// GET /viewingservice/v1/thumbnails/:encodedURN
	public function thumbnail ($urn, $width =null, $height =null) {
		$encodedURN =base64_encode ($urn) ;
	
		$config =lmv::config () ;
		$endpoint =sprintf ($config ['getThumbnailsEndPoint'], $encodedURN) ;
		$query =[] ;
		if ( $width !== null )
			$query ['width'] =$width ;
		if ( $height !== null )
			$query ['height'] =$height ;
	
		Unirest\Request::verifyPeer (false) ;
		$response =Unirest\Request::get (
			$endpoint,
			array ( 'Authorization' => ('Bearer ' . lmv::bearer ()) ),
			$query
		) ;
				
		if ( $response->code != lmv::HTTP_OK ) {
			utils::log ('thumbnail fail ' . $response->code) ;
			return (null) ;
		}
		
		return ($response->raw_body) ;
	}
	
}