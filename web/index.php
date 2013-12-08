<?php
require_once __DIR__ . '/../vendor/autoload.php';

use Symfony\Component\Yaml\Parser;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

$app = new Silex\Application();


$yaml      = new Parser();
$config    = $yaml->parse(file_get_contents('../config/config.yml'));
$openphoto = new OpenPhotoOAuth(
    $config['host'],
    $config['consumerKey'],
    $config['consumerSecret'],
    $config['token'],
    $config['tokenSecret']
);
$app['openphoto'] = $openphoto;

$app->register(new Silex\Provider\UrlGeneratorServiceProvider());
$app->register(new Silex\Provider\SessionServiceProvider());

$app->register(new Silex\Provider\TwigServiceProvider(), array(
  'twig.path'       => __DIR__.'/../views',
  'twig.class_path' => __DIR__.'/../vendor/twig/lib',
));




$app->get('/logout', function () use ($app) {
    $app['session']->clear();
    return $app->redirect($app['url_generator']->generate('login'));
})->bind('logout');

$app->get('/login', function () use ($app) {
    if ($app['session']->has('user')) {
        return $app->redirect($app['url_generator']->generate('homepage'));
    }

    return $app['twig']->render('login.twig');

})->bind('login');

$app->post('/api/login', function () use ($app) {

    $yaml      = new Parser();
    $config    = $yaml->parse(file_get_contents(__DIR__ . '/../config/browserid.yml'));
    $security  = $yaml->parse(file_get_contents(__DIR__ . '/../config/security.yml'));



    $url  = 'https://browserid.org/verify';
    $data = http_build_query(array(
      'assertion' => $_POST['assertion'],
      'audience' => urlencode($config['audience'])

    ));

    $params = array(
      'http' => array(
        'method'    => 'POST',
          'content' => $data,
          'header'  =>
            "Content-type: application/x-www-form-urlencoded\r\n"
            . "Content-Length: " . strlen($data) . "\r\n"
      )
    );

    $ctx = stream_context_create($params);
    $fp  = fopen($url, 'rb', false, $ctx);

    if ($fp) {
        $result = stream_get_contents($fp);
    } else {
        $result = false;
    }
    $json = json_decode($result);
    if ($json->status == 'okay' && in_array($json->email, $security['users'])) {
        $app['session']->start();
        $app['session']->set('user', array('username' => $json->email));
    }

    return $result;
});




$mustBeLogged = function (Request $request) use ($app) {
    if (!$app['session']->has('user')) {
        return $app->redirect($app['url_generator']->generate('login', array(), true));
    }
};








$app['debug'] = true;

$app->get('/photo/{id}/display', function ($id) use ($app, $config) {

    $photo = $app['openphoto']->get(sprintf('/photo/%s/view.json', $id), array('returnSizes' => '700x700'));
    $photo = json_decode($photo);
    $photo = $photo->result;

    $path = $photo->path700x700;

    $tokenTags = array();
    foreach ($photo->tags as $tag) {
        $tokenTags[] = array('id' => $tag, 'name' => $tag);
    }


    $tags = $app['openphoto']->get('/tags/list.json');
    $tags = json_decode($tags);
    $displayedTags = array();
    $nbTag = 0;
    foreach ($tags->result as $tag) {
        if (isset($config['tags']) && $tag->id == $config['tags']) {
            $nbTag = $tag->count;
        }
    }

    return $app['twig']->render('index.twig', array(
      'path'  => $path,
      'tags'  => $tokenTags,
      'id'    => $photo->id,
      'next'  => $app['url_generator']->generate('homepage'),
      'nb'    => $nbTag,
      'date'  => $photo->dateTaken,
      'title' => $photo->title,
    ));
})->before($mustBeLogged)->bind('photo_display');


$app->post('/photo/{id}/update', function (Request $request) use ($app, $openphoto) {
    $id    = $request->get('id');
    $tags  = $request->get('tags');
    $photo = $app['openphoto']->post(sprintf('/photo/%s/update.json', $id), array('tags' => $tags));
})->before($mustBeLogged);


$app->get('/', function () use ($app, $config) {
    $options = array('pageSize' => 1);
    if (isset($config['tags'])) {
        $options['tags'] = $config['tags'];
    }

    $photos = $app['openphoto']->get('/photos/list.json', $options);
    $photos = json_decode($photos);
    $photos = $photos->result;

    $photo = $photos[0];
    $url = $app['url_generator']->generate('photo_display', array('id' => $photo->id));
    return sprintf('<meta http-equiv="refresh" content="0; url=%s" />', $url);
})->before($mustBeLogged)->bind('homepage');

$app->get('/tags', function (Request $request) use ($app, $config) {
    $tags = $app['openphoto']->get('/tags/list.json');
    $tags = json_decode($tags);
    $displayedTags = array();
    $query = $request->get('q');
    foreach ($tags->result as $tag) {
        if (null === $query || false !== strpos(strtolower($tag->id), strtolower($query))) {
            $displayedTags[] = array('id' => $tag->id, 'name' => $tag->id);
        }
    }
    return json_encode($displayedTags);
})->before($mustBeLogged);

$app->run();
