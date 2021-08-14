<?php
/**
 * jsmin.php - PHP implementation of Douglas Crockford's JSMin.
 *
 * This is pretty much a direct port of jsmin.c to PHP with just a few
 * PHP-specific performance tweaks. Also, whereas jsmin.c reads from stdin and
 * outputs to stdout, this library accepts a string as input and returns another
 * string as output.
 *
 * PHP 5 or higher is required.
 *
 * Permission is hereby granted to use this version of the library under the
 * same terms as jsmin.c, which has the following license:
 *
 * --
 * Copyright (c) 2002 Douglas Crockford  (www.crockford.com)
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy of
 * this software and associated documentation files (the "Software"), to deal in
 * the Software without restriction, including without limitation the rights to
 * use, copy, modify, merge, publish, distribute, sublicense, and/or sell copies
 * of the Software, and to permit persons to whom the Software is furnished to do
 * so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in all
 * copies or substantial portions of the Software.
 *
 * The Software shall be used for Good, not Evil.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
 * SOFTWARE.
 * --
 *
 * @author Ryan Grove <ryan@wonko.com>
 * @copyright 2002 Douglas Crockford <douglas@crockford.com> (jsmin.c)
 * @copyright 2007 Ryan Grove <ryan@wonko.com> (PHP port)
 * @license http://opensource.org/licenses/mit-license.php MIT License
 *
 * @version 1.0.0 (2007-05-04)
 *
 * @see http://code.google.com/p/jsmin-php/
 */
define('ORD_LF', 10);
define('ORD_SPACE', 32);

class JSMin
{
    public $a = '';
    public $b = '';
    public $input = '';
    public $inputIndex = 0;
    public $inputLength = 0;
    public $lookAhead = null;
    public $output = [];

    // -- Public Static Methods --------------------------------------------------

    public function minify($js)
    {
        $jsmin = new JSMin($js);

        return $jsmin->jsminify();
    }

    // -- Public Instance Methods ------------------------------------------------

    public function __construct($input)
    {
        $this->input = $input;
        $this->inputLength = strlen($input);
    }

    // -- Protected Instance Methods ---------------------------------------------

    public function action($d)
    {
        switch ($d) {
            case 1:
                  $this->output[] = $this->a;

                  // no break
            case 2:
                  $this->a = $this->b;

                if ("'" === $this->a || '"' === $this->a) {
                    for (;;) {
                        $this->output[] = $this->a;
                        $this->a = $this->get();

                        if ($this->a === $this->b) {
                            break;
                        }

                        if (ord($this->a) <= ORD_LF) {
                            exit('Unterminated string literal.');
                        }

                        if ('\\' === $this->a) {
                            $this->output[] = $this->a;
                            $this->a = $this->get();
                        }
                    }
                }

                // no break
            case 3:
                  $this->b = $this->next();

                if ('/' === $this->b && (
                      '(' === $this->a || ',' === $this->a || '=' === $this->a ||
                      ':' === $this->a || '[' === $this->a || '!' === $this->a ||
                      '&' === $this->a || '|' === $this->a || '?' === $this->a)) {
                    $this->output[] = $this->a;
                    $this->output[] = $this->b;

                    for (;;) {
                        $this->a = $this->get();

                        if ('/' === $this->a) {
                            break;
                        } elseif ('\\' === $this->a) {
                            $this->output[] = $this->a;
                            $this->a = $this->get();
                        } elseif (ord($this->a) <= ORD_LF) {
                            exit('Unterminated regular expression literal.');
                        }

                        $this->output[] = $this->a;
                    }

                    $this->b = $this->next();
                }
        }
    }

    public function get()
    {
        $c = $this->lookAhead;
        $this->lookAhead = null;

        if (null === $c) {
            if ($this->inputIndex < $this->inputLength) {
                $c = $this->input[$this->inputIndex];
                ++$this->inputIndex;
            } else {
                $c = null;
            }
        }

        if ("\r" === $c) {
            return "\n";
        }

        if (null === $c || "\n" === $c || ord($c) >= ORD_SPACE) {
            return $c;
        }

        return ' ';
    }

    public function isAlphaNum($c)
    {
        return ord($c) > 126 || '\\' === $c || 1 === preg_match('/^[\w\$]$/', $c);
    }

    public function jsminify()
    {
        $this->a = "\n";
        $this->action(3);

        while (null !== $this->a) {
            switch ($this->a) {
                case ' ':
                    if ($this->isAlphaNum($this->b)) {
                        $this->action(1);
                    } else {
                        $this->action(2);
                    }
                    break;

                case "\n":
                    switch ($this->b) {
                        case '{':
                        case '[':
                        case '(':
                        case '+':
                        case '-':
                            $this->action(1);
                            break;

                        case ' ':
                            $this->action(3);
                            break;

                        default:
                            if ($this->isAlphaNum($this->b)) {
                                $this->action(1);
                            } else {
                                $this->action(2);
                            }
                    }
                    break;

                default:
                    switch ($this->b) {
                        case ' ':
                            if ($this->isAlphaNum($this->a)) {
                                $this->action(1);
                                break;
                            }

                            $this->action(3);
                            break;

                        case "\n":
                            switch ($this->a) {
                                case '}':
                                case ']':
                                case ')':
                                case '+':
                                case '-':
                                case '"':
                                case "'":
                                    $this->action(1);
                                    break;

                                default:
                                    if ($this->isAlphaNum($this->a)) {
                                        $this->action(1);
                                    } else {
                                        $this->action(3);
                                    }
                            }
                            break;

                        default:
                            $this->action(1);
                            break;
                    }
            }
        }

        return implode('', $this->output);
    }

    public function next()
    {
        $c = $this->get();

        if ('/' === $c) {
            switch ($this->peek()) {
                case '/':
                    for (;;) {
                        $c = $this->get();

                        if (ord($c) <= ORD_LF) {
                            return $c;
                        }
                    }

                    // no break
                case '*':
                          $this->get();

                    for (;;) {
                        switch ($this->get()) {
                            case '*':
                                if ('/' === $this->peek()) {
                                    $this->get();

                                    return ' ';
                                }
                                break;

                            case null:
                                exit('Unterminated comment.');
                        }
                    }

                    // no break
                default:
                    return $c;
            }
        }

        return $c;
    }

    public function peek()
    {
        $this->lookAhead = $this->get();

        return $this->lookAhead;
    }
}
