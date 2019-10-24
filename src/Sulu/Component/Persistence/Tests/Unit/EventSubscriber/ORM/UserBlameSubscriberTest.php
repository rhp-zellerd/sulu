<?php

/*
 * This file is part of Sulu.
 *
 * (c) Sulu GmbH
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Sulu\Component\Persistence\Tests\Unit\EventSubscriber\ORM;

use Doctrine\Common\Persistence\Mapping\ClassMetadata;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Event\LoadClassMetadataEventArgs;
use Doctrine\ORM\Event\OnFlushEventArgs;
use Doctrine\ORM\UnitOfWork;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Sulu\Bundle\SecurityBundle\Entity\User;
use Sulu\Component\Persistence\EventSubscriber\ORM\UserBlameSubscriber;
use Sulu\Component\Security\Authentication\UserInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\User\UserInterface as SymfonyUserInterface;

class UserBlameSubscriberTest extends TestCase
{
    /**
     * @var LoadClassMetadataEventArgs
     */
    private $loadClassMetadataEvent;

    /**
     * @var OnFlushEventArgs
     */
    private $onFlushEvent;

    /**
     * @var \stdClass
     */
    private $userBlameObject;

    /**
     * @var ClassMetadata
     */
    private $classMetadata;

    /**
     * @var \ReflectionClass
     */
    private $refl;

    /**
     * @var EntityManager
     */
    private $entityManager;

    /**
     * @var UnitOfWork
     */
    private $unitOfWork;

    /**
     * @var UserInterface
     */
    private $user;

    /**
     * @var TokenInterface
     */
    private $token;

    /**
     * @var TokenStorageInterface
     */
    private $tokenStorage;

    /**
     * @var UserBlameSubscriber
     */
    private $subscriber;

    public function setUp(): void
    {
        parent::setUp();
        $this->loadClassMetadataEvent = $this->prophesize('Doctrine\ORM\Event\LoadClassMetadataEventArgs');

        $this->onFlushEvent = $this->prophesize('Doctrine\ORM\Event\OnFlushEventArgs');

        $this->userBlameObject = $this->prophesize('\stdClass')
            ->willImplement('Sulu\Component\Persistence\Model\UserBlameInterface');
        $this->classMetadata = $this->prophesize('Doctrine\ORM\Mapping\ClassMetadata');
        $this->refl = $this->prophesize('\ReflectionClass');
        $this->entityManager = $this->prophesize('Doctrine\ORM\EntityManager');
        $this->user = $this->prophesize('Sulu\Component\Security\Authentication\UserInterface');
        $this->token = $this->prophesize('Symfony\Component\Security\Core\Authentication\Token\TokenInterface');
        $this->tokenStorage = $this->prophesize(TokenStorageInterface::class);

        $this->unitOfWork = $this->getMockBuilder(UnitOfWork::class)->disableOriginalConstructor()->getMock();

        $this->subscriber = new UserBlameSubscriber($this->tokenStorage->reveal(), User::class);
        $this->unitOfWork = $this->getMockBuilder(UnitOfWork::class)->disableOriginalConstructor()->getMock();

        $this->tokenStorage->getToken()->willReturn($this->token->reveal());
        $this->token->getUser()->willReturn($this->user->reveal());
        $this->onFlushEvent->getEntityManager()->willReturn($this->entityManager);
        $this->entityManager->getUnitOfWork()->willReturn($this->unitOfWork);
    }

    public function testLoadClassMetadata()
    {
        $this->loadClassMetadataEvent->getClassMetadata()->willReturn($this->classMetadata->reveal());
        $this->classMetadata->getReflectionClass()->willReturn($this->refl->reveal());
        $this->refl->implementsInterface('Sulu\Component\Persistence\Model\UserBlameInterface')->willReturn(true);

        $this->classMetadata->hasAssociation('creator')->shouldBeCalled();
        $this->classMetadata->hasAssociation('changer')->shouldBeCalled();
        $this->classMetadata->mapManyToOne(Argument::any())->shouldBeCalled();

        $this->subscriber->loadClassMetadata($this->loadClassMetadataEvent->reveal());
    }

    public function provideLifecycle()
    {
        return [
            // new entity no user set
            // RESULT: Set creator and changer
            [
                [
                ],
                [
                    'changer',
                    'creator',
                ],
                true,
            ],
            // new entity creator set
            // RESULT: Set changer
            [
                [
                    'creator' => [0 => null, 1 => 1],
                ],
                [
                    'changer',
                ],
                true,
            ],
            // new entity changer set
            // RESULT: Set changer
            [
                [
                    'changer' => [0 => null, 1 => 1],
                ],
                [
                    'creator',
                ],
                true,
            ],
            // changer not overridden, creator is not null
            // RESULT: Only set changer
            [
                [
                    'creator' => [0 => 1, 1 => null],
                ],
                [
                    'changer',
                ],
                false,
            ],
            // changer is null, creator is null (should stay null)
            // RESULT: Set changer
            [
                [
                ],
                [
                    'changer',
                ],
                false,
            ],
            // changer has been overridden, creator is null (should stay null)
            // RESULT: Set nothing
            [
                [
                    'changer' => [0 => null, 1 => 3],
                ],
                [
                ],
                false,
            ],
            // changer is has been changed, creator has been changed
            // RESULT: Do not set anything
            [
                [
                    'changer' => [0 => 1, 1 => 2],
                    'creator' => [0 => 1, 1 => 2],
                ],
                [
                ],
                false,
            ],
        ];
    }

    /**
     * @param $changeset The changeset for the entity
     * @param $expectedFields List of filds which should be updated/set
     *
     * @dataProvider provideLifecycle
     */
    public function testOnFlush($changeset, $expectedFields, $insert = true)
    {
        $entity = $this->userBlameObject->reveal();

        $insertions = $insert ? [$entity] : [];
        $updates = !$insert ? [$entity] : [];

        $this->unitOfWork->method('getScheduledEntityInsertions')->willReturn($insertions);
        $this->unitOfWork->method('getScheduledEntityUpdates')->willReturn($updates);
        $this->unitOfWork->method('getEntityChangeSet')
            ->with($this->equalTo($this->userBlameObject->reveal()))
            ->willReturn($changeset);

        $this->entityManager->getClassMetadata(get_class($entity))->willReturn($this->classMetadata);

        foreach (['creator', 'changer'] as $field) {
            $prophecy = $this->classMetadata->setFieldValue(
                $this->userBlameObject->reveal(),
                $field,
                $this->user->reveal()
            );

            if (in_array($field, $expectedFields)) {
                $prophecy->shouldBeCalled();

                continue;
            }

            $prophecy->shouldNotBeCalled();
        }

        if (count($expectedFields)) {
            $this->unitOfWork->method('recomputeSingleEntityChangeSet')
                ->with(
                    $this->equalTo($this->classMetadata->reveal()),
                    $this->equalTo($this->userBlameObject->reveal())
                );
        }

        $this->subscriber->onFlush($this->onFlushEvent->reveal());
    }

    /**
     * @param $changeset The changeset for the entity
     *
     * @dataProvider provideLifecycle
     */
    public function testOnFlushOtherUser($changeset)
    {
        $symfonyUser = $this->prophesize(SymfonyUserInterface::class);
        $token = $this->prophesize(TokenInterface::class);
        $tokenStorage = $this->prophesize(TokenStorageInterface::class);
        $tokenStorage->getToken()->willReturn($token->reveal());
        $token->getUser()->willReturn($symfonyUser->reveal());
        $subscriber = new UserBlameSubscriber($tokenStorage->reveal(), User::class);

        $entity = $this->userBlameObject->reveal();

        $this->unitOfWork->method('getScheduledEntityInsertions')->willReturn([$entity]);
        $this->unitOfWork->method('getScheduledEntityUpdates')->willReturn([]);
        $this->unitOfWork->method('getEntityChangeSet')
            ->with($this->equalTo($this->userBlameObject->reveal()))
            ->willReturn($changeset);

        $this->entityManager->getClassMetadata(get_class($entity))->willReturn($this->classMetadata);

        foreach (['creator', 'changer'] as $field) {
            $this->classMetadata->setFieldValue(
                $this->userBlameObject->reveal(),
                $field,
                $symfonyUser->reveal()
            )->shouldNotBeCalled();
        }
        $subscriber->onFlush($this->onFlushEvent->reveal());
    }
}
