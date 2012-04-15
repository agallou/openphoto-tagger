<?php
require_once 'vendor/.composer/autoload.php';

use Symfony\Component\Yaml\Parser;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;


$app = new Silex\Application();


$yaml      = new Parser();
$config    = $yaml->parse(file_get_contents('config.yml'));
$openphoto = new OpenPhotoOAuth($config['host'], $config['consumerKey'], $config['consumerSecret'], $config['token'], $config['tokenSecret']);
$app['openphoto'] = $openphoto;

$app->register(new Silex\Provider\UrlGeneratorServiceProvider());

$app->register(new Silex\Provider\TwigServiceProvider(), array(
  'twig.path'       => __DIR__.'/views',
  'twig.class_path' => __DIR__.'/vendor/twig/lib',
));


$app['debug'] = true;

$app->get('/photo/{id}/display', function ($id) use ($app, $openphoto) {

  $photo = $app['openphoto']->get(sprintf('/photo/%s/view.json', $id), array('returnSizes' => '700x700'));
  $photo = json_decode($photo);
  $photo = $photo->result;

  $path = $photo->path700x700;

  $tokenTags = array();
  foreach ($photo->tags as $tag)
  {
    $tokenTags[] = array('id' => $tag, 'name' => $tag);
  }
  return $app['twig']->render('index.twig', array(
    'path' => $path,
    'tags' => $tokenTags,
    'id'   => $photo->id,
  ));
})->bind('photo_display');


$app->post('/photo/{id}/update', function (Request $request) use ($app, $openphoto) {
  $id    = $request->get('id');
  $tags  = $request->get('tags');
  $photo = $app['openphoto']->post(sprintf('/photo/%s/update.json', $id), array('tags' => $tags));
});


$app->get('/', function () use ($app, $config) {
  $options = array('pageSize' => 1);
  if (isset($config['tags']))
  {
    $options['tags'] = $config['tags'];
  }


  $photos = $app['openphoto']->get('/photos/list.json', $options);
  $photos = json_decode($photos);
  $photos = $photos->result;

  $photo = $photos[0];

  return '<a href="'.$app['url_generator']->generate('photo_display', array('id' => $photo->id)).'">Next photo</a>';
});

$app->get('/tags', function (Request $request) use ($app, $config) {
  $tags = $app['openphoto']->get('/tags/list.json');
  $tags = json_decode($tags);
  $displayedTags = array();
  $query = $request->get('q');
  foreach ($tags->result as $tag)
  {
    if (strpos(strtolower($tag->id), strtolower($query)))
    {
      $displayedTags[] = array('id' => $tag->id, 'name' => $tag->id);
    }
  }
  return json_encode($displayedTags);
});

$app->run();

