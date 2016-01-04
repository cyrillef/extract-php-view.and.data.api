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
use Symfony\Component\Finder\Finder ;
use Twig_Loader_Filesystem ;
use Twig_Environment ;
use Unirest ;

class Extractor {
	private $identifier ;
	private $urn ;
	private $lmv ;
	private $mgr ;
	const _default_size_ =400000 ;
	
	public function __construct ($identifier, $bucketName =null) {
		$this->identifier =$identifier ;
		if ( empty ($bucketName) )
			$bucketName =lmv::getDefaultBucket () ;
		$this->lmv =new lmv ($bucketName) ;
		$this->urn =$this->lmv->getURN ($this->identifier) ;
		$this->mgr =new ExtractorProgressMgr ($this->identifier) ;
	}
	
	public function extract () {
		//utils::log ('extract launched!') ;
		try {
			$path =$this->lmv->dataDir ("/{$this->identifier}") ;
			$filesystem =new Filesystem () ;
			$filesystem->mkdir ($path) ;
				
			$data =$this->GetFullDetails () ; // Get latest full details
			$results =$this->GetItems ($data) ; // From full details, get all individual elements to download
	 		$uris =$this->ReadSvfF2dManifest ($results) ; // .svf/.f2d/manifest additional references to download/create
	 		$refs =$this->GetAdditionalItems ($uris) ; // Get additional items from the previous extraction step
	 		$results =array_merge ($results, $refs) ;
	 		$results =$this->GenerateLocalHtml ($results) ; // Generate helper html/bat
	 		$results =$this->AddViewerFiles ($results) ; // Add the View & Data viewer files
				
	 		$this->PackItems ($results) ;
	 		$this->Cleanup (true) ;
		} catch ( Exception $ex ) {
			utils::log ('Error while exracting bubbles! ZIP not created. ' . $ex->getMessage ()) ;
			$this->Cleanup (false) ;
			return (false) ;
		}
		return (true) ;
	}

	// Get latest full details
	protected function GetFullDetails () {
		utils::log ('#1 - Getting full viewable information') ;
		$data =$this->lmv->all ($this->urn) ;
		if ( is_null ($data) )
			throw new \Exception ("GetFullDetails - lmv->all ({$this->urn}) failed") ;
		if ( isset ($data->progress) && $data->progress == 'complete' ) {
			$path =$this->lmv->dataDir ("/{$this->identifier}.resultdb.json") ;
			if ( file_put_contents ($path, json_encode ($data)) === false )
				throw new \Exception ("GetFullDetails - file_put_contents {$path} failed") ;
		}
		return ($data) ;
	}

	// From full details, get all individual elements to download
	protected function GetItems ($data) {
		if ( is_null ($data) || !is_object ($data) )
			throw new \Exception ('GetItems - null data') ;
		utils::log ('#2a - Filtering objects') ;
		// Collect Urns to extract from the server
		$items =$this->loop4Urns ($data) ;
		$items =array_filter ($items, function ($item) { return (isset ($item->urn) && strstr ($item->urn, 'urn:adsk.viewing:fs.file:')) ; }) ;
	
		// Collect Views to create from the viewables
		$views =$this->loop4Views ($data, $data) ;
		$items =array_merge ($items, $views) ;
	
		// Add manifest & metadata files for f2d file
		utils::log ('#2b - Adding manifest & metadata files for any .f2d files') ;
		for ( $i =0 ; $i < count ($items) ; $i++ ) {
			if ( isset ($items [$i]->urn) && pathinfo ($items [$i]->urn, PATHINFO_EXTENSION) === 'f2d' ) {
				$items [] =(object)array ( 'urn' => (pathinfo ($items [$i]->urn, PATHINFO_DIRNAME) . '/manifest.json.gz'), 'size' => 500 ) ;
				$items [] =(object)array ( 'urn' => (pathinfo ($items [$i]->urn, PATHINFO_DIRNAME) . '/metadata.json.gz'), 'size' => 1000 ) ;
				//$items [] =(object)array ( 'urn' => (pathinfo ($items [$i]->urn, PATHINFO_DIRNAME) . '/objects_attrs.json.gz'), 'size' => 5000 ) ;
				//$items [] =(object)array ( 'urn' => (pathinfo ($items [$i]->urn, PATHINFO_DIRNAME) . '/objects_attrs.json'), 'size' => 5000 ) ;
			}
		}
		$this->mgr->dlProgressFull ($items) ;
	
		utils::log ('#2c - Downloading each item') ;
		try {
			$results =[] ;
			foreach ( $items as $item ) {
				if ( !isset ($item->urn) )
					$results [] =$item ;
				else
					$results [] =$this->DownloadUrnAndSaveItemToDisk ($item) ;
			}
			$this->mgr->dlProgressFull ($results) ;
			return ($results) ;
		} catch ( Exception $ex ) {
			utils::log ('Something wrong happened during download') ;
			throw $ex ;
		}
	}

