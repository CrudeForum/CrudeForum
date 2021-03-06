<?php

require __DIR__ . '/../../bootstrap.php';

use \CrudeForum\CrudeForum\Core;

$action = empty(trim($_SERVER['QUERY_STRING'])) ? 'add' : 'reply';
Core::bootstrap(
  $dispatcher,
  $forum,
  Core::routeQueryString('/post', $action)
);