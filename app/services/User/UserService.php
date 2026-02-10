<?php
namespace App\Services\User;


use App\Helpers\NetworkHelper;
use CompanyDetail;
use ContactDetail;
use PersonDetail;
use Phalcon\Di\Injectable;
use RefFund;
use User;

class UserService extends Injectable
{
    public function findUserById(int $id): ?User
    {
        $user = User::findFirstById($id);
        return $user ?: null;
    }

    public function findUserByEmailAndIdnum(string $email, string $idnum): ?User
    {
        $user = User::findFirst([
            "conditions" => "email = :email: AND idnum = :idnum:",
            "bind" => [
                "email" => trim($email),
                "idnum" => trim($idnum)
            ],
            "order" => "id DESC"
        ]);

        return $user ?: null;
    }

    public function emailExists(string $email, int $excludeUserId): bool
    {
        return User::findFirst([
                'conditions' => 'email = :email: AND id != :id:',
                'bind' => [
                    'email' => $email,
                    'id' => $excludeUserId,
                ]
            ]) != false;
    }

    public function updateOrCreateUserFromEdsData(array $userData): User
    {
        $idnum = $this->getIdNum($userData['iin'], $userData['bin'], $userData['eku']);
        $user = User::findFirstByIdnum($idnum);

        if (!$user) {
            $user = $this->createUserFromEdsData($idnum, $userData);
        } else {
            $this->updateUserFromEdsData($user, $userData);
        }

        return $user;
    }

    public function findUserBySecureSession(array $user_data): ?User
    {
        $idnum = $user_data['bin'] ?: $user_data['iin'];
        if (in_array($user_data['bin'], [ROP_BIN, ZHASYL_DAMU_BIN])) {
            $idnum = $user_data['iin'];
        }

        $user = User::findFirst([
            "conditions" => "idnum = :idnum:",
            "bind" => ["idnum" => $idnum],
            "order" => "id DESC"
        ]);

        return $user ?: null;
    }

    /**
     * @throws \DateMalformedStringException
     */
    public function isUserPasswordExpired(User $user): bool
    {
        $expiryDate = new \DateTime($user->password_expiry);
        $now = new \DateTime();
        return $expiryDate < $now;
    }

    public function isEmployeeUserBlocked(User $user): bool
    {
        return in_array($user->role->name, [
                'admin_soft',
                'admin_sec',
                'super_moderator',
                'moderator',
                'auditor',
                'accountant'
            ]) && $user->active == 0;
    }

    public function isUserEmployee(User $user): bool
    {
        $idnum_arr = array_filter(array_map('trim', explode(',', getenv('ALLOWED_MODERATORS'))));

        if (in_array($user->idnum, $idnum_arr)) {
            return true;
        }

        return !(in_array($user->role->name, ['admin', 'moderator', 'super_moderator', 'accountant', 'admin_soft', 'admin_sec']) &&
            ($user->bin !== ZHASYL_DAMU_BIN && $user->bin !== ROP_BIN));
    }

    public function completeUserRegistration(User $user, string $email, string $password): void
    {
        $expiryDate = date('Y-m-d', strtotime(PASSWORD_EXPIRY_DAYS . " days"));
        $passwordHash = password_hash(getenv('NEW_SALT') . $password, PASSWORD_DEFAULT);

        $user->email = $email;
        $user->email_verified = 1;
        $user->password = $passwordHash;
        $user->password_expiry = $expiryDate;
        $user->save();
    }

    public function updateUserPassword(User $user, string $password): void
    {
        $expiryDate = date('Y-m-d', strtotime(PASSWORD_EXPIRY_DAYS . " days"));
        $passwordHash = password_hash(getenv('NEW_SALT') . $password, PASSWORD_DEFAULT);

        $user->email_verified = 1;
        $user->last_reset = time();
        $user->login_attempts = 0;
        $user->password = $password;
        $user->password_expiry = $expiryDate;
        $user->save();
    }

    public function resetUserLoginState($user): void
    {
        $user->login_attempts = 0;
        $user->last_login = time();
        $user->active = 1;
        $user->save();
    }

