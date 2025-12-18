<?php

declare(strict_types=1);

namespace Tests\Unit\Api\Middleware;

use Api\Middleware\InputSanitizationMiddleware;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Http\Message\ResponseInterface;

class InputSanitizationMiddlewareTest extends TestCase
{
    public function testSanitizesInputRecursively(): void
    {
        $middleware = new InputSanitizationMiddleware();

        $dirtyData = [
            'name' => '  Bad <script>alert(1)</script> User  ',
            'details' => [
                'bio' => '<b>Bold</b> but invalid',
                'tags' => ['<p>one</p>', 'two  ']
            ]
        ];

        $request = $this->createMock(ServerRequestInterface::class);
        $request->expects($this->once())
            ->method('getParsedBody')
            ->willReturn($dirtyData);

        $request->expects($this->once())
            ->method('withParsedBody')
            ->with($this->callback(function ($cleanData) {
                return $cleanData['name'] === 'Bad alert(1) User'
                    && $cleanData['details']['bio'] === 'Bold but invalid'
                    && $cleanData['details']['tags'][0] === 'one'
                    && $cleanData['details']['tags'][1] === 'two';
            }))
            ->willReturnSelf();

        $handler = function ($req) {
            return $this->createMock(ResponseInterface::class);
        };

        $middleware($request, $handler);
    }
}
