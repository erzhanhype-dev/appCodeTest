<?php

namespace App\Services\Auth;

use PasswordHistory;
use Phalcon\Di\Injectable;
use User;

class PasswordService extends Injectable
{
    public function validatePassword(string $password): bool
    {
        return $password !== '';
    }

    public function validatePasswordConfirmation(string $password, string $confirmation): bool
    {
        return $password === $confirmation;
    }

    public function isPasswordSecure(string $password): bool
    {
        $uppercase = preg_match('@[A-Z]@', $password);
        $lowercase = preg_match('@[a-z]@', $password);
        $number = preg_match('@[0-9]@', $password);
        $specialChars = preg_match('@[^\w]@', $password);

        return $uppercase && $lowercase && $number && $specialChars && strlen($password) >= 8;
    }

    public function processPasswordReset(User $user, string $password, string $passwordAgain): bool
    {

    }

    public function isRecentPassword(User $user, string $newPassword): bool
    {
        $recentPasswords = PasswordHistory::find([
            'conditions' => 'user_id = ?1',
            'bind' => [1 => $user->id],
            'order' => 'created_at DESC',
            'limit' => 3
        ]);

        foreach ($recentPasswords as $oldPassword) {
            if (password_verify(getenv('NEW_SALT') . $newPassword, $oldPassword->password)) {
                return true;
            }
        }

        return false;
    }

    public function updatePassword(User $user, string $password): void
    {
        $passwordHash = password_hash(getenv('NEW_SALT') . $password, PASSWORD_DEFAULT);
        $this->userService->updateUserPassword($user, $passwordHash);
        $this->storePasswordHistory($user->id, $passwordHash);
        $this->cleanupOldPasswords($user->id);
    }

    private function storePasswordHistory(int $userId, string $passwordHash): void
    {
        $passwordHistory = new PasswordHistory();
        $passwordHistory->user_id = $userId;
        $passwordHistory->password = $passwordHash;
        $passwordHistory->save();
    }

    private function cleanupOldPasswords(int $userId): void
    {
        // Берём самые старые записи, которые нужно удалить (все кроме последних 3)
        $toDelete = PasswordHistory::find([
            'conditions' => 'user_id = :user_id:',
            'bind'       => ['user_id' => $userId],
            'order'      => 'id ASC',
        ]);

        $count = $toDelete->count(); // Phalcon\Mvc\Model\ResultsetInterface
        if ($count <= 3) {
            return;
        }

        $deleteCount = $count - 3;

        // Удаляем первые (самые старые) deleteCount записей
        $i = 0;
        foreach ($toDelete as $row) {
            if ($i >= $deleteCount) {
                break;
            }
            $row->delete();
            $i++;
        }
    }
}
