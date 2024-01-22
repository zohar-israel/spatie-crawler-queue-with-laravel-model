<?php

namespace App\Observers\Crawler;

use App\Models\CrawlerQueue;
use Psr\Http\Message\UriInterface;
use Psr\Http\Message\ResponseInterface;
use Spatie\Crawler\CrawlObservers\CrawlObserver as SpatieCrawlObserver;
use GuzzleHttp\Exception\RequestException;
use DOMDocument;

class ConsoleObserver extends SpatieCrawlObserver
{

    public function __construct(\Illuminate\Console\Command $console)
    {
        $this->console = $console;
    }

    /**
     * @param UriInterface $url
     */
    public function willCrawl(UriInterface $url): void
    {
        $this->console->comment("Found: {$url}");
    }

    /**
     * Called when the crawler has crawled the given url successfully.
     *
     * @param UriInterface $url
     * @param ResponseInterface $response
     * @param UriInterface|null $foundOnUrl
     */
    public function crawled(UriInterface $url, ResponseInterface $response, ?UriInterface $foundOnUrl = NULL): void
    {
        $this->console->total_crawled++;

        $item = CrawlerQueue::onlyTrashed()->url($url)->first();
        // if ($item->count()) { 

        $doc = new DOMDocument();
        @$doc->loadHTML($response->getBody());
        //# save HTML 
        $content = $doc->saveHTML();
        //# convert encoding
        $content1 = mb_convert_encoding($content,'UTF-8',mb_detect_encoding($content,'UTF-8, ISO-8859-1',true));
        //# strip all javascript
        $content2 = preg_replace('/<script\b[^>]*>(.*?)<\/script>/is', '', $content1);
        //# strip all style
        $content3 = preg_replace('/<style\b[^>]*>(.*?)<\/style>/is', '', $content2);
        //# strip tags
        $content4 = str_replace('<',' <',$content3);
        $content5 = strip_tags($content4);
        $content6 = str_replace( '  ', ' ', $content5 );
        //# strip white spaces and line breaks
        $content7 = preg_replace('/\s+/S', " ", $content6);
        //# html entity decode - ö was shown as &ouml;
        $text = html_entity_decode($content7);
        
        $item->html = $content1;
        $item->text = $text;

        $item->save();

        $this->console->info("Crawled: ({$this->console->total_crawled}) {$url} ({$foundOnUrl})");
    }

    /**
     * Called when the crawler had a problem crawling the given url.
     *
     * @param UriInterface $url
     * @param RequestException $requestException
     * @param UriInterface|null $foundOnUrl
     */
    public function crawlFailed(UriInterface $url, RequestException $requestException, ?UriInterface $foundOnUrl = NULL): void
    {
        $this->console->error("Fail: {$url}. {$requestException->getMessage()}");
    }

    /**
     * Called when the crawl has ended.
     */
    public function finishedCrawling(): void
    {
        $this->console->info('Crawler: Finished');
    }
}
