<?php

namespace App\Queues;

use App\Models\CrawlerQueue;
use Psr\Http\Message\UriInterface;
use Spatie\Crawler\CrawlQueues\CrawlQueue;
use Spatie\Crawler\CrawlUrl;
use Spatie\Crawler\Exceptions\InvalidUrl;
use Spatie\Crawler\Exceptions\UrlNotFoundByIndex;

class CrawlerCacheQueue implements CrawlQueue
{

    /**
     * Define expiry of cached URLs.
     *
     * @var int|null
     */
    protected mixed $ttl = NULL;
    protected mixed $site = "";

    /**
     * Defines an instance of the CacheQueue
     *
     * @param int|null $ttl
     */
    public function __construct($site, int $ttl = NULL)
    {
        $this->ttl = $ttl ?? config('crawler.cache.ttl', 86400); // one day
        $this->site = $site;
    }

    /**
     * Adds a new URL to the queue (and cache).
     *
     * @param CrawlUrl $crawlUrl
     * @return CrawlQueue
     */
    public function add(CrawlUrl $crawlUrl): CrawlQueue
    {
        if (!$this->has($crawlUrl)) {
            $crawlUrl->setId((string) $crawlUrl->url);

            $item = new CrawlerQueue;

            $item->url_class  = $crawlUrl;
            $item->site  = $this->site;
            $item->expires_at = $this->ttl;

            $item->save();
        }

        return $this;
    }

    /**
     * Marks the given URL as processed
     *
     * @param CrawlUrl $crawlUrl
     * @return void
     */
    public function markAsProcessed(CrawlUrl $crawlUrl): void
    {
        // @OBS deleted_at = soft delete = processado
        CrawlerQueue::where('site',$this->site)->url($crawlUrl)->delete();
    }

    public function getPendingUrl(): ?CrawlUrl
    {
        // Any URLs left?
        if ($this->hasPendingUrls()) {
            return  CrawlerQueue::where('site',$this->site)->first()->url_class;
            // $random = CrawlerQueue::inRandomOrder()->first();
            // return $random->url_class;
        }

        return NULL;
    }

    public function has(UriInterface|CrawlUrl|string $crawlUrl): bool
    {
        return (bool) CrawlerQueue::where('site',$this->site)->withTrashed()->url($crawlUrl)->count();
    }

    public function hasPendingUrls(): bool
    {
        return (bool) CrawlerQueue::where('site',$this->site)->count();
    }

    public function getUrlById($id): CrawlUrl
    {
        if (!$this->has($id)) {
            throw new UrlNotFoundByIndex("Crawl url {$id} not found in collection.");
        }
        $item = CrawlerQueue::withTrashed()->where('site',$this->site)->url($id)->first();

        return $item->url_class;
    }

    public function hasAlreadyBeenProcessed(CrawlUrl $crawlUrl): bool
    {
        $inQueue   = (bool) CrawlerQueue::where('site',$this->site)->url($crawlUrl)->count();
        $processed = (bool) CrawlerQueue::onlyTrashed()->where('site',$this->site)->url($crawlUrl)->count();

        if ($inQueue) {
            return FALSE;
        }

        if ($processed) {
            return TRUE;
        }

        return FALSE;

    }

    public function getProcessedUrlCount(): int
    {
        $processed = CrawlerQueue::onlyTrashed()->where('site',$this->site)->count();
        $pending   = CrawlerQueue::where('site',$this->site)->count();

        return $processed - $pending;
    }
}
