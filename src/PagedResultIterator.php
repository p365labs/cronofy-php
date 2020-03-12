<?php

declare(strict_types=1);

namespace Cronofy;

class PagedResultIterator implements \IteratorAggregate
{
    /**
     * @var Cronofy
     */
    private $cronofy;

    /**
     * @var string
     */
    private $itemsKey;

    /**
     * @var array
     */
    private $authHeaders;

    /**
     * @var string
     */
    private $url;

    /**
     * @var string
     */
    private $urlParams;

    /**
     * @var mixed
     */
    private $firstPage;

    /**
     * PagedResultIterator constructor.
     *
     * @param Cronofy $cronofy
     * @param string  $itemsKey
     * @param array   $authHeaders
     * @param string  $url
     * @param string  $urlParams
     */
    public function __construct(Cronofy $cronofy, string $itemsKey, array $authHeaders, string $url, string $urlParams)
    {
        $this->cronofy = $cronofy;
        $this->itemsKey = $itemsKey;
        $this->authHeaders = $authHeaders;
        $this->url = $url;
        $this->urlParams = $urlParams;
        $this->firstPage = $this->getPage($url, $urlParams);
    }

    /**
     * @return \Generator
     */
    public function each()
    {
        $page = $this->firstPage;
        $itemsCount = \count($page[$this->itemsKey]);

        for ($i = 0; $i < $itemsCount; ++$i) {
            yield $page[$this->itemsKey][$i];
        }

        while (isset($page['pages']['next_page'])) {
            $page = $this->getPage($page['pages']['next_page']);
            $itemsCount = \count($page[$this->itemsKey]);

            for ($i = 0; $i < $itemsCount; ++$i) {
                yield $page[$this->itemsKey][$i];
            }
        }
    }

    /**
     * @return \Generator|\Traversable
     */
    public function getIterator()
    {
        return $this->each();
    }

    /**
     * @param string $url
     * @param string $urlParams
     *
     * @throws Exception\CronofyException
     *
     * @return mixed
     */
    private function getPage(string $url, string $urlParams = '')
    {
        list($result, $status_code) = $this->cronofy->httpClient->getPage($url, $this->authHeaders, $urlParams);

        return $this->cronofy->handleResponse($result, $status_code);
    }
}
