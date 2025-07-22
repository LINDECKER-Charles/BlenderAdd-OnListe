<?php

namespace App\Tests\Service;

use App\Entity\User;
use App\Service\UserAccesChecker;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;

class UserAccesCheckerTest extends TestCase
{
    private function getChecker(User $user): UserAccesChecker
    {
        $token = $this->createMock(TokenInterface::class);
        $token->method('getUser')->willReturn($user);

        $tokenStorage = $this->createMock(TokenStorageInterface::class);
        $tokenStorage->method('getToken')->willReturn($token);

        $router = $this->createMock(RouterInterface::class);

        return new UserAccesChecker($router, $tokenStorage);
    }

    private function createUser(array $roles = [], bool $verified = true): User
    {
        $user = $this->createMock(User::class);
        $user->method('getRoles')->willReturn($roles);
        $user->method('isVerified')->willReturn($verified);
        return $user;
    }

    private function mockTokenStorage(User $user): TokenStorageInterface
    {
        $token = $this->createMock(TokenInterface::class);
        $token->method('getUser')->willReturn($user);

        $tokenStorage = $this->createMock(TokenStorageInterface::class);
        $tokenStorage->method('getToken')->willReturn($token);

        return $tokenStorage;
    }


    public function testIsConnectedReturnsTrue()
    {
        $user = $this->createUser();
        $checker = $this->getChecker($user);
        $this->assertTrue($checker->isConnected());
    }

    public function testIsVerifiedReturnsTrueWhenUserIsVerified()
    {
        $user = $this->createUser([], true);
        $checker = $this->getChecker($user);
        $this->assertTrue($checker->isVerified());
    }

    public function testIsVerifiedReturnsFalseWhenUserIsNotVerified()
    {
        $user = $this->createUser([], false);
        $checker = $this->getChecker($user);
        $this->assertFalse($checker->isVerified());
    }

    public function testHasRoleReturnsTrueForExistingRole()
    {
        $user = $this->createUser(['ADMIN']);
        $checker = $this->getChecker($user);
        $this->assertTrue($checker->hasRole($user, 'ADMIN'));
    }

    public function testIsAdmin()
    {
        $user = $this->createUser(['ADMIN']);
        $checker = $this->getChecker($user);
        $this->assertTrue($checker->isAdmin($user));
    }

    public function testIsModerator()
    {
        $user = $this->createUser(['MODO']);
        $checker = $this->getChecker($user);
        $this->assertTrue($checker->isModerator($user));
    }

    public function testIsStaffAsAdmin()
    {
        $user = $this->createUser(['ADMIN'], true);
        $checker = $this->getChecker($user);
        $this->assertTrue($checker->isStaff($user));
    }

    public function testIsStaffAsModerator()
    {
        $user = $this->createUser(['MODO'], true);
        $checker = $this->getChecker($user);
        $this->assertTrue($checker->isStaff($user));
    }

    public function testIsStaffReturnsFalseWhenNotVerified()
    {
        $user = $this->createUser(['MODO'], false);
        $checker = $this->getChecker($user);
        $this->assertFalse($checker->isStaff($user));
    }

    public function testIsBanned()
    {
        $user = $this->createUser(['BAN']);
        $checker = $this->getChecker($user);
        $this->assertTrue($checker->isBanned($user));
    }

    public function testIsLocked()
    {
        $user = $this->createUser(['LOCK']);
        $checker = $this->getChecker($user);
        $this->assertTrue($checker->isLocked($user));
    }

    public function testIsAllowedReturnsFalseWhenLocked()
    {
        $user = $this->createUser(['LOCK']);
        $checker = $this->getChecker($user);
        $this->assertFalse($checker->isAllowed($user));
    }

    public function testIsAllowedReturnsFalseWhenBanned()
    {
        $user = $this->createUser(['BAN']);
        $checker = $this->getChecker($user);
        $this->assertFalse($checker->isAllowed($user));
    }

