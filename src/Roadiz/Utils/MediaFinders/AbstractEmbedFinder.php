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
 * @file AbstractEmbedFinder.php
 * @author Ambroise Maupate <ambroise@rezo-zero.com>
 */
namespace RZ\Roadiz\Utils\MediaFinders;

use Doctrine\Common\Persistence\ObjectManager;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Stream\Stream;
use RZ\Roadiz\Core\Models\DocumentInterface;
use RZ\Roadiz\Utils\Document\AbstractDocumentFactory;
use RZ\Roadiz\Utils\Document\ViewOptionsResolver;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\HttpFoundation\Response;

/**
 * Abstract class to handle external media via their Json API.
 *
 * @package RZ\Roadiz\Utils\MediaFinders
 */
abstract class AbstractEmbedFinder
{
    protected $feed = null;
    /**
     * @var string
     */
    protected $embedId;
    protected $key;

    protected static $platform = 'abstract';

    /**
     * AbstractEmbedFinder constructor.
     * @param string $embedId
     */
    public function __construct($embedId = '')
    {
        $this->embedId = $this->validateEmbedId($embedId);
    }

    /**
     * Validate extern Id against platform naming policy.
     *
     * @param string $embedId
     * @return string
     */
    protected function validateEmbedId($embedId = "")
    {
        if (preg_match('#(?<id>[^\/^=^?]+)$#', $embedId, $matches)) {
            return $matches['id'];
        }
        throw new \InvalidArgumentException('embedId.is_not_valid');
    }

