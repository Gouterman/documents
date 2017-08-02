<?php
/**
 * Copyright (c) 2017. Ambroise Maupate and Julien Blanchet
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is furnished
 * to do so, subject to the following conditions:
 * The above copyright notice and this permission notice shall be included in all
 * copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS
 * OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL
 * THE AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS
 * IN THE SOFTWARE.
 *
 * Except as contained in this notice, the name of the ROADIZ shall not
 * be used in advertising or otherwise to promote the sale, use or other dealings
 * in this Software without prior written authorization from Ambroise Maupate and Julien Blanchet.
 *
 * @file FileAwareInterface.php
 * @author Ambroise Maupate <ambroise@rezo-zero.com>
 */
namespace RZ\Roadiz\Core\Models;

/**
 * Provide paths for file management.
 *
 * @package RZ\Roadiz\Core
 */
interface FileAwareInterface
{
    /**
     * @return string Return absolute path to public files folder.
     */
    public function getPublicFilesPath();

    /**
     * @return string Return relative path to public files folder.
     */
    public function getPublicFilesBasePath();

    /**
     * @return string Return absolute path to private files folder. Path must be protected.
     */
    public function getPrivateFilesPath();

    /**
     * @return string Return relative path to private files folder.
     */
    public function getPrivateFilesBasePath();

    /**
     * @return string Return absolute path to private font files folder. Path must be protected.
     */
    public function getFontsFilesPath();

    /**
     * @return string Return relative path to private font files folder.
     */
    public function getFontsFilesBasePath();
}
