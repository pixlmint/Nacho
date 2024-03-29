<?php

namespace Nacho\Helpers;

use Exception;
use Nacho\Contracts\UserHandlerInterface;
use Nacho\Nacho;
use Nacho\Models\PicoPage;
use Nacho\Security\PageSecurityStatus;
use Nacho\Security\UserInterface;

class PageSecurityHelper
{
    private UserHandlerInterface $userHandler;
    private array $privateFolders = [];

    public function __construct(UserHandlerInterface $userHandler)
    {
        $this->userHandler = $userHandler;
    }

    public static function isPagePublic(PicoPage $page): bool
    {
        return !$page->getSecurity() || $page->getSecurity() === PageSecurityStatus::PUBLIC;
    }

    public function isPageShowingForCurrentUser(PicoPage $page): bool
    {
        if ($this->isChildPathOfPrivateFolder($page->id)) {
            return false;
        }

        if ($this->isPagePublic($page)) {
            return true;
        }

        /** @var UserInterface $currentUser */
        $currentUser = $this->userHandler->getCurrentUser();
        if ($page->getSecurity() === PageSecurityStatus::PRIVATE) {
            if (!$page->meta->owner) {
                throw new Exception(sprintf('Post %s doesn\'t have an owner specified but it\'s set to Private', $page->id));
            }
            if ($currentUser->getUsername() === $page->meta->owner) {
                return true;
            }
        }

        $this->privateFolders[] = $page->id;

        return false;
    }

    private function isChildPathOfPrivateFolder(string $pagePath): bool
    {
        foreach ($this->privateFolders as $privateFolder) {
            if (str_starts_with($pagePath, $privateFolder)) {
                return true;
            }
        }

        return false;
    }
}