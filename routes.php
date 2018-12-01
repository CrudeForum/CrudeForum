<?php

/**
 * Central route file that is used by bootstrap.php
 * to bootstrap the route dispatcher.
 *
 * PHP Version 7.1
 *
 * @category File
 * @package  None
 * @author   Koala Yeung <koalay@gmail.com>
 * @license  https://opensource.org/licenses/MIT MIT License
 * @link     https://github.com/crude-forum/crude-forum/blob/master/routes.php Source Code
 */

use \CrudeForum\CrudeForum\Post;
use \CrudeForum\CrudeForum\PostSummary;
use \CrudeForum\CrudeForum\Iterator\Utils;
use \CrudeForum\CrudeForum\Iterator\Paged;
use \CrudeForum\CrudeForum\Iterator\Filtered;
use \CrudeForum\CrudeForum\Iterator\Map;

$router->addRoute(
    'GET',
    '/post/{postID:\d+}',
    function ($vars, $forum) use ($configs) {
        $lock = $forum->getLock();
        $postID = $vars['postID'];

        $forum->log("read?" . $postID);
        $post = $forum->readPost($postID);
        $lock->unlock();
        if ($post === null) {
            die('post not found');
        }

        echo $forum->template->render(
            'post.twig',
            [
                'configs' => $configs,
                'topLinks' => [
                    ['text' => '回覆', 'href' => $forum->linkTo('post', $postID, 'reply')],
                    ['text' => '上一發言', 'href' => $forum->linkTo('post', $postID, 'prev')],
                    ['text' => '下一發言', 'href' => $forum->linkTo('post', $postID, 'next')],
                    ['text' => '回論壇', 'href' => $forum->linkTo('post', $postID, 'back')],
                ],
                'bottomLinks' => [
                    ['text' => '回覆', 'href' => $forum->linkTo('post', $postID, 'reply')],
                    ['text' => '上一發言', 'href' => $forum->linkTo('post', $postID, 'prev')],
                    ['text' => '下一發言', 'href' => $forum->linkTo('post', $postID, 'next')],
                    ['text' => '回論壇', 'href' => $forum->linkTo('post', $postID, 'back')],
                ],
                'postID' => $postID,
                'post' => $post,
            ]
        );
    }
);

$router->addRoute(
    'GET',
    '/post/{postID:\d+}/prev',
    function ($vars, $forum) use ($configs) {
        $lock = $forum->getLock();
        $postID = $vars['postID'];
        try {
            $prev = $forum->readPrevPostSummary($postID);
            $lock->unlock();
            header('Refresh: 0; URL=' . $forum->linkTo('post', $prev->id));
            echo $forum->template->render(
                'base.twig',
                ['configs' => $configs]
            );
        } catch (Exception $e) {
            $lock->unlock();
            die($e->getMessage());
        }
    }
);

$router->addRoute(
    'GET',
    '/post/{postID:\d+}/next',
    function ($vars, $forum) use ($configs) {
        $lock = $forum->getLock();
        $postID = $vars['postID'];
        try {
            $next = $forum->readNextPostSummary($postID);
            $lock->unlock();
            header('Refresh: 0; URL=' . $forum->linkTo('post', $next->id));
            echo $forum->template->render(
                'base.twig',
                ['configs' => $configs]
            );
        } catch (Exception $e) {
            $lock->unlock();
            die($e->getMessage());
        }
    }
);

$router->addRoute(
    'GET',
    '/post/{postID:\d+}/back',
    function ($vars, $forum) use ($configs) {
        $lock = $forum->getLock();
        $postID = $vars['postID'];
        try {
            $postSummary = $forum->readPostSummary($postID);
            $lock->unlock();
            header(
                'Refresh: 0; URL=' .
                $forum->linkTo(
                    'forum',
                    $configs['postPerPage'] * floor($postSummary->pos / $configs['postPerPage'])
                )
            );
            echo $forum->template->render(
                'base.twig',
                ['configs' => $configs]
            );
        } catch (Exception $e) {
            $lock->unlock();
            die($e->getMessage());
        }
    }
);

