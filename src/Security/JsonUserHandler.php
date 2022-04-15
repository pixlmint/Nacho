<?php

namespace Nacho\Security;


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
        return json_decode(file_get_contents(FILE_PATH), true);
    }

    public function changePassword(string $username, string $oldPassword, string $newPassword)
    {
        $user = $this->findUser($username);
        if (!password_verify($oldPassword, $user['password'])) {
            throw new \Exception('The Passwords don\'t match');
        }

        $this->setPassword($username, $newPassword);

        return true;
    }

    public function setPassword(string $username, string $newPassword)
    {

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

    public function getRoles()
    {
        return ['Super Admin', 'Editor', 'Reader', 'Guest'];
    }

    public function isGranted(string $minRight = 'Guest', array $user = null)
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
        file_put_contents(FILE_PATH, json_encode($json));
    }
}
