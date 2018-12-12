<?php

/**
 * Class for post content objects.
 *
 * PHP Version 7.1
 *
 * @category File
 * @package  CrudeForum\CrudeForum
 * @author   Koala Yeung <koalay@gmail.com>
 * @license  https://opensource.org/licenses/MIT MIT License
 * @link     https://github.com/crude-forum/crude-forum/blob/master/app/Post.php
 * Source Code
 */
namespace CrudeForum\CrudeForum;

use \Exception;
use \InvalidArgumentException;
use \CrudeForum\CrudeForum\Exception\PostInvalid;

define(
    'POST_HEADER_NAMES',
    [
        'author' => '來自',
        'time' => '時間',
    ]
);
define('POST_HEADER_NAMES_LOOPUP', array_flip(POST_HEADER_NAMES));

/**
 * Class for post content objects.
 *
 * @category Class
 * @package  CrudeForum\CrudeForum
 * @author   Koala Yeung <koalay@gmail.com>
 * @license  https://opensource.org/licenses/MIT MIT License
 * @link     https://github.com/crude-forum/crude-forum/blob/master/app/Post.php
 * Source Code
 */
class Post
{
    public $id = null;
    public $title = '';
    public $body = '';
    public $header = []; // author, time information (meta data)
    public $noAutoBr = false;

    /**
     * Constructor of a Post.
     *
     * @param string  $title    Title of the post.
     * @param string  $body     Body of the post.
     * @param array   $header   Meta information of the post.
     * @param boolean $noAutoBr Should the post be processed with auto br on browser.
     */
    public function __construct(string $title = '', string $body = '', array $header = [], bool $noAutoBr = false)
    {
        $this->title = (string) $title;
        $this->body = (string) $body;
        $this->header = (array) $header;
        $this->noAutoBr = (bool) $noAutoBr;
    }

    /**
     * Look up the mahcine name of a header field by its human-readable name.
     *
     * @param string $humanName Human-readable name of a header field.
     *
     * @return string
     */
    public static function headerName(string $humanName): string
    {
        return POST_HEADER_NAMES_LOOPUP[$humanName] ?? $humanName;
    }

    /**
     * Look up the human-readable name of a header field by its machine name.
     *
     * @param string $machineName Machine name of a header field.
     *
     * @return string
     */
    public static function headerHumanName(string $machineName): string
    {
        return POST_HEADER_NAMES[$machineName] ?? $machineName;
    }

    /**
     * Create a Post object from its plain text storage format.
     *
     * @param string $text Plain text storage format of a Post.
     *
     * @return Post|null The Post of no error, or null;
     */
    public static function fromText(string $text): ?Post
    {
        if (empty($text)) {
            throw new InvalidArgumentException('expected non-empty string as parameter');
        }
        $lines = explode("\n", str_replace("\r\n", "\n", $text), 3);
        $size = sizeof($lines);
        if ($size === 1) {
            throw new PostInvalid('the post is misformatted');
            return null;
        } else if ($size === 2) {
            throw new PostInvalid('the post is missing a proper body');
            return null;
        }

        // parse header
        $lineNum = -1;
        $title = $lines[0];
        $header = [];
        $bodyLines = explode("\n", $lines[2]);
        foreach ($bodyLines as $lineNum => $line) {
            // identify any colon (either ASCII unicode, in the line
            if (preg_match("/^(.+?)(:|\x{FE30}|\x{FF1A})(.+?)$/u", $line, $matches)) {
                $header[Post::headerName($matches[1])] = trim($matches[3]);
            } else if (empty(trim($line))) {
                // header ended
                break;
            } else {
                // throw error here
                throw new PostInvalid('the post header is misformatted');
                return null;
            }
        }
        if ($lineNum < 0) {
            throw new PostInvalid('the post body has no header');
        }

        $body = implode("\n", array_splice($bodyLines, $lineNum + 1));
        return new Post($title, $body, $header);
    }

    /**
     * Generate a reply Post from the parent object. Or generate
     * an empty post if $parent is null;
     *
     * @param Post|null $parent The parent post. If no parent post, use null.
     *
     * @return Post
     */
    public static function replyFor(?Post $parent): Post
    {
        if ($parent === null) {
            return new Post();
        }
        if (empty($parent->title) && empty($parent->body)) {
            return new Post();
        }

        // generate reply post
        $title = (strpos($parent->title, 'Re: ') === 0) ?
            $parent->title : 'Re: ' . $parent->title;

        $author = $parent->header['author'] ?? '';
        $authorHeaderName = Post::headerHumanName('author');
        $time = $parent->header['time'] ?? '';
        $timeHeaderName = Post::headerHumanName('time');

        // generate quote to replying post
        $quoteLine = function ($line) {
            return "| $line";
        };
        $header = implode(
            "\n",
            array_map(
                $quoteLine,
                [
                    "{$authorHeaderName}：{$author}",
                    "{$timeHeaderName}：{$time}",
                ]
            )
        );
        $mainBody = $parent->filterBody($quoteLine);
        $body = "{$header}\n|\n{$mainBody}\n\n";

        return new Post($title, $body);
    }

    /**
     * Return the title as HTML safe string.
     *
     * @return string
     */
    public function safeTitle(): string
    {
        return htmlspecialchars($this->title);
    }

    /**
     * Return an array of header it the storage format
     * (i.e. human-readable name as key).
     *
     * @return array
     */
    public function storageHeader(): array
    {
        // encode all header values
        $header = $this->header;
        return array_map(
            function ($key) use ($header) {
                $name = Post::headerHumanName($key);
                $value = $header[$key];
                return "{$name}：{$value}";
            },
            array_keys($header)
        );
    }

    /**
     * Return a visitor suitable version of header in HTML safe string.
     *
     * @return string
     */
    public function safeHeader(): string
    {
        // display limited header value here for public eyes
        $author = htmlspecialchars($this->header['author'] ?? '');
        $authorHeaderName = htmlspecialchars(Post::headerHumanName('author'));
        $time = htmlspecialchars($this->header['time'] ?? '');
        $timeHeaderName = htmlspecialchars(Post::headerHumanName('time'));
        return "{$authorHeaderName}：{$author}\n{$timeHeaderName}：{$time}\n";
    }

    /**
     * Return a visitor suitable version of header for HTML display.
     *
     * @return string
     */
    public function htmlHeader(): string
    {
        return nl2br($this->safeHeader(), false);
    }

    /**
     * Filtering lines in body with the given line callback,
     * implode the filtered lines as a string and return.
     *
     * @param callable $lineCallback Callback function to apply to
     *                               every line of the body.
     *
     * @return void
     */
    public function filterBody(callable $lineCallback)
    {
        $lines = array_map(
            $lineCallback,
            explode("\n", trim($this->body))
        );
        return implode("\n", $lines);
    }

    /**
     * Return an HTML safe version of body.
     *
     * @return string
     */
    public function safeBody(): string
    {
        return htmlspecialchars($this->body);
    }
}
