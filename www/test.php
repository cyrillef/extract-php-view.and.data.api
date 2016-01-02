<?php
// http://www.sitepoint.com/introduction-silex-symfony-micro-framework/
// https://openclassrooms.com/courses/premiers-pas-avec-le-framework-php-silex
date_default_timezone_set ('UTC') ;

require_once __DIR__ . '/../vendor/autoload.php';
$app =new Silex\Application () ;

$app->get ('/hello/{name}', function ($name) use ($app) {
    return ('Hello ' . $app->escape ($name)) ;
}) ;

$app->get ('/', function () {
    return ("Hello world") ;
}) ;

$app->get('/tet', function () {
    return (new Symfony\Component\HttpFoundation\Response ("Hello world")) ;
}) ;

//$app->get("/users/{id}", function ($id) {
//        return ("User - {$id}") ;
//    })
//    ->value ("id", 0) // set a default value
//    ->assert ("id", "\\d+") ; // make sure the id is numeric
//
//$app->get ("/users/{user}", function ($user) {
//        // return the user profile
//        return ("User {$user}") ;
//    })
//    ->convert ("user", function ($id) {
//        $userRepo =new User () ;
//        $user =$userRepo->find ($id) ;
//        if ( !$user )
//            return (new Symfony\Component\HttpFoundation\Response ("User #{$id} not found.", 404)) ;
//        return ($user) ;
//    }) ;
//
//$app->get ("/users/{user}", function ($user) {
//        // return the user profile
//        return ("User {$user}") ;
//    })
//    ->before (function ($request, $app) {
//        // redirect if the user is not logged in
//    })
//    ->after (function ($request, $response) {
//        // log request events
//    })
//    ->finish (function () {
//        // log request event
//    }) ;

$app ['debug'] =true ;
$app->run () ;