	protected function loop4Urns ($doc) {
		$data =[] ;
		if ( isset ($doc->urn) )
			$data [] =(object)array (
				'urn' => $doc->urn,
				'size' => (isset ($doc->size) ? intval ($doc->size) : Extractor::_default_size_)
			) ;
		if ( isset ($doc->children) ) {
			foreach ( $doc->children as $i )
				$data =array_merge ($data, $this->loop4Urns ($i)) ;
		}
		return ($data) ;
	}

	protected function loop4Views ($doc, $parentNode) {
		$data =[] ;
		if (   isset ($doc->urn)
			&& (pathinfo ($doc->urn, PATHINFO_EXTENSION) === 'svf' || pathinfo ($doc->urn, PATHINFO_EXTENSION) === 'f2d')
		) {
			$fullpath =utils::postStr ('/output/', $doc->urn) ;
			$data [] =(object)array (
				'path' => $fullpath,
				'name' => $parentNode->name,
				'size' => (isset ($doc->size) ? intval ($doc->size) : Extractor::_default_size_)
			) ;
		}
		if ( isset ($doc->children) ) {
			foreach ( $doc->children as $i )
				$data =array_merge ($data, $this->loop4Views ($i, $doc)) ;
		}
		return ($data) ;
	}

	protected function DownloadUrnAndSaveItemToDisk ($item) {
		try {
			$urn =$item->urn ;
			$data =$this->lmv->downloadItem ($urn) ;
			
			if ( is_int ($data) ) {
				if ( $data == 404 ) {
					utils::log ('Error 404 - ' . $urn . ' <ignoring>') ;
					$fullpath =$this->lmv->dataDir ("/{$this->identifier}/" . utils::postStr ('/output/', $urn)) ;
					return ((object)array (
						'urn' => $urn,
						'name' => utils::postStr ('/data/', $fullpath),
						'size' => $item->size,
						'error' => 404
					)) ;
				}
				utils::log ('Download failed for ' . $urn) ;
				throw new \Exception ($data) ;
			} else if ( !$data ) {
				utils::log ('Download failed for ' . $urn) ;
				throw new \Exception ('unknow error') ;
			}
			
			//$filename =pathinfo ($urn, PATHINFO_BASENAME) ;
			$fullpath =$this->lmv->dataDir ("/{$this->identifier}/" . utils::postStr ('/output/', $urn)) ;
			$filepath =pathinfo ($fullpath, PATHINFO_DIRNAME) ;
			
			$filesystem =new Filesystem () ;
			$filesystem->mkdir ($filepath) ;
			
			$this->mgr->dlProgressIntermediate ((object)array (
				'urn' => $urn,
				'name' => utils::postStr ('/data/', $fullpath),
				'size' => strlen ($data),
				'dl' => strlen ($data)
			)) ;
			
			if ( file_put_contents ($fullpath, $data) === false )
				throw new \Exception ("file_put_contents {$fullpath} failed") ;
			return ((object)array (
				'urn' => $urn,
				'name' => utils::postStr ('/data/', $fullpath),
				'size' => strlen ($data),
				'dl' => strlen ($data)
			)) ;
		} catch ( Exception $err ) {
			utils::log ('DownloadUrnAndSaveItemToDisk exception ' . $err) ;
			utils::log ('Save to disk failed for ' . $urn) ;
			throw $err ;
		}
	}