    /**
     * Tell if embed media exists after its API feed.
     *
     * @return boolean
     */
    public function exists()
    {
        if ($this->getFeed() !== false) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * Crawl and parse an API json feed for current embedID.
     *
     * @return array|bool
     */
    public function getFeed()
    {
        if (null === $this->feed) {
            $this->feed = $this->getMediaFeed();
            if (false !== $this->feed) {
                $this->feed = json_decode($this->feed, true);
            }
        }
        return $this->feed;
    }

    /**
     * Get embed media source URL.
     *
     * @param array $options
     *
     * @return string
     */
    public function getSource(array &$options = [])
    {
        $resolver = new ViewOptionsResolver();
        $options = $resolver->resolve($options);

        return "";
    }

    /**
     * Crawl an embed API to get a Json feed.
     *
     * @param string|bool $search
     *
     * @return string
     */
    abstract public function getMediaFeed($search = null);

    /**
     * Crawl an embed API to get a Json feed against a search query.
     *
     * @param string  $searchTerm
     * @param string  $author
     * @param integer $maxResults
     *
     * @return string
     */
    abstract public function getSearchFeed($searchTerm, $author, $maxResults = 15);

    /**
     * Compose an HTML iframe for viewing embed media.
     *
     * * width
     * * height
     * * title
     * * id
     * * class
     *
     * @param  array $options
     * @final
     * @return string
     */
    final public function getIFrame(array &$options = [])
    {
        $attributes = [];
        /*
         * getSource method will resolve all options for us.
         */
        $attributes['src'] = $this->getSource($options);

        if ($options['width'] > 0) {
            $attributes['width'] = $options['width'];

            /*
             * Default height is defined to 16:10
             */
            if ($options['height'] === 0) {
                $attributes['height'] = (int)(($options['width']*10)/16);
            }
        }

        if ($options['height'] > 0) {
            $attributes['height'] = $options['height'];
        }

        $attributes['title'] = $options['title'];
        $attributes['id'] = $options['id'];
        $attributes['class'] = $options['class'];
        $attributes['frameborder'] = "0";

        if ($options['fullscreen']) {
            $attributes['webkitAllowFullScreen'] = "1";
            $attributes['mozallowfullscreen'] = "1";
            $attributes['allowFullScreen'] = "1";
        }

        $attributes = array_filter($attributes);


        $htmlAttrs = [];
        foreach ($attributes as $key => $value) {
            if ($value == '') {
                $htmlAttrs[] = $key;
            } else {
                $htmlAttrs[] = $key.'="'.addslashes($value).'"';
            }
        }

        return '<iframe '.implode(' ', $htmlAttrs).'></iframe>';
    }

    /**
     * Create a Document from an embed media.
     *
     * Be careful, this method does not flush.
     *
     * @param ObjectManager $objectManager
     * @param AbstractDocumentFactory $documentFactory
     * @return DocumentInterface
     */
    public function createDocumentFromFeed(
        ObjectManager $objectManager,
        AbstractDocumentFactory $documentFactory
    ) {
        /** @var File $file */
        $file = $this->downloadThumbnail();

        if (!$this->exists() || null === $file) {
            throw new \RuntimeException('no.embed.document.found');
        }

        if ($this->documentExists($objectManager, $this->embedId, static::$platform)) {
            throw new \InvalidArgumentException('embed.document.already_exists');
        }

        $documentFactory->setFile($file);
        $document = $documentFactory->getDocument();

        if (null === $document) {
            throw new \RuntimeException('document.cannot_persist');
        }

        $document->setEmbedId($this->embedId);
        $document->setEmbedPlatform(static::$platform);

        /*
         * Create document metas
         * for each translation
         */
        $this->injectMetaInDocument($objectManager, $document);

        return $document;
    }

    /**
     * @param ObjectManager $objectManager
     * @param $embedId
     * @param $embedPlatform
     * @return bool
     */
    abstract protected function documentExists(ObjectManager $objectManager, $embedId, $embedPlatform);

    /**
     * Store additional information into Document.
     *
     * @param ObjectManager $objectManager
     * @param DocumentInterface $document
     * @return DocumentInterface
     */
    abstract protected function injectMetaInDocument(ObjectManager $objectManager, DocumentInterface $document);

    /**
     * Get media title from feed.
     *
     * @return string
     */
    abstract public function getMediaTitle();

    /**
     * Get media description from feed.
     *
     * @return string
     */
    abstract public function getMediaDescription();

    /**
     * Get media copyright from feed.
     *
     * @return string
     */
    abstract public function getMediaCopyright();

    /**
     * Get media thumbnail external URL from its feed.
     *
     * @return string
     */
    abstract public function getThumbnailURL();

    /**
     * Send a CURL request and get its string output.
     *
     * @param $url
     * @return \GuzzleHttp\Stream\StreamInterface|null
     * @throws \RuntimeException
     */
    public function downloadFeedFromAPI($url)
    {
        $client = new Client();
        $response = $client->get($url);

        if (Response::HTTP_OK == $response->getStatusCode()) {
            return $response->getBody();
        }

        throw new \RuntimeException($response->getReasonPhrase());
    }

    /**
     * Download a picture from the embed media platform
     * to get a thumbnail.
     *
     * @return File|null
     */
    public function downloadThumbnail()
    {
        $url = $this->getThumbnailURL();

        if (false !== $url && '' !== $url) {
            $pathinfo = basename($url);

            if ($pathinfo != "") {
                $thumbnailName = $this->embedId.'_'.$pathinfo;

                try {
                    $original = Stream::factory(fopen($url, 'r'));

                    $tmpFile = tempnam(sys_get_temp_dir(), $thumbnailName);
                    $handle = fopen($tmpFile, 'w');

                    $local = Stream::factory($handle);
                    $local->write($original->getContents());
                    $local->close();

                    $file = new File($tmpFile);

                    if ($file->isReadable() &&
                        filesize($file->getPathname()) > 0) {
                        return $file;
                    }
                } catch (RequestException $e) {
                    return null;
                }
            }
        }

        return null;
    }

    /**
     * Gets the value of key.
     *
     * Key is the access_token which could be asked to consume an API.
     * For example, for Youtube it must be your API server key. For Soundcloud
     * it should be you app client Id.
     *
     * @return string
     */
    public function getKey()
    {
        return $this->key;
    }

    /**
     * Sets the value of key.
     *
     * Key is the access_token which could be asked to consume an API.
     * For example, for Youtube it must be your API server key. For Soundcloud
     * it should be you app client Id.
     *
     * @param string $key the key
     *
     * @return self
     */
    public function setKey($key)
    {
        $this->key = $key;

        return $this;
    }
}
