<?php
require_once __DIR__ . '/../vendor/autoload.php';

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\DependencyInjection\ContainerBuilder;

$app = new Silex\Application();

$container = new ContainerBuilder();
$container->setParameter("host", getenv("OPENPHOTO_HOST"));
$container->setParameter("consumerKey", getenv("OPENPHOTO_CONSUMERKEY"));
$container->setParameter("consumerSecret", getenv("OPENPHOTO_CONSUMERSECRET"));
$container->setParameter("token", getenv("OPENPHOTO_TOKEN"));
$container->setParameter("tokenSecret", getenv("OPENPHOTO_TOKENSECRET"));
$container->setParameter("tags", getenv("TAGS"));
$container->setParameter('browserid.audience', getenv("BROWSERID_AUDIENCE"));
$container->setParameter('security.users', getenv("SECURITY_USERS"));


$openphoto = new OpenPhotoOAuth(
    $container->getParameter("host"),
    $container->getParameter('consumerKey'),
    $container->getParameter('consumerSecret'),
    $container->getParameter('token'),
    $container->getParameter('tokenSecret')
);
$app['openphoto'] = $openphoto;

$app->register(new Silex\Provider\UrlGeneratorServiceProvider());
$app->register(new Silex\Provider\SessionServiceProvider());

$app->register(new Silex\Provider\TwigServiceProvider(), array(
  'twig.path'       => __DIR__.'/../views',
  'twig.class_path' => __DIR__.'/../vendor/twig/lib',
));



$app['twig'] = $app->share($app->extend('twig', function($twig, $app) {
    $files = glob(__DIR__ . '/css/*');
    $file = array_pop($files);
    $twig->addGlobal('cssfile', basename($file));

    return $twig;
}));



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

$app->post('/api/login', function () use ($app, $container) {

    $users =  explode(",", $container->getParameter('security.users'));

    $url  = 'https://browserid.org/verify';
    $data = http_build_query(array(
      'assertion' => $_POST['assertion'],
      'audience' => urlencode($container->getParameter('browserid.audience'))

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
    if ($json->status == 'okay' && in_array($json->email, $users)) {
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

$app->get('/photo/{id}/display', function ($id) use ($app, $container) {

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
        if (strlen($container->getParameter('tags')) && $tag->id == $container->getParameter('tags')) {
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


$app->get('/', function () use ($app, $container) {
    $options = array('pageSize' => 1);
    if (strlen($container->getParameter('tags'))) {
        $options['tags'] = $container->getParameter('tags');
    }

    $photos = $app['openphoto']->get('/photos/list.json', $options);
    $photos = json_decode($photos);
    $photos = $photos->result;

    $photo = $photos[0];
    $url = $app['url_generator']->generate('photo_display', array('id' => $photo->id));
    return sprintf('<meta http-equiv="refresh" content="0; url=%s" />', $url);
})->before($mustBeLogged)->bind('homepage');

$app->get('/tags', function (Request $request) use ($app) {
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
