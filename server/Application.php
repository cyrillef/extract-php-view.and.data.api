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
//use Silex\Provider\DoctrineServiceProvider ;
//use Silex\Provider\FormServiceProvider ;
//use Silex\Provider\HttpCacheServiceProvider ;
//use Silex\Provider\MonologServiceProvider ;
//use Silex\Provider\SecurityServiceProvider ;
use Silex\Provider\SessionServiceProvider ;
//use Silex\Provider\TranslationServiceProvider ;
use Silex\Provider\TwigServiceProvider ;
//use Silex\Provider\UrlGeneratorServiceProvider ;
//use Silex\Provider\ValidatorServiceProvider ;
//use Symfony\Component\Security\Core\Encoder\PlaintextPasswordEncoder ;
//use Symfony\Component\Security\Http\Authentication\AuthenticationUtils ;
//use Symfony\Component\Translation\Loader\YamlFileLoader ;
use Symfony\Component\HttpFoundation\Request ;
use Symfony\Component\HttpFoundation\Response ;
//use Symfony\Component\HttpFoundation\ParameterBag ;

class BaseApplication extends Silex\Application {
	protected $rootDir ;
	protected $env ;

	public function __construct ($env) {
		utils::log ('Extract Application running') ;
		
		$this->rootDir =utils::realpath (__DIR__ . '/../') ;
		$this->env =$env ;
		parent::__construct () ;
		
		// $app =$this ;
		// $app->before (function (Request $request) {
		// 	if ( strpos ($request->headers->get ('Content-Type'), 'application/json') === 0 ) {
		// 		$data =json_decode ($request->getContent (), true) ;
		// 		$request->request->replace (is_array ($data) ? $data : array ()) ;
		// 	}
		// }) ;
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

	public function getEnv () {
		return ($this->env) ;
	}

}

class Application extends BaseApplication {

	public function __construct ($env) {
		utils::log ('WEB Extract Application running') ;
		parent::__construct ($env) ;
		$app =$this ;
		
		// Override these values in resources/config/prod.php file
		//$app ['var_dir'] =$this->rootDir . '/var' ;
		//$app ['locale'] ='fr' ;
		//$app ['http_cache.cache_dir'] =$app->share (function (Application $app) {
		//	return ($app ['var_dir'] . '/http') ;
		//}) ;
		//$app ['monolog.options'] =[
		//	'monolog.logfile' => $app['var_dir'].'/logs/app.log',
		//	'monolog.name' => 'app',
		//	'monolog.level' => 300, // = Logger::WARNING
		//] ;
		//$app ['security.users'] =array( 'alice' => array ( 'ROLE_USER', 'password' ) ) ;
		
		lmv::tokenObject () ;
		
		//$app->register (new HttpCacheServiceProvider ()) ;
		$app->register (new SessionServiceProvider ()) ;
		//$app->register (new ValidatorServiceProvider ()) ;
		//$app->register (new FormServiceProvider ()) ;
		//$app->register (new UrlGeneratorServiceProvider ()) ;
		//$app->register (new DoctrineServiceProvider ()) ;
		//$app->register (new SecurityServiceProvider (), array (
		//	'security.firewalls' => array (
		//		'admin' => array (
		//			'pattern' => '^/',
		//			'form' => array (
		//				'login_path' => '/login',
		//			),
		//			'logout' => true,
		//			'anonymous' => true,
		//			'users' => $app ['security.users'],
		//		),
		//	),
		//)) ;
		//$app ['security.encoder.digest'] =$app->share (function ($app) {
		//	return (new PlaintextPasswordEncoder ()) ;
		//}) ;
		//$app ['security.utils'] =$app->share (function ($app) {
		//	return (new AuthenticationUtils ($app ['request_stack'])) ;
		//}) ;
		//$app->register (new TranslationServiceProvider ()) ;
		//$app ['translator'] =$app->share($app->extend ('translator', function ($translator, $app) {
		//	$translator->addLoader ('yaml', new YamlFileLoader ()) ;
		//	$translator->addResource ('yaml', $this->rootDir.'/resources/translations/fr.yml', 'fr') ;
		//	return ($translator) ;
		//})) ;
		//$app->register (new MonologServiceProvider (), $app ['monolog.options']) ;
		$app->register (new TwigServiceProvider (), array (
			//	'twig.options' => array (
			//		'cache' => $app ['var_dir'].'/cache/twig',
			//		'strict_variables' => true,
			//	),
			//	'twig.form.templates' => array ( 'bootstrap_3_horizontal_layout.html.twig' ),
			'twig.path' => array ( $this->rootDir . '/views' ),
		)) ;
		//$app ['twig'] = $app->share ($app->extend ('twig', function ($twig, $app) {
		//	$twig->addFunction (new \Twig_SimpleFunction ('asset', function ($asset) use ($app) {
		//		$base = $app['request_stack']->getCurrentRequest ()->getBasePath () ;
		//		return (sprintf ($base . '/' . $asset, ltrim ($asset, '/'))) ;
		//	})) ;
		//	return ($twig) ;
		//})) ;
		//$app->mount ('', new ControllerProvider ()) ;
		
		$app->mount ('/api', new LmvFile ()) ;
		$app->mount ('/api/projects', new LmvProjects ()) ;
		$app->mount ('/api/results', new LmvResults ()) ;
		
		$app->get ('/', function () use ($app) {
			return ($app ['twig']->render ('index.html.twig', [])) ;
		}) ;
		$app->get ('/explore/{identifier}', function ($identifier) use ($app) {
			$bucket =lmv::getDefaultBucket () ;
			$zipExist =$app->extractDir ("/$identifier.zip", true) !== false ;
			$path =$app->dataDir ("/$identifier.resultdb.json") ;
			$content =file_get_contents ($path) ;
			if ( $content === false ) {
				$app->abort (Response::HTTP_NOT_ACCEPTABLE, "DB record not present") ;
				return ;
			}
			$data =json_decode ($content) ;
			
			return ($app ['twig']->render (
				'explore.twig',
				[
					'bucket' => $bucket,
					'extracted' => ($zipExist ? 'true' : 'false'),
					'urn' => $data->urn,
					'accessToken' => lmv::bearer (),
					'root' => $identifier
				]
			)) ;
		}) ;
		
	}

}
