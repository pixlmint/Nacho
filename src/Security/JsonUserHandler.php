<?php

namespace Nacho\Security;

use Nacho\Contracts\UserHandlerInterface;
use Nacho\Helpers\DataHandler;

class JsonUserHandler implements UserHandlerInterface
{
    public function __construct()
    {
        if (!isset($_SESSION['user'])) {
            $_SESSION['user'] = ['username' => 'Guest', 'password' => null, 'role' => 'Guest'];
        }
    }

    public function getCurrentUser()
    {
        return $this->findUser($_SESSION['user']['username']);
    }

    public function getUsers()
    {
        return DataHandler::getInstance()->readData('users');
    }

    public function changePassword(string $username, string $oldPassword, string $newPassword)
    {
        $user = $this->findUser($username);
        if (!$this->passwordVerify($username, $oldPassword)) {
            throw new \Exception('The Passwords don\'t match');
        }

        $this->setPassword($username, $newPassword);

        return true;
    }

    public function passwordVerify(string $username, string $password): bool
    {
        $user = $this->findUser($username);
        return password_verify($password, $user['password']);
    }

    public function setPassword(string $username, string $newPassword)
    {
        $user = $this->findUser($username);
        $user['password'] = password_hash($newPassword, PASSWORD_DEFAULT);
        $this->changeUser($user);

        return $user;
    }

    public function findUser($username)
    {
        foreach ($this->getUsers() as $user) {
            if ($username === $user['username']) {
                return $user;
            }
        }

        return false;
    }

    public function logout()
    {
        session_destroy();
    }

    public function getRoles(): array
    {
        return ['Super Admin', 'Editor', 'Reader', 'Guest'];
    }

    public function isGranted(string $minRight = 'Guest', array $user = null): bool
    {
        if (!$user) {
            $user = $this->getCurrentUser();
        }

        return array_search($user['role'], $this->getRoles()) <= array_search($minRight, $this->getRoles());
    }

    public function modifyUser(string $username, string $newKey, $newVar)
    {
        $user = $this->findUser($username);
        $user[$newKey] = $newVar;
        $this->changeUser($user);
        return $user;
    }

    private function changeUser(array $newUser): void
    {
        $json = $this->getUsers();
        foreach ($json as $key => $user) {
            if ($user['username'] === $newUser['username']) {
                $json[$key] = $newUser;
                break;
            }
        }
        DataHandler::getInstance()->writeData('users', $json);
    }
}
