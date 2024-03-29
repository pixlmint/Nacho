<?php


namespace Nacho\Contracts;


use Nacho\Security\UserInterface;

interface UserHandlerInterface
{
    public function getCurrentUser();

    public function getUsers();

    public function findUser(string $username);

    public function changePassword(string $username, string $oldPassword, string $newPassword);

    public function setPassword(string $username, string $newPassword);

    public function logout();

    public function passwordVerify(UserInterface $user, string $password);

    public function isGranted(string $minRight = 'Guest', ?UserInterface $user = null);
}