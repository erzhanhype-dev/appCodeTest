<?php

class User extends \Phalcon\Mvc\Model
{

    /**
     *
     * @var integer
     */
    public $id;

    /**
     *
     * @var string
     */
    public $idnum;

    /**
     *
     * @var string
     */
    public $login;

    /**
     *
     * @var string
     */
    public $password;

    /**
     *
     * @var string
     */
    public $email;

    /**
     *
     * @var string
     */
    public $fio;

    /**
     *
     * @var string
     */
    public $org_name;

    /**
     *
     * @var integer
     */
    public $active;

    /**
     *
     * @var string
     */
    public $lastip;

    /**
     *
     * @var integer
     */
    public int $user_type_id = 1;

    /**
     *
     * @var string
     */
    public $fund_stage;

    /**
     *
     * @var string
     */
    public $pir_stage;

    /**
     *
     * @var string
     */
    public $accountant;

    /**
     *
     * @var integer
     */
    public $view_mode;

    /**
     *
     * @var string
     */
    public $lang;

    /**
     *
     * @var integer
     */
    public $login_attempts;

    /**
     *
     * @var integer
     */
    public $last_attempt;

    /**
     *
     * @var string
     */
    public $password_expiry;

    /**
     *
     * @var integer
     */
    public $role_id;

    /**
     *
     * @var string
     */
    public $bin;

    /**
     * @var int
     */
    public $fund_user;

    /**
     * @var int
     */
    public $is_employee;

    /**
     * @param $permission
     * @return bool
     */
    public static function isB($permission): bool
    {
        return count($permission) > 0;
    }


    /**
     * Initialize method for model.
     */

    public function initialize()
    {
        $this->setSchema("recycle");
        $this->setSource("user");

        $this->belongsTo(
            'role_id',
            Role::class,
            'id',
            [
                'alias' => 'role'
            ]
        );

        $this->belongsTo(
            'id',
            CompanyDetail::class,
            'user_id',
            [
                'alias' => 'company_detail'
            ]
        );

        $this->belongsTo(
            'id',
            PersonDetail::class,
            'user_id',
            [
                'alias' => 'person_detail'
            ]
        );

        $this->belongsTo(
            'id',
            ContactDetail::class,
            'user_id',
            [
                'alias' => 'contact_detail'
            ]
        );
    }

    public static function isEmployee(): bool
    {
        $is_jd_user = false;
        $auth = User::getUserBySession();
        $roleName = $auth->role->name;
        $idnum_arr = array_filter(array_map('trim', explode(',', getenv('ALLOWED_MODERATORS'))));
        if ($auth->bin === ZHASYL_DAMU_BIN || $auth->bin === ROP_BIN || in_array($auth->idnum, $idnum_arr)) {
            if ($roleName == 'admin') $is_jd_user = true;
            if ($roleName == 'moderator') $is_jd_user = true;
            if ($roleName == 'super_moderator') $is_jd_user = true;
            if ($roleName == 'accountant') $is_jd_user = true;
            if ($roleName == 'admin_soft') $is_jd_user = true;
            if ($roleName == 'admin_sec') $is_jd_user = true;
            if ($roleName == 'auditor') $is_jd_user = true;
        }
        return $is_jd_user;
    }

    public static function getUserBySession()
    {
        if (isset($_SESSION['auth'])) {
            $a = $_SESSION['auth'];
            $session = Session::findFirstBySessionId($a['session_id']);
            if (!$session) {
                return null;
            }

            $user = self::findFirst($session->user_id);
            if($user->login === 'admin') {
                return (new User)->getAdminPermission($user);
            }
            return $user;
        }
        return null;
    }

    public static function isSuperModerator(): bool
    {
        $auth = User::getUserBySession();
        $idnum_arr = array_filter(array_map('trim', explode(',', getenv('ALLOWED_MODERATORS'))));
        if ($auth->bin === ZHASYL_DAMU_BIN || $auth->bin === ROP_BIN || in_array($auth->idnum, $idnum_arr)) {
            if ($auth->role && $auth->role->name == 'super_moderator') {
                return true;
            }
        }
        return false;
    }

    public static function isModerator(): bool
    {
        $idnum_arr = array_filter(array_map('trim', explode(',', getenv('ALLOWED_MODERATORS'))));
        $auth = User::getUserBySession();
        if ($auth->bin === ZHASYL_DAMU_BIN || $auth->bin === ROP_BIN || in_array($auth->idnum, $idnum_arr)) {
            if ($auth->role && $auth->role->name == 'moderator') {
                return true;
            }
        }
        return false;
    }

