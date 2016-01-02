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

// Progress helper
class ExtractorProgressMgr {
	private $identifier ;
	private $progress ;
	private $factor ;
	private $children ;

	public function __construct ($identifier) {
		$this->identifier =$identifier ;
		$this->progress =0 ;
		$this->factor =0.5 ;
		$this->children =[] ;
		
		// $lock =utils::realpath (__DIR__ . "/../data/{$this->identifier}.lock") ;
		// if ( $lock ) {
		// 	$content =file_get_contents ($lock) ;
		// 	$data =json_decode ($content) ;
		// 	$this->progress =intval ($data->progress) ;
		// }
	}
	
	public function progress () {
		$lock =utils::realpath (__DIR__ . "/../data/{$this->identifier}.lock") ;
		if ( $lock ) {
			$content =file_get_contents ($lock) ;
			$data =json_decode ($content) ;
			return (intval ($data->progress)) ;
		}
		return ($this->progress) ;
	}

	public function dlProgressIntermediate ($item) {
		$this->children =array_filter (
			$this->children,
			function ($elt) use ($item) {
				return (!isset ($elt->urn) || $elt->urn != $item->urn) ;
			}
		) ;
		$this->children [] =$item ;
		$this->_dlProgress () ;
	}

	public function dlProgressFull ($items) {
		$this->children =$items ;
		$this->_dlProgress () ;
	}

	public function setFactor ($factor =1.0) {
		$this->factor =$factor ;
	}

	protected function _dlProgress () {
		if ( count ($this->children) == 0 )
			return ($this->progress =0) ;
		$ret =array_reduce (
			$this->children,
			function ($previousValue, $currentValue) {
				if ( isset ($currentValue->dl) && $currentValue->size === Extractor::_default_size_ )
					$currentValue->size =$currentValue->dl ;
				$previousValue->size +=(isset ($currentValue->size) ? $currentValue->size : 0) ;
				$previousValue->dl +=(isset ($currentValue->dl) ? $currentValue->dl : 0) ;
				return ($previousValue) ;
			},
			(object)array ( 'size' => 0, 'dl' => 0 )
		) ;
		$pct =0 ;
		if ( $ret->size != 0 )
			$pct =intval (floor ($this->factor * 100 * $ret->dl / $ret->size)) ;
		$this->progress =$pct ;
		
		$lock =utils::realpath (__DIR__ . "/../data/{$this->identifier}.lock") ;
		if ( $lock ) {
			$content =file_get_contents ($lock) ;
			$data =json_decode ($content) ;
			$data->progress =$pct ;
			file_put_contents ($lock, json_encode ($data)) ;
		}

		//utils::log ("progress({$this->identifier}): {$ret->dl} / {$ret->size} = $pct%") ;
		return ($pct) ;
	}

}
