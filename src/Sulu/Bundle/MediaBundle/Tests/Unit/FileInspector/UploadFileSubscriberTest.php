<?php

declare(strict_types=1);

/*
 * This file is part of Sulu.
 *
 * (c) Sulu GmbH
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Sulu\Bundle\MediaBundle\Tests\Unit\FileInspector;

use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Prophecy\Prophecy\ObjectProphecy;
use Sulu\Bundle\MediaBundle\FileInspector\FileInspectorInterface;
use Sulu\Bundle\MediaBundle\FileInspector\UnsafeFileException;
use Sulu\Bundle\MediaBundle\FileInspector\UploadFileSubscriber;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\HttpKernelInterface;

class UploadFileSubscriberTest extends TestCase
{
    use ProphecyTrait;

    public const JPEG_FILE = __DIR__ . '/../../Fixtures/files/photo.jpeg';
    public const SVG_FILE = __DIR__ . '/../../Fixtures/files/sulu.svg';

    /**
     * @var ObjectProphecy<FileInspectorInterface>
     */
    private ObjectProphecy $svgInspector;

    private UploadFileSubscriber $subscriber;

    protected function setUp(): void
    {
        $this->svgInspector = $this->prophesize(FileInspectorInterface::class);
        $this->subscriber = new UploadFileSubscriber(new \ArrayObject([$this->svgInspector->reveal()]));
    }

    public function testGetSubscribedEvents(): void
    {
        $this->assertSame(
            ['kernel.request' => 'onKernelRequest'],
            UploadFileSubscriber::getSubscribedEvents(),
        );
    }

    public function testOnKernelRequestWithSafeSvg(): void
    {
        $mimeType = 'image/svg+xml';
        $uploadedFile = new UploadedFile(self::SVG_FILE, 'test.svg', $mimeType, 0, true);

        $request = new Request([], [], [], [], ['file' => $uploadedFile]);
        $event = $this->createRequestEvent($request);

        $this->svgInspector->supports($mimeType)->willReturn(true);
        $this->svgInspector->inspect($uploadedFile)->willReturn($uploadedFile);

        $this->subscriber->onKernelRequest($event);

        // If we reach here without exception, the test passes
        $this->addToAssertionCount(1);
    }

    public function testOnKernelRequestWithMockedFile(): void
    {
        $mimeType = 'image/svg+xml';
        $uploadedFile = $this->getMockBuilder(UploadedFile::class)
            ->disableOriginalConstructor()
            ->getMock();

        $uploadedFile->expects($this->any())
            ->method('getMimeType')
            ->willReturn($mimeType);

        $uploadedFile->expects($this->any())
            ->method('getClientMimeType')
            ->willReturn('image/jpeg');

        $request = new Request([], [], [], [], ['file' => $uploadedFile]);
        $event = $this->createRequestEvent($request);

        $this->svgInspector->supports($mimeType)->willReturn(true);
        $this->svgInspector->inspect($uploadedFile)->willReturn($uploadedFile);

        $this->subscriber->onKernelRequest($event);

        // If we reach here without exception, the test passes
        $this->addToAssertionCount(1);
    }

    public function testOnKernelRequestWithMockedFileAndClientMimeType(): void
    {
        $mimeType = 'image/svg+xml';
        $uploadedFile = $this->getMockBuilder(UploadedFile::class)
            ->disableOriginalConstructor()
            ->getMock();

        $uploadedFile->expects($this->any())
            ->method('getMimeType')
            ->willReturn(null);

        $uploadedFile->expects($this->any())
            ->method('getClientMimeType')
            ->willReturn($mimeType);

        $request = new Request([], [], [], [], ['file' => $uploadedFile]);
        $event = $this->createRequestEvent($request);

        $this->svgInspector->supports($mimeType)->willReturn(true);
        $this->svgInspector->inspect($uploadedFile)->willReturn($uploadedFile);

        $this->subscriber->onKernelRequest($event);

        // If we reach here without exception, the test passes
        $this->addToAssertionCount(1);
    }

    public function testOnKernelRequestWithReplace(): void
    {
        $mimeType = 'image/svg+xml';
        $uploadedFile = new UploadedFile(self::SVG_FILE, 'test.svg', $mimeType, 0, true);
        $newUploadedFile = new UploadedFile(self::SVG_FILE, 'test.svg', $mimeType, 0, true);

        $request = new Request([], [], [], [], ['file' => $uploadedFile]);
        $event = $this->createRequestEvent($request);

        $this->svgInspector->supports($mimeType)->willReturn(true);
        $this->svgInspector->inspect($uploadedFile)->willReturn($newUploadedFile);

        $this->subscriber->onKernelRequest($event);

        $this->assertSame($newUploadedFile, $request->files->get('file'));
    }

    public function testOnKernelRequestWithNestedFiles(): void
    {
        $mimeType = 'image/svg+xml';
        $uploadedFile = new UploadedFile(self::SVG_FILE, 'test.svg', $mimeType, 0, true);

        $request = new Request([], [], [], [], ['Product' => ['thumbnail' => $uploadedFile]]);
        $event = $this->createRequestEvent($request);

        $this->svgInspector->supports($mimeType)->willReturn(true);
        $this->svgInspector->inspect($uploadedFile)
            ->willReturn($uploadedFile)
            ->shouldBeCalled();

        $this->subscriber->onKernelRequest($event);

        // If we reach here without exception, the test passes
        $this->addToAssertionCount(1);
    }

    public function testOnKernelRequestWithNull(): void
    {
        $request = new Request([], [], [], [], ['Product' => ['thumbnail' => null]]);
        $event = $this->createRequestEvent($request);

        $this->svgInspector->supports(Argument::any())->shouldNotBeCalled();
        $this->svgInspector->inspect(Argument::any())->shouldNotBeCalled();

        $this->subscriber->onKernelRequest($event);

        // If we reach here without exception, the test passes
        $this->addToAssertionCount(1);
    }

    public function testOnKernelRequestWithUnsafeSvg(): void
    {
        $this->expectException(BadRequestHttpException::class);

        $mimeType = 'image/svg+xml';
        $uploadedFile = new UploadedFile(self::SVG_FILE, 'test.svg', $mimeType, 0, true);

        $request = new Request([], [], [], [], ['file' => $uploadedFile]);
        $event = $this->createRequestEvent($request);

        $this->svgInspector->supports($mimeType)->willReturn(true);
        $this->svgInspector->inspect($uploadedFile)->willThrow(new UnsafeFileException($uploadedFile));

        $this->subscriber->onKernelRequest($event);
    }

    public function testOnKernelRequestWithNonSvgFile(): void
    {
        $mimeType = 'image/jpeg';
        $uploadedFile = new UploadedFile(self::JPEG_FILE, 'test.svg', $mimeType, 0, true);

        $request = new Request([], [], [], [], ['file' => $uploadedFile]);
        $event = $this->createRequestEvent($request);

        $this->svgInspector->supports($mimeType)->willReturn(false);
        $this->svgInspector->inspect($uploadedFile)->shouldNotBeCalled();

        $this->subscriber->onKernelRequest($event);

        // If we reach here without exception, the test passes
        $this->addToAssertionCount(1);
    }

    private function createRequestEvent(Request $request): RequestEvent
    {
        $kernel = $this->prophesize(HttpKernelInterface::class);

        return new RequestEvent($kernel->reveal(), $request, HttpKernelInterface::MAIN_REQUEST);
    }
}