$showForm = function ($vars, $forum) use ($configs) {
    $postID = $vars['postID'] ?? '';
    $action = $vars['action'];

    if ($action == 'edit') {
        $post = $forum->readPost($postID);
        $post->author = $post->header['author'] ?? '';
        $forumName = $_COOKIE['forumName'] ?? '';

        // read current post author from post
        // check if the post user is cookie user, or if user is admin
        if (($forumName === '' || $forumName !== $post->author) && !$forum->isAdmin($forumName)) {
            throw new Exception('permission denied');
        }
    } else if ($action == 'reply') {
        $parent = $forum->readPost($postID);
        $post = Post::replyFor($parent);
        $post->author = $_COOKIE['forumName'] ?? '';
        if ($parent == null) {
            die('post not found');
        }
    } else if ($action == 'add' && empty($postID)) {
        $post = new Post();
        $post->author = $_COOKIE['forumName'] ?? '';
    }

    echo $forum->template->render(
        'postForm.twig',
        [
            'pageClass' => 'page-form',
            'configs' => $configs,
            'action' => $action,
            'postID' => $postID,
            'post' => $post,
        ]
    );
};
$router->addRoute('GET', '/post/{postID:\d+}/{action:edit|reply}', $showForm);
$router->addRoute('GET', '/post/{action:add}', $showForm);

$savePost = function ($vars, $forum) use ($configs) {
    $forum->log("say?");
    $action = $vars['action'] ?? '';
    $postID = $vars['postID'] ?? false;

    $author = $_POST[$configs['formPostAuthor']];
    $title = $_POST[$configs['formPostTitle']];
    $body = $_POST[$configs['formPostBody']];
    $currentTime = strftime("%a %F %T");

    // Characters to be avoided
    // TODO: rewrite sanatization with blacklist tag and attribute.
    $author = trim(str_replace(["\n", "\r", "\t"], ' ', $author));
    $title = str_replace(["\n", "\r", "\t"], ' ', $title);
    $title = trim(str_replace("\022", "'", $title));
    $body = str_replace("<", "&lt;", $body);
    $body = str_replace(">", "&gt;", $body);
    $body = trim(str_replace("\r\n", "\n", $body)); // use UNIX linebreak

    // validate the form
    $errors = array();
    if (strlen($author) > 8) {
        $errors[$configs['formPostAuthor']][] = 'Name too long';
    }
    if ($author == '') {
        $errors[$configs['formPostAuthor']][] = 'Please enter your name';
    }
    if ($title == '') {
        $errors[$configs['formPostTitle']][] = 'Please enter a subject';
    }
    if ($body == '') {
        $errors[$configs['formPostBody']][] = 'Please enter post content';
    }

    // display error message with the originally filled form
    if (!empty($errors)) {
        $post = new Post($title, $body);
        $post->author = $author;
        echo $forum->template->render(
            'postForm.twig',
            [
                'pageClass' => 'page-form',
                'errors' => $errors,
                'configs' => $configs,
                'action' => $action,
                'postID' => $postID,
                'post' => $post,
            ]
        );
        return;
    }

    // set author to cookies
    if ($author <> '') {
        setcookie('forumName', $author, mktime(0, 0, 0, 1, 1, 2038), "/");
    }

    $lock = $forum->getLock();

    switch ($action) {
        case 'add':
            $nextID = $forum->getCount() + 1;
            $post = new Post(
                $title,
                $body,
                ['author' => $author, 'time' => $currentTime]
            );
            $forum->writePost($nextID, $post);
            $forum->appendIndex(
                new PostSummary(
                    $nextID,
                    0,
                    $title,
                    $author,
                    $currentTime
                ),
                null
            );
            $forum->incCount();
            $lock->unlock();
            header('Refresh: 0; URL=' . $forum->linkTo('forum'));
            echo $forum->template->render(
                'base.twig',
                ['configs' => $configs]
            );
            break;
        case 'reply':
            $parentID = $postID;
            $nextID = $forum->getCount() + 1;
            $post = new Post(
                $title,
                $body,
                ['author' => $author, 'time' => $currentTime]
            );
            $forum->writePost($nextID, $post);
            $forum->appendIndex(
                new PostSummary(
                    $nextID,
                    0,
                    $title,
                    $author,
                    $currentTime
                ),
                $parentID
            );
            $forum->incCount();
            $lock->unlock();
            header('Refresh: 0; URL=' . $forum->linkTo('post', $parentID, 'back'));
            echo $forum->template->render(
                'base.twig',
                ['configs' => $configs]
            );
            break;
        case 'edit':
            $existingPost = $forum->readPost($postID);
            // check if the editor is the original author
            if (!$forum->isAdmin($author) && ($existingPost->header['author'] !== $author)) {
                throw new Exception('you are not the original author of this post.');
            }

            // inherit header by default
            $header = $existingPost->header;
            if ($forum->isAdmin($author)) {
                // admin can override user name
                $header['author'] = $author;
            }

            // generate new post
            $post = new Post(
                $title,
                $body,
                $header
            );
            $forum->writePost($postID, $post);
            $lock->unlock();
            header('Refresh: 0; URL=' . $forum->linkTo('post', $postID));
            echo $forum->template->render(
                'base.twig',
                ['configs' => $configs]
            );
            break;
    }
};
$router->addRoute('POST', '/post/{postID:\d+}/{action:edit|reply}', $savePost);
$router->addRoute('POST', '/post/{action:add}', $savePost);