    public function testIsAllowedReturnsTrueWhenNoLockOrBan()
    {
        $user = $this->createUser(['USER']);
        $checker = $this->getChecker($user);
        $this->assertTrue($checker->isAllowed($user));
    }

    public function testRedirectingGlobalToLoginWhenNotConnected()
    {
        $router = $this->createMock(RouterInterface::class);
        $router->method('generate')->with('app_login')->willReturn('/login');

        $tokenStorage = $this->createMock(TokenStorageInterface::class);
        $tokenStorage->method('getToken')->willReturn(null);

        $checker = new UserAccesChecker($router, $tokenStorage);

        $response = $checker->redirectingGlobal();
        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertSame('/login', $response->getTargetUrl());
    }

    public function testRedirectingGlobalToVerifyWhenNotVerified()
    {
        $router = $this->createMock(RouterInterface::class);
        $router->method('generate')->willReturnMap([
            ['should_verify', [], '/verify']
        ]);

        $user = $this->createUser(['USER'], false);
        $checker = new UserAccesChecker($router, $this->mockTokenStorage($user));

        $response = $checker->redirectingGlobal($user);
        $this->assertSame('/verify', $response->getTargetUrl());
    }

    public function testRedirectingGlobalToProfilWhenNotStaff()
    {
        $router = $this->createMock(RouterInterface::class);
        $router->method('generate')->willReturnMap([
            ['app_profil', [], '/profil']
        ]);

        $user = $this->createUser(['USER'], true);
        $checker = new UserAccesChecker($router, $this->mockTokenStorage($user));

        $response = $checker->redirectingGlobal($user);
        $this->assertSame('/profil', $response->getTargetUrl());
    }

    public function testRedirectingGlobalToHomeWhenStaffAndVerified()
    {
        $router = $this->createMock(RouterInterface::class);
        $router->method('generate')->willReturnMap([
            ['app_home', [], '/home']
        ]);

        $user = $this->createUser(['ADMIN'], true);
        $checker = new UserAccesChecker($router, $this->mockTokenStorage($user));

        $response = $checker->redirectingGlobal($user);
        $this->assertSame('/home', $response->getTargetUrl());
    }

    public function testRedirectingGlobalJsonNotConnected()
    {
        $checker = new UserAccesChecker($this->createMock(RouterInterface::class), $this->createMock(TokenStorageInterface::class));

        $response = $checker->redirectingGlobalJson(null);
        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertSame(401, $response->getStatusCode());
    }

    public function testRedirectingGlobalJsonNotVerified()
    {
        $user = $this->createUser(['USER'], false);
        $checker = new UserAccesChecker($this->createMock(RouterInterface::class), $this->mockTokenStorage($user));

        $response = $checker->redirectingGlobalJson($user);
        $this->assertSame(403, $response->getStatusCode());
        $this->assertStringContainsString('not verified', $response->getContent());
    }

    public function testRedirectingGlobalJsonLocked()
    {
        $user = $this->createUser(['LOCK'], true);
        $checker = new UserAccesChecker($this->createMock(RouterInterface::class), $this->mockTokenStorage($user));

        $response = $checker->redirectingGlobalJson($user);
        $this->assertSame(403, $response->getStatusCode());
        $this->assertStringContainsString('locked', $response->getContent());
    }

    public function testRedirectingGlobalJsonBanned()
    {
        $user = $this->createUser(['BAN'], true);
        $checker = new UserAccesChecker($this->createMock(RouterInterface::class), $this->mockTokenStorage($user));

        $response = $checker->redirectingGlobalJson($user);
        $this->assertSame(403, $response->getStatusCode());
        $this->assertStringContainsString('banned', $response->getContent());
    }

    public function testRedirectingGlobalJsonReturnsNullIfAllowed()
    {
        $user = $this->createUser(['ADMIN'], true);
        $checker = new UserAccesChecker($this->createMock(RouterInterface::class), $this->mockTokenStorage($user));

        $this->assertNull($checker->redirectingGlobalJson($user));
    }

}
