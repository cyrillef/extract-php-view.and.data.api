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

use Symfony\Component\Filesystem\Filesystem ;
use Twig_Loader_Filesystem ;
use Twig_Environment ;

class Translator {
	private $identifier ;
	private $lmv ;
	
	public function __construct ($identifier, $bucketName =null) {
		$this->identifier =$identifier ;
		if ( empty ($bucketName) )
			$bucketName =lmv::getDefaultBucket () ;
		$this->lmv =new lmv ($bucketName) ;
	}
	
	public function translate () {
		//utils::log ('translate launched!') ;
		try {
			$path =$app->dataDir ("/{$this->identifier}.dependencies.json", true) ;
			if ( $path === false )
				throw new Exception ('No dependency file found') ;
			$content =file_get_contents ($path) ;
			$connections =json_decode ($content) ;
			
			$items =[ $connections->uniqueIdentifier ] ;
			$items =array_merge ($items, traverseConnections ($connections->children)) ;
			// This is to help the upload progress bar to be more precise
			if ( file_put_contents ($path, json_encode ($items)) === false ) {
				utils::log ('ERROR: project dependencies not saved :(') ;
				throw new Exception ('ERROR: project dependencies not saved :(') ;
			}
			
			// Bucket
			$policy ='transient' ;
			$r1 =$this->lmv->createBucketIfNotExist ($policy) ;
			if ( $r1 == null ) {
				// No need to continue if the bucket was not created
				utils::log ('Failed to create bucket!') ;
				throw new Exception ('Failed to create bucket!') ;
			}
			utils::log ('Bucket created (or did exist already)!') ;
				
			// Uploading file(s)
			utils::log ('Uploading(s)') ;
			foreach ( $items as $item ) {
				utils::log (" uploading $item") ;
				$r2 =$this->lmv->uploadFile ($item) ;
				if ( $r2 == null ) {
					utils::log (" Failed to upload $item") ;
					throw new Exception ("Failed to upload $item") ;
				}
				utils::log ("$item upload completed!") ;
			}
			utils::log ('All files uploaded') ;
	
			// Dependencies
			$r3 =$this->lmv->setDependencies (count ($items) == 1 ? null : $connections) ;
			if ( $r3 == null ) {
				utils::log ('Failed to set connections') ;
				throw new Exception ('Failed to set connections') ;
			}
			utils::log ('References set, launching translation') ;
	
			// Register
			$r4 =$this->lmv->register ($connections) ;
			if ( $r3 == null ) {
				utils::log ('URN registration for translation failed') ;
				throw new Exception ('URN registration for translation failed') ;
			}
			utils::log ('URN registered for translation') ;
	
			// We are done for now!
	
			// Just remember locally we did submit the project for translation
			$urn =$this->lmv->getURN ($this->identifier) ;
			$urn =base64_encode ($urn) ;
			$data =(object)array (
					'guid' => $urn,
					'progress' => '0% complete',
					'startedAt' => gmdate (DATE_RFC2822),
					'status' => 'requested',
					'success' => '0%',
					'urn' => $urn
			) ;
			$path =$app->dataDir ("/{$this->identifier}.resultdb.json") ;
			if ( file_put_contents ($path, json_encode ($data)) === false )
				utils::log ("Could not save file to disk - $path") ;
	
			$filesystem =new Filesystem () ;
			foreach ( $items as $item )
				$filesystem->remove ($this->lmv->dataDir ("/$item.json")) ;
			$filesystem->remove ($this->lmv->dataDir ("/{$this->identifier}.dependencies.json")) ;
			$filesystem->remove ($this->lmv->dataDir ("/{$this->identifier}.connections.json")) ;

			return (true) ;
		} catch ( Exception $ex ) {
			//- We are done! email me if any error
			utils::log ('Something wrong happened during upload') ;
			
			$loader =new Twig_Loader_Filesystem (array ( $this->lmv->viewsDir ('', true) )) ;
			$environment =new Twig_Environment ($loader, array ( 'debug' => false, 'charset' => 'utf-8', 'strict_variables' => true )) ;
			
			$content =$environment->render (
				'email-xlt-failed.twig',
				array ( 'ID' => $this->identifier )
			) ;
			$config =lmv::config () ;
			$mjet =new Mailjet ($config ['MAILJET1'], $config ['MAILJET2']) ;
			$mjet->sendContent (
				'ADN Sparks <adn.sparks@autodesk.com>',
				'cyrille@autodesk.com',
				'Autodesk View & Data API Extractor app failed to translate a project',
				'html',
				$content
			) ;
				
			$filesystem =new Filesystem () ;
			$filesystem->rename (
				$this->lmv->dataDir ("/{$this->identifier}.resultdb.json", true),
				$this->lmv->dataDir ("/{$this->identifier}.resultdb.failed"),
				true
			) ;
			
			return (false) ;
		}
	}
	
	private function traverseConnections ($conn) {
		$items =[] ;
		foreach ( $conn as $item ) {
			$items [] ($item->uniqueIdentifier) ;
			$items =array_merge ($items, traverseConnections ($item->children)) ;
		}
		return (items) ;
	}
}