	protected function DownloadFileAndSaveItemToDisk ($item) {
		try {
			Unirest\Request::verifyPeer (false) ;
			$response =Unirest\Request::get (
				$item,
				array ( 'Authorization' => ('Bearer ' . lmv::bearer ()) ),
				null
			) ;
			
			//$filename =basename ($item) ;
			$fullpath =$this->lmv->dataDir ("/{$this->identifier}/" . utils::postStr ('/viewers/', $item)) ;
			$filepath =dirname ($fullpath) ;
	
			if ( $response->code != 200 ) {
				if ( $response->code == 404 ) {
					utils::log ("Error 404 - $item <ignoring>") ;
					utils::log ("Download failed for $fullpath") ;
					return ((object)array (
						'urn' => $item,
						'name' => utils::postStr ('/data/', $fullpath),
						'size' => 0,
						'dl' => 0
					)) ;
				}
				utils::log ("Download failed for $urn") ;
				throw new \Exception ($response->code) ;
			}
	
			$filesystem =new Filesystem () ;
			$filesystem->mkdir ($filepath) ;
			
	 		$this->mgr->dlProgressIntermediate ((object)array (
 				'urn' => $item,
 				'name' => utils::postStr ('/data/', $fullpath),
 				'size' => strlen ($response->raw_body),
 				'dl' => strlen ($response->raw_body)
			)) ;
	 		
			if ( file_put_contents ($fullpath, $response->raw_body) === false )
				throw new \Exception ("file_put_contents ($fullpath)") ;
			return ((object)array (
				'urn' => $item,
				'name' => utils::postStr ('/data/', $fullpath),
				'size' => strlen ($response->raw_body),
				'dl' => strlen ($response->raw_body)
			)) ;
		} catch ( Exception $err ) {
			utils::log ('DownloadFileAndSaveItemToDisk exception ' . $err->getMessage ()) ;
			utils::log ("Save to disk failed for $item") ;
			throw $err ;
		}
	}

	// .svf/.f2d/manifest additional references to download/create
	protected function ReadSvfF2dManifest ($results) {
		$uris =[] ;
		utils::log ('#3 - Reading svf/f2d/manifest information') ;
		// Collect the additional elements
		$svf =array_filter ($results, function ($item) { return (isset ($item->urn) && preg_match ('/^.*\.svf$/', $item->urn)) ; }) ;
		foreach ( $svf as $item )
			$uris =array_merge ($uris, $this->ReadSvfItem ($item, $svf)) ;
		
		$f2d =array_filter ($results, function ($item) { return (isset ($item->urn) && preg_match ('/^.*\.f2d$/', $item->urn)) ; }) ;
		foreach ( $f2d as $item )
			$uris =array_merge ($uris, $this->ReadF2dItem ($item, $f2d)) ;
			
		$gz =array_filter ($results, function ($item) { return (isset ($item->urn) && preg_match ('/^.*manifest\.json\.gz$/', $item->urn)) ; }) ;
		foreach ( $gz as $item )
			$uris =array_merge ($uris, $this->ReadManifest ($item)) ;
	
		return ($uris) ;
	}

	protected function ReadSvfItem ($item, $svf) {
		$uris =[] ;
	
		// Generate the document reference for local view html
		//$pathname =$item->name ;
		//$pathname =pathname.substring (pathname.indexOf ('/') + 1) ;
		//$name =path.basename (item.name) + '-' + svf.indexOf (item) ;
		//$uris [] =({ 'path': pathname, 'name': name }) ;
	
		// Get manifest file
		$path =$this->lmv->dataDir ("/{$item->name}", true) ;
		$zip =new \ZipArchive () ;
		if ( $zip->open ($path) === true ) {
			for ( $i =0 ; $i < $zip->numFiles ; $i++ ) {
				$stat =$zip->statIndex ($i) ;
				if ( substr ($stat ['name'], -1) != '/' && basename ($stat ['name']) == 'manifest.json' ) {
					$manifest =json_decode ($zip->getFromIndex ($i)) ;
					$uris =array_merge ($uris, $this->loopManifest ($manifest, dirname ($item->urn))) ;
				}
			}
		} else {
			throw new \Exception ("ZipArchive $path error") ;
		}
	
		return ($uris) ;
	}
	
	protected function ReadF2dItem ($item, $f2d) {
		$uris =[] ;
	
		// Generate the document reference for local view html
		//var pathname =item.name ;
		//pathname =pathname.substring (pathname.indexOf ('/') + 1) ;
		//var name =path.basename (item.name) + '-' + f2d.indexOf (item) ;
		//uris.push ({ 'path': pathname, 'name': name }) ;
	
		return ($uris) ;
	}

	protected function ReadManifest ($item) {
		$path =$path =$this->lmv->dataDir ("/{$item->name}/", true) ;
		$content =file_get_contents ($path) ;
		if ( $content === false )
			throw new \Exception ("file_get_contents ($path) error") ;
		$manifest =json_decode (gzuncompress ($content)) ;
		$uris =$this->loopManifest ($manifest, dirname ($item->urn)) ;
		return ($uris) ;
	}
	