$router->addRoute(
    'GET',
    '/forum[/[{page:\d+}]]',
    function ($vars, $forum) use ($configs) {

        $page = $vars['page'] ?? 0;
        $forum->log("forum?" . $page);

        $lock = $forum->getLock();
        $index = Utils::chainWrappers(new Paged($page, $configs['postPerPage']))->wrap($forum->getIndex());
        $contents = $forum->template->render(
            'forum.twig',
            [
                'configs' => $configs,
                'page' => $page,
                'topLinks' => [
                    ['text' => '發言', 'href' => $forum->linkTo('post', null, 'add')],
                    ['text' => '上一頁', 'href' => $forum->linkTo('forum', (($page > $configs['postPerPage']) ? $page - $configs['postPerPage'] : 0))],
                    #['text' => '下一頁', 'href' => $forum->linkTo('forum', ($page + $configs['postPerPage']))],
                    ['text' => '首頁', 'href' => $forum->linkTo('forum')],
                    ['text' => '主頁', 'href' => '/'],
                ],
                'bottomLinks' => [
                    ['text' => '發言', 'href' => $forum->linkTo('post', null, 'add')],
                    #['text' => '上一頁', 'href' => $forum->linkTo('forum', (($page > $configs['postPerPage']) ? $page - $configs['postPerPage'] : 0))],
                    ['text' => '下一頁', 'href' => $forum->linkTo('forum', ($page + $configs['postPerPage']))],
                    ['text' => '首頁', 'href' => $forum->linkTo('forum')],
                    ['text' => '主頁', 'href' => '/'],
                ],
                'postSummaries' => $index,
            ]
        );
        $lock->unlock();
        echo $contents;
    }
);

