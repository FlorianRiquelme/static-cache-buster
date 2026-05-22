<?php

declare(strict_types=1);

namespace FRoepstorf\StaticCacheBuster\StaticCaching;

use Illuminate\Http\Request;
use Override;
use Statamic\StaticCaching\Cachers\FileCacher;

class CacheBusterFileCacher extends FileCacher
{
    /**
     * Check if a page has been cached, but bypass cache when the cache buster header is present.
     *
     * @return bool
     */
    #[Override]
    public function hasCachedPage(Request $request)
    {
        // Skip serving from cache when cache buster command is running
        if ($request->header('X-Statamic-Cache-Buster') === 'true') {
            return false;
        }

        return parent::hasCachedPage($request);
    }

    /**
     * FileCacher::getUrl() preserves the original query-string order so the file
     * name matches nginx's lookup, but it skips the deny-list filter that
     * AbstractCacher::getUrl() applies. That mismatch means every tracking
     * parameter combination becomes its own cache file. Re-apply the filter
     * here so disallowed_query_strings is honoured for the file driver too.
     */
    #[Override]
    public function getUrl(Request $request): string
    {
        /** @var string $url */
        $url = parent::getUrl($request);

        if ($this->config('ignore_query_strings', false)) {
            return $url;
        }

        $disallowed = $this->config('disallowed_query_strings');

        if (! is_array($disallowed) || $disallowed === []) {
            return $url;
        }

        $parts = parse_url($url);

        if ($parts === false || ! isset($parts['scheme'], $parts['host'], $parts['path'], $parts['query'])) {
            return $url;
        }

        parse_str($parts['query'], $query);
        $query = array_diff_key($query, array_flip($disallowed));

        $rebuilt = $parts['scheme'].'://'.$parts['host'].$parts['path'];

        if ($query !== []) {
            $rebuilt .= '?'.http_build_query($query, '', '&', PHP_QUERY_RFC3986);
        }

        return $rebuilt;
    }
}
