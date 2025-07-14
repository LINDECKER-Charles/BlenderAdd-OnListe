<?php

namespace App\Service\Collection;

use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\RateLimiter\RateLimit;

class CollectionRateLimiter
{
    private RateLimiterFactory $collectionLimiter;

    public function __construct(RateLimiterFactory $collectionLimiter)
    {
        $this->collectionLimiter = $collectionLimiter;
    }

    public function consume(Request $request): RateLimit
    {
        $limiter = $this->collectionLimiter->create($request->getClientIp());
        return $limiter->consume();
    }

    public function getErrorMessage(RateLimit $limit): string
    {
        $retryAfter = $limit->getRetryAfter();
        $seconds = $retryAfter ? $retryAfter->getTimestamp() - time() : 60;
        $minutes = ceil($seconds / 60);

        return "Trop de tentatives. Réessayez dans environ $minutes minute" . ($minutes > 1 ? 's' : '') . ".";
    }
}
