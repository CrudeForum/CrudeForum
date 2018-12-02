<?php

/**
 * Bootstrapping the main objects to use in the forum
 *
 * PHP Version 7.1
 *
 * @file     bootstrap.php
 * @category File
 * @package  None
 * @author   Koala Yeung <koalay@gmail.com>
 * @license  https://opensource.org/licenses/MIT MIT License
 * @link     https://github.com/crude-forum/crude-forum/blob/master/bootstrap.php Source Code
 */

// common bootstrap code
require_once __DIR__ . '/vendor/autoload.php';

use \CrudeForum\CrudeForum\Core;
use \CrudeForum\CrudeForum\StreamFilter;
use \League\Flysystem\Adapter\Local;
use \League\Flysystem\Filesystem;
use \Cache\Adapter\Filesystem\FilesystemCachePool;

// load env config
Core::loadDotenv(__DIR__);

// for debug
if ((bool) Core::env('CRUDE_DEBUG', 'FALSE')) {
    ini_set('display_errors', 1);
    error_reporting(E_ALL);
}

// set default timezone
date_default_timezone_set(Core::env('CRUDE_TIMEZONE', 'UTC'));

// initialize forum storage
$storage = new \CrudeForum\CrudeForum\Storage\FileStorage(
    [
        'logDirectory' => Core::env('CRUDE_DIR_LOGS', __DIR__ . '/data/logs'),
        'dataDirectory' => Core::env('CRUDE_DIR_DATA', __DIR__ . '/data/forumdata_utf8'),
    ]
);

// initialize template engine
$template = new \Twig\Environment(
    new \Twig\Loader\FilesystemLoader(
        [
            __DIR__ . '/views/custom',
            __DIR__ . '/views/dist',
        ]
    ),
    [
        'cache' =>
            (($cache_dir = Core::env('CRUDE_DIR_CACHE')) === null) ? false : $cache_dir . '/twig',
    ]
);

// initialize cache pool
$cache = null;
if (!empty(Core::env('CRUDE_DIR_CACHE'))) {
    $fsAdapter = new Local(Core::env('CRUDE_DIR_CACHE') . '/common');
    $fs = new Filesystem($fsAdapter);
    $cache = new FilesystemCachePool($fs);
}

// define body-to-html filter
$bodyToHTML = new \Twig\TwigFilter('bodyToHTML', function ($string, $type = 'text') use ($cache) {
    $filter = StreamFilter::pipeString(
        [\CrudeForum\CrudeForum\StreamFilter::class, 'quoteToBlockquote'],
        [\CrudeForum\CrudeForum\StreamFilter::class, 'reduceFlashEmbed'],
        \CrudeForum\CrudeForum\StreamFilter::autoWidgetfy($cache, []),
        [\CrudeForum\CrudeForum\StreamFilter::class, 'autoLink'],
        [\CrudeForum\CrudeForum\StreamFilter::class, 'autoParagraph']
    );

    // concat filtered lines back into string
    // and do auto br
    return nl2br($filter($string), false);
});
$template->addFilter($bodyToHTML);

// define linkTo filter
$link = new \Twig\TwigFilter('link', function ($id, $type, $action=null) {
    global $forum;
    return $forum->linkTo($type, $id, $action);
});
$template->addFilter($link);

// initialize forum core
$forum = new Core(
    $storage,
    $template,
    [
        'administrator' => Core::env('CRUDE_ADMIN'),
        'baseURL' => Core::env('CRUDE_BASE_URL', Core::defaultBaseURL()),
        'basePath' => Core::env('CRUDE_BASE_PATH', Core::defaultBasePath()),
    ]
);

// some configurations in routes and template rendering
$configs = [
    'formPostAuthor' => Core::env('CRUDE_FORM_POST_AUTHOR'),
    'formPostTitle' => Core::env('CRUDE_FORM_POST_TITLE'),
    'formPostBody' => Core::env('CRUDE_FORM_POST_BODY'),
    'postPerPage' => (int) Core::env('CRUDE_POST_PER_PAGE'),
    'rssPostLimit' => (int) Core::env('CRUDE_RSS_POST_NUMBER'),
    'siteName' => Core::env('CRUDE_SITE_NAME'),
    'sloganTop' => Core::env('CRUDE_SLOGAN_TOP'),
    'sloganBottom' => Core::env('CRUDE_SLOGAN_BOTTOM'),
    'baseURL' => Core::env('CRUDE_BASE_URL'),
    'basePath' => Core::env('CRUDE_BASE_PATH'),
    'assetsPath' => Core::env('CRUDE_ASSETS_PATH'),
];

// initialize route dispatcher
$dispatcher = FastRoute\simpleDispatcher(
    function (FastRoute\RouteCollector $router) use ($configs) {
        // use routes defined in routes.php
        include __DIR__ . '/routes.php';
    }
);
