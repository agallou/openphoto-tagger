<?php
require_once 'vendor/.composer/autoload.php';

use Symfony\Component\Yaml\Parser;

$app = new Silex\Application();


$yaml      = new Parser();
$value     = $yaml->parse(file_get_contents('config.yml'));
$openphoto = new OpenPhotoOAuth($value['host'], $value['consumerKey'], $value['consumerSecret'], $value['token'], $value['tokenSecret']);


$app->register(new Silex\Provider\TwigServiceProvider(), array(
  'twig.path'       => __DIR__.'/views',
  'twig.class_path' => __DIR__.'/vendor/twig/lib',
));


$app->get('/', function () use ($app, $openphoto) {

  $photos = $openphoto->get('/photos/list.json', array('pageSize' => 1, 'returnSizes' => '700x700'));
  $photos = json_decode($photos);
  $photos = $photos->result;
  $photo = $photos[0];
  $path = $photo->path700x700;

  return $app['twig']->render('index.twig', array(
    'path' => $path,
  ));
});


$app->run();

