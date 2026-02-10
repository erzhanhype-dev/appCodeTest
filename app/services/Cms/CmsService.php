<?php

namespace App\Services\Cms;

use App\Exceptions\AppException;
use App\Services\Integration\IntegrationService;

class CmsService extends IntegrationService
{
    /**
     * @throws AppException
     */
    public function auth($clientHash, $pem):array
    {
        $result = $this->verify([
            'hash' => $clientHash,
            'sign' => $pem
        ]);

        return $result;
    }

    /**
     * @throws AppException
     */
    public function check($clientHash, $pem):array
    {
        $result = $this->sign([
            'hash' => $clientHash,
            'sign' => $pem
        ]);
        return $result;
    }

    /**
     * @throws AppException
     */
    public function checkAttached($clientHash, $pem):array
    {
        $result = $this->verifyAttached([
            'hash' => $clientHash,
            'sign' => $pem
        ]);
        return $result;
    }

    /**
     * @throws AppException
     */
    public function verifyAttached(array $payload)
    {
        return $this->post('/cms/verify/attached', $payload);
    }

    public function verify(array $payload): array
    {
        return $this->post('/cms/verify', $payload);
    }

    /**
     * @throws AppException
     */
    public function sign(array $payload): array
    {
        return $this->post('/cms/sign/attached', $payload);
    }

    /**
     * @throws AppException
     */
    public function signHash(string $hash): array
    {
        return $this->post('/cms/sign/hash', ['hash' => $hash]);
    }

    public function parseUserData(array $cmsData): array
    {
        return [
            'iin'     => $cmsData['iin'],
            'bin'     => $cmsData['bin'],
            'eku'     => $cmsData['eku'],
            'company' => $cmsData['company'],
            'ln'      => $cmsData['ln'],
            'fn'      => $cmsData['fn'],
            'gn'      => $cmsData['gn'],
            'fio'      => $cmsData['fio'],
            'subject_dn'      => $cmsData['subject_dn'],
        ];
    }

    //проверка oid на Юридическое лицо по ЭЦП полю eku
    public function isLegalEntity(?string $eku): bool
    {
        if (empty($eku)) {
            return false;
        }

        $legalEntityOid = OIDS['legal_entity'];

        // примитивный парсер: режем по ;, вытаскиваем всё, что похоже на OID
        $oids = [];
        foreach (explode(';', $eku) as $part) {
            if (preg_match_all('/\d+(?:\.\d+)+/', $part, $m)) {
                $oids = array_merge($oids, $m[0]);
            }
        }

        return in_array($legalEntityOid, $oids, true);
    }
}