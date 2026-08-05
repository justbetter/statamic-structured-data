<?php

namespace Justbetter\StatamicStructuredData\Tests\Unit\Http\Middleware;

use Illuminate\Contracts\Auth\Access\Authorizable;
use Illuminate\Http\Request;
use Justbetter\StatamicStructuredData\Http\Middleware\AuthorizeStructuredDataReports;
use Justbetter\StatamicStructuredData\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;

class AuthorizeStructuredDataReportsTest extends TestCase
{
    #[Test]
    public function it_allows_authorizable_users_with_permission(): void
    {
        $user = $this->mock(Authorizable::class);
        $user->shouldReceive('can')
            ->once()
            ->with('view structured data reports')
            ->andReturn(true);

        $request = Request::create('/cp/justbetter/structured-data/reports');
        $request->setUserResolver(fn () => $user);

        $middleware = new AuthorizeStructuredDataReports;
        $response = $middleware->handle($request, fn (): Response => response('ok'));

        $this->assertSame('ok', $response->getContent());
    }

    #[Test]
    public function it_denies_when_user_cannot(): void
    {
        $user = $this->mock(Authorizable::class);
        $user->shouldReceive('can')
            ->once()
            ->with('view structured data reports')
            ->andReturn(false);

        $request = Request::create('/cp/justbetter/structured-data/reports');
        $request->setUserResolver(fn () => $user);

        $this->expectException(HttpException::class);

        (new AuthorizeStructuredDataReports)->handle($request, fn (): Response => response('ok'));
    }

    #[Test]
    public function it_denies_when_there_is_no_user(): void
    {
        $request = Request::create('/cp/justbetter/structured-data/reports');
        $request->setUserResolver(fn () => null);

        $this->expectException(HttpException::class);

        (new AuthorizeStructuredDataReports)->handle($request, fn (): Response => response('ok'));
    }
}
