<?php


function test () {
	$bucket =lmv::getDefaultBucket () ;
	$ret =preg_match ('/^[-_.a-z0-9]{3,128}$/', $bucket) ;
	if ( !$ret )
		return (false) ;
	
	$lmv =new lmv ($bucket) ;
	
	$path =$app->dataDir ("/{$connections->uniqueIdentifier}.dependencies.json") ;
	$content =file_get_contents ($path) ;
	$items =json_decode ($content) ;

	// Bucket
	$policy ='transient' ;
	$r1 =$lmv->createBucketIfNotExist ($policy) ;
	if ( $r1 == null ) {
		// No need to continue if the bucket was not created
		utils::log ('Failed to create bucket!') ;
		return (new Response ('Failed to create bucket!', Response::HTTP_INTERNAL_SERVER_ERROR, [ 'Content-Type' => 'text/plain' ])) ;
	}
	utils::log ('Bucket created (or did exist already)!') ;
		
	// Uploading file(s)
	utils::log ('Uploading(s)') ;
	foreach ( $items as $item ) {
		utils::log (" uploading $item") ;
		$r2 =$lmv->uploadFile ($item) ;
		if ( $r2 == null ) {
			utils::log (" Failed to upload $item") ;
			utils::log ('Something wrong happened during upload') ;
			return (new Response ('Something wrong happened during upload', Response::HTTP_INTERNAL_SERVER_ERROR, [ 'Content-Type' => 'text/plain' ])) ;
		}
		utils::log ("$item upload completed!") ;
	}
	utils::log ('All files uploaded') ;
	
	// Dependencies
	$r3 =$lmv->setDependencies (count ($items) == 1 ? null : $connections) ;
	if ( $r3 == null ) {
		utils::log ("Failed to set connections") ;
		return (new Response ('Failed to set connections', Response::HTTP_INTERNAL_SERVER_ERROR, [ 'Content-Type' => 'text/plain' ])) ;
	}
	utils::log ('References set, launching translation') ;
	
	// Register
	$r4 =$lmv->register ($connections) ;
	if ( $r3 == null ) {
		utils::log ('URN registration for translation failed') ;
		return (new Response ('URN registration for translation failed', Response::HTTP_INTERNAL_SERVER_ERROR, [ 'Content-Type' => 'text/plain' ])) ;
	}
	utils::log ('URN registered for translation') ;
	
	// We are done for now!
	
	// Just remember locally we did submit the project for translation
	$identifier =$connections->uniqueIdentifier ;
	$urn =$lmv->getURN ($identifier) ;
	$urn =base64_encode ($urn) ;
	$data =(object)array (
			'guid' => $urn,
			'progress' => '0% complete',
			'startedAt' => gmdate (DATE_RFC2822),
			'status' => 'requested',
			'success' => '0%',
			'urn' => $urn
	) ;
	$path =$app->dataDir ("/$identifier.resultdb.json") ;
	file_put_contents ($path, json_encode ($data)) ;
	
	//- We are done! email me if any error
	if ( err ) {
		fs.readFile ('views/email-xlt-failed.ejs', 'utf-8', function (err, st) {
			if ( err )
				return ;
				var obj ={ ID: connections.uniqueIdentifier } ;
				var data =ejs.render (st, obj) ;
				sendMail ({
					'from': 'ADN Sparks <adn.sparks@autodesk.com>',
					'replyTo': 'adn.sparks@autodesk.com',
					//'to': 'adn.sparks@autodesk.com',
					'subject': 'Autodesk View & Data API Extractor app failed to translate a project',
					'html': data,
					'forceEmbeddedImages': true
				}) ;
		}) ;
			fs.rename (
					'data/' + connections.uniqueIdentifier + '.resultdb.json',
					'data/' + connections.uniqueIdentifier + '.resultdb.failed',
					function (err) {}
					) ;
			return ;
	} else {
		fs.readFile ('data/' + connections.uniqueIdentifier + '.dependencies.json', 'utf-8', function (err, data) {
			if ( err )
				return ;
				data =JSON.parse (data) ;
				for ( var i =0 ; i < data.length ; i++ )
					fs.unlink ('data/' + data [i] + '.json', function (err) {}) ;
					fs.unlink ('data/' + connections.uniqueIdentifier + '.dependencies.json', function (err) {}) ;
					fs.unlink ('data/' + connections.uniqueIdentifier + '.connections.json', function (err) {}) ;
		}) ;
	}
	}) ;
}