	protected function loopManifest ($doc, $urnParent) {
		$data =[] ;
		if ( isset ($doc->URI) && strstr ($doc->URI, 'embed:/') == false ) // embed:/ - Resource embedded into the svf file, so just ignore it
			//$data [] =$urnParent . '/' . $doc->URI ;
			//$data [] =utils.normalize ($urnParent . '/' . $doc->URI) ;
			$data [] =(object)array (
				'urn' => utils::normalize ($urnParent . '/' . $doc->URI),
				'size' => (isset ($doc->size) ? intval ($doc->size) : Extractor::_default_size_)
			) ;
		if ( isset ($doc->assets) ) {
			foreach ( $doc->assets as $i )
				$data =array_merge ($data, $this->loopManifest ($i, $urnParent)) ;
		}
		return ($data) ;
	}
	
	// Get additional items from the previous extraction step
	protected function GetAdditionalItems ($uris) {
		utils::log ('#4 - Downloading additional items') ;
		try {
			$this->mgr->dlProgressFull ($uris) ;
			$this->mgr->setFactor (1.0) ;
			
			$results =[] ;
			foreach ( $uris as $item ) {
				if ( !isset ($item->urn) || isset ($item->dl) )
					$results [] =$item ;
				else
					$results [] =$this->DownloadUrnAndSaveItemToDisk ($item) ;
			}
			$this->mgr->dlProgressFull ($results) ;
			return ($results) ;
		} catch ( Exception $ex ) {
			utils::log ('Something wrong happened during additional items download') ;
			throw $ex ;
		}
	}

	// Generate helper html/bat
	// http://devzone.zend.com/1886/creating-web-page-templates-with-php-and-twig-part-1/
	// http://twig.sensiolabs.org/doc/api.html
	// http://blog.servergrove.com/2014/03/11/symfony2-components-overview-templating/
	protected function GenerateLocalHtml ($refs) {
		utils::log ('#5 - Generate Local Files') ;
		$loader =new Twig_Loader_Filesystem (array ( $this->lmv->viewsDir ('', true) )) ;
		$environment =new Twig_Environment ($loader, array ( 'debug' => false, 'charset' => 'utf-8', 'strict_variables' => true )) ;
		
		$doclist =array_filter ($refs, function ($obj) { return (isset ($obj->path)) ; }) ;
		$doclist =array_map (function ($obj) { if ( isset ($obj->size) ) unset ($obj->size) ; return ($obj) ; }, $doclist) ;
		$refs =array_filter ($refs, function ($obj) { return (!isset ($obj->path)) ; }) ;
		
		$path =$this->lmv->viewsDir ('/go.twig', true) ;
		$target =$this->lmv->dataDir ("/{$this->identifier}/index.bat") ;
		$filesystem =new Filesystem () ;
		$filesystem->copy ($path, $target, true) ;
		$refs [] =(object)array (
			'name' => "{$this->identifier}/index.bat",
			'size' => 602,
			'dl' => 602
		) ;
		
		//$path =utils::realpath (__DIR__ . '/../views/view.twig') ;
		$content =$environment->render (
			'view.twig', //$path,
			array ( 'docs' => array_values ($doclist) )
		) ;
		$target =$this->lmv->dataDir ("/{$this->identifier}/index.html") ;
		if ( file_put_contents ($target, $content) === false )
			throw new \Exception ("file_put_contents ($target)") ;
		$refs [] =(object)array (
			'name' => "{$this->identifier}/index.html",
			'size' => strlen ($content),
			'dl' => strlen ($content)
		) ;
		return ($refs) ;			
	}

	// Add the View & Data viewer files
	protected function AddViewerFiles ($refs) {
		utils::log ('#6 - Downloading the viewer files') ;
		try {
			$viewerFileList =require realpath (__DIR__ . '/viewer.php') ;
			$urns =array_map (
				function ($item) {
					$config =lmv::config () ;
					$urn ="{$config ['BaseEndPoint']}/viewingservice/{$config ['Version']}/viewers/$item" ;
					$this->mgr->dlProgressIntermediate ((object)array (
						'urn' => $urn,
						'name' => $item,
						'size' => 20000,
						'dl' => 0
					)) ;
					return ($urn) ;
				},
				$viewerFileList
			) ;
			
			$results =[] ;
			foreach ( $urns as $item )
				$results [] =$this->DownloadFileAndSaveItemToDisk ($item) ;
			
			$results =array_merge ($results, $refs) ;
			$this->mgr->dlProgressFull ($results) ;
			
			$filesystem =new Filesystem () ;
			$filesystem->copy (
				$this->lmv->rootDir ('/www/bower_components/jquery/dist/jquery.min.js', true),
				$this->lmv->dataDir ("/{$this->identifier}/jquery.min.js"),
				true
			) ;
			$results [] =(object)array (
				'urn' => 'www/bower_components/jquery/dist/jquery.min.js',
				'name' => "{$this->identifier}/jquery.min.js",
				'size' => 84380,
				'dl' => 84380
			) ;
			
			$filesystem->copy (
				$this->lmv->rootDir ('/www/bower_components/jquery-ui/jquery-ui.min.js', true),
				$this->lmv->dataDir ("/{$this->identifier}/jquery-ui.min.js"),
				true
			) ;
			$results [] =(object)array (
				'urn' => 'www/bower_components/jquery-ui/jquery-ui.min.js',
				'name' => "$this->identifier}/jquery-ui.min.js",
				'size' => 240427,
				'dl' => 240427
			) ;
			
			return ($results) ;
		} catch ( Exception $ex ) {
			utils::log ('Something wrong happened during viewer files download') ;
			throw $ex ;
		}
	}