    public static function isAdmin(): bool
    {
        $auth = User::getUserBySession();
        if ($auth->login === 'admin') {
            $auth->role = (object)[
                'name' => 'admin',
                'description' => 'Мастер Администратор',
                'priority' => 1,
                'weight' => 1,
            ];
                return true;
            }
        return false;
    }

    public static function isAdminSoft(): bool
    {
        $auth = User::getUserBySession();
        $idnum_arr = array_filter(array_map('trim', explode(',', getenv('ALLOWED_MODERATORS'))));
        if ($auth->bin === ZHASYL_DAMU_BIN || $auth->bin === ROP_BIN || in_array($auth->idnum, $idnum_arr)) {
            if ($auth->role && $auth->role->name == 'admin_soft') {
                return true;
            }
        }
        return false;
    }

    public static function isAdminSec(): bool
    {
        $auth = User::getUserBySession();
        $idnum_arr = array_filter(array_map('trim', explode(',', getenv('ALLOWED_MODERATORS'))));
        if ($auth->bin === ZHASYL_DAMU_BIN || $auth->bin === ROP_BIN || in_array($auth->idnum, $idnum_arr)) {
            if ($auth->role && $auth->role->name == 'admin_sec') {
                return true;
            }
        }
        return false;
    }

    public static function isAccountant(): bool
    {
        $auth = User::getUserBySession();
        $idnum_arr = array_filter(array_map('trim', explode(',', getenv('ALLOWED_MODERATORS'))));

        if ($auth->bin === ZHASYL_DAMU_BIN || $auth->bin === ROP_BIN || in_array($auth->idnum, $idnum_arr)) {
            if ($auth->role && $auth->role->name == 'accountant') {
                return true;
            }
        }
        return false;
    }

    public static function isClient(): bool
    {
        $auth = User::getUserBySession();
        if ($auth->role && $auth->role->name == 'client') {
            return true;
        }
        return false;
    }

    public static function isAuditor(): bool
    {
        $auth = User::getUserBySession();
        if ($auth->role && $auth->role->name == 'auditor') {
            return true;
        }
        return false;
    }

    public static function isOperator(): bool
    {
        $auth = User::getUserBySession();
        if ($auth->role && $auth->role->name == 'operator') {
            return true;
        }
        return false;
    }

    public static function isAgent(): bool
    {
        $auth = User::getUserBySession();
        if ($auth->role && $auth->role->name == 'agent') {
            return true;
        }
        return false;
    }

    public function getRoleName()
    {
        return $this->getRole()->name;
    }

    public static function hasPermission($controller, $action): bool
    {
        $auth = User::getUserBySession();
        $permission = $auth->role->getPermissions([
            'conditions' => "[Permission].controller = :controller: AND [Permission].action = :action:",
            'bind' => [
                'controller' => $controller,
                'action' => $action
            ],
        ])->toArray();

        if (count($permission) > 0) {
            return true;
        }

        return false;
    }

    public static function getAdminPermission($user){

        $role = (object)[
            'id' => 1,
            'name' => 'admin',
            'description' => 'Мастер Администратор',
            'priority' => 1,
            'weight' => 1
        ];
        $user->role = $role;

        $rolePermissions = ADMIN_PERMISSIONS;

        $permissions = [];
        foreach ($rolePermissions as $rolePermission) {
            $permission = Permission::findFirst([
                'conditions' => 'id = :id:',
                'bind'       => [
                    'id' => $rolePermission['permission_id'],
                ]
            ]);

            if ($permission) {
                $permissions[] = $permission; // Сохранение в виде массива
            }
        }

        $user->role->permissions = $permissions;
        return $user;
    }

    public static function find($parameters = null)
    {
        return parent::find($parameters);
    }

    public static function findFirst($parameters = null): mixed
    {
        return parent::findFirst($parameters);
    }

    public static function findFirstById(?int $id): ?self
    {
        return self::findFirst([
            'conditions' => 'id = :id:',
            'bind'       => [
                'id' => $id,
            ],
        ]);
    }

    public static function findFirstByIdnum(?string $idnum): ?self
    {
        return self::findFirst([
            'conditions' => 'idnum = :idnum:',
            'bind'       => [
                'idnum' => $idnum,
            ],
        ]);
    }
}
