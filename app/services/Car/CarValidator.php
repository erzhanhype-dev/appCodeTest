<?php

namespace App\Services\Car;

use App\Exceptions\AppException;
use App\Repositories\CarRepository;
use Phalcon\Di\Injectable;
use Profile;
use User;

class CarValidator extends Injectable
{
    /** @var VinService */
    private VinService $vinService;
    protected CarRepository $carRepository;
    protected CarSessionService $carSessionService;

    public function __construct()
    {
        $this->onConstruct();
    }

    public function onConstruct(): void
    {
        $this->vinService = $this->getDI()->getShared(VinService::class);
        $this->carRepository = $this->di->getShared(CarRepository::class);
        $this->carSessionService = $this->di->getShared(CarSessionService::class);
    }

    /**
     * @throws AppException
     */
    public function assertProfileAccessible(?Profile $profile, $authUser): Profile
    {
        if (!$profile) {
            throw new AppException('Профиль не найден');
        }

        $isClient = $authUser && $authUser->isClient();
        $isOwner = $profile->user_id === ($authUser ? $authUser->id : null);
        $isStaff = $authUser && ($authUser->isSuperModerator() || $authUser->isAdminSoft());

        if ($isClient && !$isOwner && !$isStaff) {
            throw new AppException('У вас нет прав на это действие!');
        }

        return $profile;
    }

    /**
     * @throws AppException
     */
    public function assertCarLimit(Profile $profile): void
    {
        if ($this->hasReachedCarLimit((int)$profile->id)) {
            throw new AppException($this->translator->_('max_car_add_profile_validate'));
        }
    }

    /**
     * @throws AppException
     */
    public function assertVinUnique(?string $vin, ?string $idCode, ?string $bodyCode, ?int $ignoreCarId = null): void
    {
        if (empty($vin)) {
            $vin = (string)$idCode . '&' . $bodyCode;
        }

        if ($this->isCarAlreadyExists((string)$vin, $ignoreCarId)) {
            throw new AppException($this->translator->_('vin_exist_validate'));
        }
    }

    public function hasReachedCarLimit(int $profileId): bool
    {
        $limit = (int)PROFILE_CAR_LIMIT;
        return $this->carRepository->countByProfileId($profileId) >= $limit;
    }

    public function isCarAlreadyExists(string $vin, ?int $carId = null): bool
    {
        return $this->carRepository->existsByVin($vin, $carId);
    }

    /**
     * @throws AppException
     */
    public function check(array $data): void
    {
        $vehicleType = $data['vehicle_type'] ?? '';

        if ($vehicleType === 'AGRO') {
            $this->validateAgroNum($data);
        } else {
            $this->validateVin($data);
        }

        $serialNum = $this->getSerialNum($data);
        if (!$this->isLatinAndDigitsOnly($serialNum) && $vehicleType !== 'AGRO') {
            throw new AppException($this->translator->_('car_latin_and_number_validate'));
        }
    }

    /**
     * @throws AppException
     */
    private function validateAgroNum(array $data): void
    {
        $idCode = $data['id_code'] ?? '';
        $bodyCode = $data['body_code'] ?? '';

        if (($idCode === '' || $bodyCode === '')) {
            throw new AppException($this->translator->_('vin_agro_validate'));
        }

        $serialNum = $idCode . '&' . $bodyCode;
        if (!(bool)preg_match('/^(?=.*\S)(?!\s)(?!.*\s$)(?!.*\s&)(?!.*&\s).*$/u', $serialNum)) {
            throw new AppException($this->translator->_('Пробелы в начале и в конце запрещены'));
        }

        if(!$this->isAgroNumValid($idCode, $bodyCode)){
            throw new AppException($this->translator->_('Слишком короткий номер'));
        }
    }

    /**
     * @throws AppException
     */
    private function validateVin(array $data): void
    {
        $vin = $data['vin'] ?? '';
        if (!$this->isVinValid($vin)) {
            throw new AppException($this->translator->_('vin_car_validate'));
        }
    }

    private function getSerialNum(array $data): string
    {
        if (($data['vehicle_type'] ?? '') === 'AGRO') {
            return ($data['id_code'] ?? '') . '&' . ($data['body_code'] ?? '');
        }
        return $data['vin'] ?? '';
    }

    /**
     * @throws AppException
     */
    public function assert(array $data, ?User $auth = null): void
    {
        $this->check($data);

        if ($this->isVolumeTooHigh($data['volume'] ?? 0)) {
            throw new AppException($this->translator->_('max_volume_validate'));
        }

        if ($this->isImportedBeforeThreshold($data['date_import'] ?? 0)) {
            throw new AppException($this->translator->_('car_import_date_validate'));
        }

        if($data['ref_car_type_id'] != 6) {
            if ($this->isInvalidElectricVolume($data['electric_car'] ?? 0, $data['ref_car_type_id'] ?? null, $data['volume'] ?? 0)) {
                throw new AppException($this->translator->_('electric_car_volume_validate'));
            }
        }

        if ($this->isInvalidStCategory($data['ref_st_type'] ?? 0, $data['ref_car_cat'] ?? null, $data['ref_car_type_id'] ?? null)) {
            $catName = $data['ref_car_cat'] && isset($data['ref_car_cat']->name) ? $this->translator->_($data['ref_car_cat']->name) : 'Категория';
            $message = "$catName не может быть седельным тягачом!";
            throw new AppException($this->translator->_($message));
        }

        if ($auth === null) {
            $auth = User::getUserBySession();
        }

        if ($auth && $auth->isClient()) {
            if (empty($data['ref_country'])) {
                $message = "Поле «Страна производства» обязательно для заполнения.";
                throw new AppException($this->translator->_($message));
            }
        }

        if ($auth && $auth->isClient()) {
            if (empty($data['ref_country_import'])) {
                $message = "Поле «Страна импорта» обязательно для заполнения.";
                throw new AppException($this->translator->_($message));
            }
        }

        if ((float)($data['volume'] ?? 0) < 1 && (int)($data['electric_car'] ?? 0) === 0) {
            throw new AppException('Объем двигателя не заполнен');
        }
    }

    public function isAgroNumValid($idCode, $bodyCode): bool
    {
        $num = $this->vinService->buildVinFromParts((string)$idCode, (string)$bodyCode);
        return mb_strlen($num) >= 7;
    }

    public function isVinValid($vin): bool
    {
        return mb_strlen((string)$vin) === 17;
    }

    public function isLatinAndDigitsOnly($string): bool
    {
        return $this->vinService->isLatinAndDigitsOnly($string);
    }

    public function isVolumeTooHigh($volume): bool
    {
        return (float)$volume > 50000;
    }

    public function isImportedBeforeThreshold($dateImport): bool
    {
        return (int)$dateImport < strtotime(STARTROP);
    }

    public function isInvalidElectricVolume($isElectric, $carTypeId, $volume): bool
    {
        return $isElectric && $carTypeId != 2 && (float)$volume > 0;
    }

    public function isInvalidStCategory($refSt, $refCarCat, $refCarTypeId): bool
    {
        if ((int)$refSt > 0) {
            $catId = is_object($refCarCat) ? $refCarCat->id : (int)$refCarCat;
            if ($refCarTypeId == 2 && $catId >= 3 && $catId <= 8) {
                return false;
            }
            return true;
        }

        return false;
    }
}