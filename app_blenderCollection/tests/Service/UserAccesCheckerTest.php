<?php 

namespace App\Tests\Service;

use App\Entity\User;
use App\Service\UserAccesChecker;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;

class UserAccesCheckerTest extends TestCase
{
    public function testIsConnectedReturnsTrueWithUser()
    {
        $user = $this->createMock(User::class);
        $token = $this->createMock(TokenInterface::class);
        $token->method('getUser')->willReturn($user);

        $tokenStorage = $this->createMock(TokenStorageInterface::class);
        $tokenStorage->method('getToken')->willReturn($token);

        $router = $this->createMock(RouterInterface::class);

        $checker = new UserAccesChecker($router, $tokenStorage);

        $this->assertTrue($checker->isConnected());
    }

    public function testIsVerifiedReturnsFalseWhenNotVerified()
    {
        $user = $this->createMock(User::class);
        $user->method('isVerified')->willReturn(false);

        $token = $this->createMock(TokenInterface::class);
        $token->method('getUser')->willReturn($user);

        $tokenStorage = $this->createMock(TokenStorageInterface::class);
        $tokenStorage->method('getToken')->willReturn($token);

        $router = $this->createMock(RouterInterface::class);

        $checker = new UserAccesChecker($router, $tokenStorage);

        $this->assertFalse($checker->isVerified());
    }
}
