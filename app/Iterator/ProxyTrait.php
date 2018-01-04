<?php

/**
 * Trait implementation of \Iterator
 *
 * PHP Version 7.1
 *
 * @category File
 * @package  CrudeForum\CrudeForum\Iterator
 * @author   Koala Yeung <koalay@gmail.com>
 * @license  https://opensource.org/licenses/MIT MIT License
 * @link     https://github.com/crude-forum/crude-forum/blob/master/app/Iterator/Wrapper.php
 * Source Code
 */
namespace CrudeForum\CrudeForum\Iterator;
use \Iterator;

 /**
  * Trait implementation of \Iterator
  *
  * @category Trait
  * @package  CrudeForum\CrudeForum\Iterator
  * @author   Koala Yeung <koalay@gmail.com>
  * @license  https://opensource.org/licenses/MIT MIT License
  * @link     https://github.com/crude-forum/crude-forum/blob/master/app/Iterator/Wrapper.php
  * Source Code
  */
trait ProxyTrait
{

    private $_iter = null;

    /**
     * Class constructor.
     *
     * @param \Iterator $iter inner iterator.
     *
     * @return void
     */
    public function __construct(Iterator $iter)
    {
        $this->_iter = $iter;
    }

    /**
     * Class destructor.
     *
     * @return void
     */
    public function __destruct()
    {
        unset($this->_iter);
    }

    /**
     * Method to access inner iterator
     *
     * @return Iterator The proxyed Iterator.
     */
    public function &iter(): Iterator
    {
        return $this->_iter;
    }

    /**
     * Pass on current() to inner iterator
     *
     * @return void
     */
    public function current()
    {
        return $this->_iter->current();
    }

    /**
     * Pass on key() to inner iterator
     *
     * @return void
     */
    public function key()
    {
        return $this->_iter->key();
    }

    /**
     * Pass on next() to inner iterator
     *
     * @return void
     */
    public function next()
    {
        return $this->_iter->next();
    }

    /**
     * Pass on rewind() to inner iterator
     *
     * @return void
     */
    public function rewind()
    {
        return $this->_iter->rewind();
    }

    /**
     * Pass on valid() to inner iterator
     *
     * @return void
     */
    public function valid()
    {
        return $this->_iter->valid();
    }
}
