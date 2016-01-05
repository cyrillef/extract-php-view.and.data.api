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

class HttpRequest {
	private $uri ;
	private $headers ;
	private $query ;
	private $body ;
	
	const max_chunk_size =50 * 1024 ;

	public function __construct ($uri, $headers, $query =null, $body =null) {
		$this->uri =$uri ;
		$this->headers =$headers ;
		$this->query =$query ;
		$this->body =$body ;
	}
	
	public function get ($cb =null) {
		return ($this->send ('GET', $cb)) ;
	}

	public function head ($cb =null) {
		return ($this->send ('HEAD', $cb)) ;
	}
	
	public function post ($cb =null) {
		return ($this->send ('POST', $cb)) ;
	}
	
	public function put ($cb =null) {
		return ($this->send ('PUT', $cb)) ;
	}
	
	public function delete ($cb =null) {
		return ($this->send ('DELETE', $cb)) ;
	}
	
	protected function send ($method ='GET', $cb =null) {
		$method =strtoupper ($method) ;
		$headers =$this->headers ;
		$body =$this->body ;
		if (   (!is_string ($this->body) && isset ($headers ['Content-Type']) && $headers ['Content-Type'] == 'application/json' )
			|| ($this->body && !isset ($headers ['Content-Type']))
		) {
			$body =json_encode ($this->body) ;
		 	$headers ['Content-Type'] ='application/json' ;
		}
	
		$urls =parse_url ($this->uri) ;
		
		$bSsl =($urls ['scheme'] == 'https') ;
		$ssl =($bSsl == true ? 'ssl://' : '') ;
		
		$port =($bSsl == true ? '443' : '80') ;
		$port =(isset ($urls ['port']) ? $urls ['port'] : $port) ;
		
		$domain ="{$ssl}{$urls ['host']}" ;
		
		$sock =fsockopen ($domain, $port, $errno, $errstr, 30) ;
		if ( !$sock )
			return (null) ;
		
		fwrite ($sock, "$method {$urls ['path']} HTTP/1.1\r\n") ;
		fwrite ($sock, "Host: {$urls ['host']}\r\n") ;
		foreach ( $headers as $key => $value )
			fwrite ($sock, "$key: $value\r\n") ;
		if ( $body && !isset ($headers ['Content-Length']))
			fwrite ($fp, "Content-Length: " . strlen ($body) . "\r\n") ;
		if ( !isset ($headers ['Connection']) )
			fwrite ($sock, "Connection: close\r\n") ;
		fwrite ($sock, "\r\n") ;

		if ( $body )
			fwrite ($sock, $body) ;

		$headers ='' ;
		while ( $str =trim (fgets ($sock, 4096)) )
			$headers .="$str\n" ;
		$temp =new Unirest\Response (lmv::HTTP_OK, '', $headers) ;
		if ( $cb )
			$cb ('headers', $temp->headers) ;
		
		$body ='' ;
		while ( !feof ($sock) ) {
			$data =fgets ($sock, HttpRequest::max_chunk_size) ;
			if ( !$cb || !$cb ('data', $data) ) // if $cb() returns true, we ignore $data
				$body .=$data ;
		}
		fclose ($sock) ;

		$response =new Unirest\Response (lmv::HTTP_OK, $body, $headers) ;
		preg_match ('/^(HTTP|http)\/([0-9\.]*)\s*([0-9]*)\s*(.*)$/', $response->headers [0], $matches) ;
		$response->code =$matches [3] ;
		return ($response) ;
	}
	
}