    public function incrementLoginAttempts($user): void
    {
        $user->login_attempts = $user->login_attempts + 1;
        $user->last_attempt = time();
        $user->last_login = time();
        $user->save();
    }

    private function createUserFromEdsData(string $idnum, array $edsData): User
    {
        $isLegal = $this->cmsService->isLegalEntity($edsData['eku']);
        $user = new User();
        $user->login = 'gost';
        $user->idnum = $idnum;
        $user->bin = $edsData['bin'];
        $user->active = 0;
        $user->is_employee = (int)in_array($edsData['bin'], [ROP_BIN, ZHASYL_DAMU_BIN]);
        $user->lastip = NetworkHelper::getClientIp();
        $user->user_type_id = $isLegal ? 2 : 1;
        $user->fio = $edsData['fio'];
        $user->org_name = $edsData['company'];
        $user->fund_user = (int)(bool)RefFund::findFirstByIdnum($idnum);
        $user->role_id = 9;
        if ($user->save()) {
            $ct = new ContactDetail();
            $ct->user_id = $user->id;
            $ct->ref_country_id = KZ_CODE;
            $ct->ref_reg_country_id = KZ_CODE;
            $ct->save();

            if ($user->user_type_id === 1) {
                $dt = new PersonDetail();
                $dt->user_id = $user->id;
                $dt->iin = $edsData['iin'];
                $dt->first_name = $edsData['fn'];
                $dt->last_name = $edsData['ln'];
                $dt->parent_name = $edsData['gn'];
                $dt->save();
            } else if ($user->user_type_id === 2) {
                $dt = new CompanyDetail();
                $dt->user_id = $user->id;
                $dt->bin = $edsData['bin'];
                $dt->name = $edsData['company'];
                $dt->save();
            }
        }

        return $user;
    }

    private function updateUserFromEdsData(User $user, array $edsData): void
    {
        $fund_user = RefFund::findFirstByIdnum($user->idnum) ? 1 : 0;
        $isEmployee = in_array($edsData['bin'], [ROP_BIN, ZHASYL_DAMU_BIN]) ? 1 : 0;
        $isLegal = $this->cmsService->isLegalEntity($edsData['eku']);
        $user->user_type_id = $isLegal ? 2 : 1;

        $user->lastip = NetworkHelper::getClientIp();
        $user->bin = $edsData['bin'];
        $user->fio = $edsData['fio'];
        $user->org_name = $edsData['company'];
        $user->fund_user = $fund_user;
        $user->is_employee = $isEmployee;
        if ($user->save()) {
            $ct = new ContactDetail();
            $ct->user_id = $user->id;
            $ct->ref_country_id = KZ_CODE;
            $ct->ref_reg_country_id = KZ_CODE;
            $ct->save();

            if ($user->user_type_id === 1) {
                $dt = new PersonDetail();
                $dt->user_id = $user->id;
                $dt->iin = $edsData['iin'];
                $dt->first_name = $edsData['fn'];
                $dt->last_name = $edsData['ln'];
                $dt->parent_name = $edsData['gn'];
                $dt->save();
            } else if ($user->user_type_id === 2) {
                $dt = new CompanyDetail();
                $dt->user_id = $user->id;
                $dt->bin = $edsData['bin'];
                $dt->name = $edsData['company'];
                $dt->save();
            }
        }
    }

    public function getUserByIdnum($idnum): ?User
    {
        return User::findFirstByIdnum($idnum);
    }

    public function getIdNum($iin, $bin, $eku): string
    {
        if (__checkCompany($eku)) {
            if (in_array($bin, [ROP_BIN, ZHASYL_DAMU_BIN])) {
                return $iin;
            }
            return $bin;
        }
        return $iin;
    }

    public function hasValidPassword(User $user): bool
    {
        return !empty($user->password) && strlen($user->password) > 40;
    }

    public function hasEmail(User $user): bool
    {
        return !empty($user->email);
    }

    public function isEmailVerified(User $user): bool
    {
        return (bool)$user->email_verified;
    }
}