	// Create a ZIP file and return all elements
	protected function PackItems ($results) {
		// We got all d/l
		try {
			// We are done! Create a ZIP file
			$zip =new \ZipArchive () ;
			$output =$this->lmv->extractDir ("/{$this->identifier}.zip") ;
			$zip->open ($output, \ZipArchive::CREATE) ;
			$zip->addEmptyDir ($this->identifier) ;
			
			$path =$this->lmv->dataDir ("/{$this->identifier}") ;
			$finder =new Finder () ;
			$finder->files ()->in ($path) ;
			foreach ( $finder as $file ) {
				$zpath =utils::postStr ('/data/', utils::normalize ($file->getRealpath ())) ;
				$zip->addFile ($file->getRealpath (), $zpath) ;
			}
			$zip->close () ;
			utils::log ('PackItems ended successfully.') ;
		} catch ( Exception $err ) {
			utils::log ('PackItems exception') ;
			throw $err ;
		}
	}

	protected function Cleanup ($bSuccess =false) {
		if ( $bSuccess ) {
			$path =$this->lmv->dataDir ("/{$this->identifier}.lock", true) ;
			if ( $path ) {
				$content =file_get_contents ($path) ;
				if ( $content !== false ) {
					$data =json_decode ($content) ;
					$this->Notify ($data->emails) ;
				}
			}
		} else {
			$this->NotifyError () ;
		}
		$filesystem =new Filesystem () ;
		$filesystem->remove ($this->lmv->dataDir ("/{$this->identifier}.lock")) ;
		$filesystem->remove ($this->lmv->dataDir ("/{$this->identifier}")) ;
	}

	protected function Notify ($tos) {
		if ( count ($tos) == 0 || !is_array ($tos) )
			return ;
		$loader =new Twig_Loader_Filesystem (array ( $this->lmv->viewsDir ('', true) )) ;
		$environment =new Twig_Environment ($loader, array ( 'debug' => false, 'charset' => 'utf-8', 'strict_variables' => true )) ;
		
		$content =$environment->render (
			'email.twig',
			array ( 'ID' => $this->identifier )
		) ;
		$config =lmv::config () ;
		$mjet =new Mailjet ($config ['MAILJET1'], $config ['MAILJET2']) ;
		foreach ( $tos as $to ) {
			$mjet->sendContent (
				'ADN Sparks <adn.sparks@autodesk.com>',
				$to,
				'Autodesk View & Data API Extractor notification',
				'html',
				$content
			) ;
			//'replyTo': 'adn.sparks@autodesk.com',
			//'forceEmbeddedImages': true
		}
	}

	protected function NotifyError () {
		$loader =new Twig_Loader_Filesystem (array ( $this->lmv->viewsDir ('', true) )) ;
		$environment =new Twig_Environment ($loader, array ( 'debug' => false, 'charset' => 'utf-8', 'strict_variables' => true )) ;
		
		$content =$environment->render (
			'email-extract-failed.twig',
			array ( 'ID' => $this->identifier )
		) ;
		$config =lmv::config () ;
		$mjet =new Mailjet ($config ['MAILJET1'], $config ['MAILJET2']) ;
		
		$mjet->sendContent (
			'ADN Sparks <adn.sparks@autodesk.com>',
			$config ['mailTo'],
			'Autodesk View & Data API Extraction failed',
			'html',
			$content
		) ;
		//'replyTo': 'adn.sparks@autodesk.com',
		//'forceEmbeddedImages': true
	}
	
}
