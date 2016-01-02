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
//var mail_parser = require('./mail-parser');

// Initialization class
class Mailjet {
	private $_apiKey ;
	private $_secretKey ;
	private $_authentificate ;
	
	public function __construct ($apiKey, $secretKey) {
		$this->_apiKey =$apiKey ;
		$this->_secretKey =$secretKey ;
		$this->_authentificate =base64_encode ($apiKey . ':' . $secretKey) ;
	}

	// Email sending code
	public function sendContent ($from, $to, $subject, $type, $content) {
		if ( is_string ($to) )
			$to =[ $to ] ;
		$recipients =Mailjet::parse_recipient_type ($to) ;
		
		// Build the HTTP POST body text
		if ( $type == 'html' ) {
			$body =http_build_query (array (
				'from' => $from,
				// Handle many destinations
				'to' => implode (', ', $recipients ['to']),
				'cc' => implode (', ', $recipients ['cc']),
				'bcc' => implode (', ', $recipients ['bcc']),
				'subject' => $subject,
				'html' => $content
			)) ;
		} else if ( $type == 'text' ) {
			$body =http_build_query (array (
				'from' => $from,
				// Handle many destinations
				'to' => implode (', ', $recipients ['to']),
				'cc' => implode (', ', $recipients ['cc']),
				'bcc' => implode (', ', $recipients ['bcc']),
				'subject' => $subject,
				'text' => $content
			)) ;
		} else {
			throw new Exception ('Wrong email type') ;
		}
		utils::log ($body) ;

		$options =array (
			'scheme' => 'http',
			'host' => 'api.mailjet.com',
			//'port' => 80,
			'path' => '/v3/send/',
		) ;
		$endpoint =Mailjet::unparse_url ($options) ;
		$headers =array (
			'Authorization' => ('Basic ' . $this->_authentificate),
			'Content-Type' => 'application/x-www-form-urlencoded',
			'Content-Length' => strlen ($body)
		) ;

		// API request
		Unirest\Request::verifyPeer (false) ;
		$response =Unirest\Request::post ($endpoint, $headers, $body) ;
				
		utils::log ('STATUS: ' . $response->code) ;
		utils::log ('HEADERS: ' . json_encode ($response->headers)) ;
		utils::log ('BODY: ' . $response->raw_body) ;
		
		return ($response->code == 200) ;
	}
	
	protected static function parse_recipient_type ($recipient_list) {
		$return_vals =array (
			'to' => [],
			'cc' => [],
			'bcc' => []
		) ;
		for ( $i =0 ; $i < count ($recipient_list) ; $i++) {
			$parsed =explode (':', $recipient_list [$i]) ;
			if (count ($parsed) > 1 )
				$return_vals [$parsed [0]] [] =$parsed [1] ;
			else
				$return_vals ['to'] [] =$parsed [0] ;
		}
		return ($return_vals) ;
	}
	
	protected static function unparse_url ($parsed_url) {
		$scheme =isset ($parsed_url ['scheme']) ? $parsed_url ['scheme'] . '://' : '' ;
		$host =isset ($parsed_url ['host']) ? $parsed_url ['host'] : '' ;
		$port =isset ($parsed_url ['port']) ? ':' . $parsed_url ['port'] : '' ;
		$user =isset ($parsed_url ['user']) ? $parsed_url ['user'] : '' ;
		$pass =isset ($parsed_url ['pass']) ? ':' . $parsed_url ['pass'] : '' ;
		$pass =($user || $pass) ? "$pass@" : '' ;
		$path =isset ($parsed_url ['path']) ? $parsed_url ['path'] : '' ;
		$query =isset ($parsed_url ['query']) ? '?' . $parsed_url ['query'] : '' ;
		$fragment =isset ($parsed_url ['fragment']) ? '#' . $parsed_url ['fragment'] : '' ;
		return ("$scheme$user$pass$host$port$path$query$fragment") ;
	}
	
}