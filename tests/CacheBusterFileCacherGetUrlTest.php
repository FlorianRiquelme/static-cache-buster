<?php

declare(strict_types=1);

namespace FRoepstorf\StaticCacheBuster\Tests;

use FRoepstorf\StaticCacheBuster\StaticCaching\CacheBusterFileCacher;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Http\Request;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Statamic\StaticCaching\Cachers\Writer;

class CacheBusterFileCacherGetUrlTest extends TestCase
{
    /**
     * @param  array<string, mixed>  $config
     */
    private function makeCacher(array $config): CacheBusterFileCacher
    {
        $writer = $this->createStub(Writer::class);
        $cache = $this->createStub(Repository::class);

        return new class($writer, $cache, $config) extends CacheBusterFileCacher
        {
            public function isExcluded($url)
            {
                return false;
            }
        };
    }

    #[Test]
    public function it_strips_disallowed_query_strings_from_the_cache_key(): void
    {
        $cacheBusterFileCacher = $this->makeCacher([
            'disallowed_query_strings' => ['gclid', 'utm_source'],
        ]);

        $request = Request::create('https://example.com/page?gclid=abc&utm_source=newsletter&keep=yes');

        $this->assertSame(
            'https://example.com/page?keep=yes',
            $cacheBusterFileCacher->getUrl($request)
        );
    }

    #[Test]
    public function it_returns_a_path_only_url_when_every_query_param_is_disallowed(): void
    {
        $cacheBusterFileCacher = $this->makeCacher([
            'disallowed_query_strings' => ['gclid', 'fbclid'],
        ]);

        $request = Request::create('https://example.com/page?gclid=abc&fbclid=def');

        $this->assertSame('https://example.com/page', $cacheBusterFileCacher->getUrl($request));
    }

    #[Test]
    public function it_passes_through_when_no_disallowed_param_is_present(): void
    {
        $cacheBusterFileCacher = $this->makeCacher([
            'disallowed_query_strings' => ['gclid'],
        ]);

        $request = Request::create('https://example.com/page?step=2&author=jane');

        $this->assertSame(
            'https://example.com/page?step=2&author=jane',
            $cacheBusterFileCacher->getUrl($request)
        );
    }

    #[Test]
    public function it_preserves_the_original_query_order_after_filtering(): void
    {
        $cacheBusterFileCacher = $this->makeCacher([
            'disallowed_query_strings' => ['gclid'],
        ]);

        $request = Request::create('https://example.com/page?step=2&gclid=x&author=jane');

        $this->assertSame(
            'https://example.com/page?step=2&author=jane',
            $cacheBusterFileCacher->getUrl($request)
        );
    }

    #[Test]
    public function it_returns_the_path_only_url_when_ignore_query_strings_is_enabled(): void
    {
        $cacheBusterFileCacher = $this->makeCacher([
            'ignore_query_strings'     => true,
            'disallowed_query_strings' => ['gclid'],
        ]);

        $request = Request::create('https://example.com/page?gclid=abc&keep=yes');

        $this->assertSame('https://example.com/page', $cacheBusterFileCacher->getUrl($request));
    }

    #[Test]
    public function it_is_a_noop_when_disallowed_list_is_empty(): void
    {
        $cacheBusterFileCacher = $this->makeCacher([
            'disallowed_query_strings' => [],
        ]);

        $request = Request::create('https://example.com/page?gclid=abc&keep=yes');

        $this->assertSame(
            'https://example.com/page?gclid=abc&keep=yes',
            $cacheBusterFileCacher->getUrl($request)
        );
    }
}