$router->addRoute(
    'GET',
    '/user/{username}[/[{page:\d+}]]',
    function ($vars, $forum) use ($configs) {
        $page = $vars['page'] ?? 0;
        $forum->log("forum?" . $page);

        $lock = $forum->getLock();

        // TODO: filter by username
        // TODO: remove level from display
        $index = Utils::chainWrappers(
            new Map(
                function ($postSummary) {
                    $postSummary->level = 0;
                    return $postSummary;
                }
            ),
            new Filtered(
                function ($postSummary) use ($vars) {
                    return ($postSummary->author == $vars['username']);
                }
            ),
            new Paged($page, $configs['postPerPage'])
        )->wrap($forum->getIndex());
        $user_path = 'user/' . $vars['username'];
        $contents = $forum->template->render(
            'forum.twig',
            [
                'configs' => $configs,
                'page' => $page,
                'topLinks' => [
                    ['text' => '發言', 'href' => $forum->linkTo('post', null, 'add')],
                    ['text' => '上一頁', 'href' => $forum->linkTo($user_path, (($page > $configs['postPerPage']) ? $page - $configs['postPerPage'] : 0))],
                    #['text' => '下一頁', 'href' => $forum->linkTo($user_path, ($page + $configs['postPerPage']))],
                    ['text' => '首頁', 'href' => $forum->linkTo('forum')],
                    ['text' => '主頁', 'href' => '/'],
                ],
                'bottomLinks' => [
                    ['text' => '發言', 'href' => $forum->linkTo('post', null, 'add')],
                    #['text' => '上一頁', 'href' => $forum->linkTo($user_path, (($page > $configs['postPerPage']) ? $page - $configs['postPerPage'] : 0))],
                    ['text' => '下一頁', 'href' => $forum->linkTo($user_path, ($page + $configs['postPerPage']))],
                    ['text' => '首頁', 'href' => $forum->linkTo('forum')],
                    ['text' => '主頁', 'href' => '/'],
                ],
                'postSummaries' => $index,
            ]
        );
        $lock->unlock();
        echo $contents;
    }
);


$router->addRoute(
    'GET',
    '/rss',
    function ($vars, $forum) use ($configs) {
        $mode = $_GET['mode'] ?? 'post';

        // read post summaries as reference to the mode
        $lock = $forum->getLock();

        // an array of PostSummary with additional attributes:
        // post (Post object) and rssBody (string).
        $rssPosts = [];

        switch ($mode) {
            case 'thread':
            case 'threads':
                $postSummaries = Utils::chainWrappers(
                    new Filtered(
                        function ($postSummary) {
                            return ($postSummary->level == 0);
                        }
                    ),
                    new Paged(0, $configs['rssPostLimit'])
                )->wrap($forum->getIndex());

                // read post index and post contents
                foreach ($postSummaries as $postSummary) {
                    $postSummary->post = $forum->readPost($postSummary->id);
                    $postSummary->rssBody = $forum->template->render(
                        'rssPostBody.twig',
                        [
                            'configs' => $configs,
                            'sitePath' => 'http://localhost:8080',
                            'postSummary' => $postSummary,
                            'post' => $postSummary->post,
                            't' => [
                                'author' => '作者',
                                'time' => '時間',
                                'reply' => '回覆',
                                'forumIndex' => '回論壇',
                            ],
                        ]
                    );
                    $rssPosts[] = $postSummary;
                }
                break;
            case 'post':
                $posts = Utils::chainWrappers(
                    new Paged(0, $configs['rssPostLimit'])
                )->wrap($forum->getPosts());

                // parse post as postSummary and generate rssPosts
                foreach ($posts as $post) {
                    $postSummary = PostSummary::fromPost($post);
                    $postSummary->post = $post;
                    $postSummary->rssBody = $forum->template->render(
                        'rssPostBody.twig',
                        [
                            'configs' => $configs,
                            'sitePath' => 'http://localhost:8080',
                            'postSummary' => $postSummary,
                            'post' => $postSummary->post,
                            't' => [
                                'author' => '作者',
                                'time' => '時間',
                                'reply' => '回覆',
                                'forumIndex' => '回論壇',
                            ],
                        ]
                    );
                    $rssPosts[] = $postSummary;
                }
                break;
            default:
                throw new Exception(
                    sprintf('mode "%s" is not supported', htmlspecialchars($mode))
                );
        }
        unset($postSummaries);
        $lock->unlock();

        // render rss
        header("Content-Type: text/xml; charset=utf-8");
        echo $forum->template->render(
            'rss.twig',
            [
                'configs' => $configs,
                'link' => $forum->linkTo('forum', null, null, ['absolute' => true]),
                'language' => 'zh-hk',
                'pubDate' => date('D, d M Y H:i:s ', time()),
                'lastBuildDate' => date('D, d M Y H:i:s ', time()),
                'generator' => 'CrudeForum',
                'managingEditor' => 'stupidsing@yahoo.com (Y W Sing)',
                'webMaster' => 'stupidsing@yahoo.com (Y W Sing)',
                'posts' => $rssPosts,
            ]
        );
    }
);
