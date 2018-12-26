<?php

declare(strict_types=1);

namespace App\Share;

use App\Entity\Share;
use App\Entity\User;
use App\Model\DTO\ShareUser;
use App\Share\Event\ShareCreatedEvent;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\EntityManagerInterface;
use FOS\UserBundle\Model\UserManagerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Core\Security;

final class ShareManager
{
    /**
     * @var Security
     */
    private $security;
    /**
     * @var UserManagerInterface
     */
    private $userManager;
    /**
     * @var EntityManagerInterface
     */
    private $entityManager;
    /**
     * @var EventDispatcherInterface
     */
    private $eventDispatcher;

    public function __construct(
        Security $security,
        UserManagerInterface $userManager,
        EntityManagerInterface $entityManager,
        EventDispatcherInterface $eventDispatcher
    ) {
        $this->security = $security;
        $this->userManager = $userManager;
        $this->entityManager = $entityManager;
        $this->eventDispatcher = $eventDispatcher;
    }

    public function updateShare(Share $share, ShareUser $shareUser): Share
    {
        if (!$this->security->getUser() instanceof User) {
            throw new AccessDeniedException('Access denied to the method');
        }

        $user = $this->userManager->findUserByEmail($shareUser->getEmail());
        if (!$user) {
            /** @var User $user */
            $user = $this->userManager->createUser();
            $user->setEnabled(true);
            $user->setGuest(true);
            $user->setEmail($shareUser->getEmail());
            $user->setUsername($shareUser->getEmail());
            $user->setEncryptedPrivateKey($shareUser->getEncryptedPrivateKey());
            $user->setPublicKey($shareUser->getPublicKey());
            $user->setPlainPassword(md5(uniqid('', true)));

            $this->userManager->updateUser($user);
        }

        $share->setOwner($this->security->getUser());
        $share->setUser($user);

        $this->entityManager->persist($share);
        $this->entityManager->flush();

        $this->eventDispatcher->dispatch(ShareCreatedEvent::NAME, new ShareCreatedEvent($share));

        return $share;
    }

    public function editShare(string $shareId, Share $shareNew): Share
    {
        $share = $this->entityManager->getRepository(Share::class)->find($shareId);
        if (!$this->security->getUser() instanceof User || $this->security->getUser() !== $share->getOwner()) {
            throw new AccessDeniedException('Access denied to the method');
        }

        $share->setSharedItems(new ArrayCollection());
        foreach ($shareNew->getSharedItems() as $shareItem) {
            $share->addSharedItem($shareItem);
        }

        $this->entityManager->persist($share);
        $this->entityManager->flush();

        return $share;
    }
}
