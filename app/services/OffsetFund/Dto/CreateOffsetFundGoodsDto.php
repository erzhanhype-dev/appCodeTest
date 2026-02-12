<?php

namespace App\Services\OffsetFund\Dto;

use Phalcon\Http\RequestInterface;

class CreateOffsetFundGoodsDto
{
    public int $offset_fund_id;
    public int $ref_country_id = 201;
    public int $ref_tn_code_id;
    public int $basis_at;
    public string $basis;
    public float $weight = 0.0;

    public static function fromRequest(RequestInterface $request, int $fundId, ?int $goodsId = null): self
    {
        $basisAtRaw = trim((string)$request->getPost('basis_at', 'string'));
        $weightRaw = (string)$request->getPost('weight');

        $dto = new self();
        $dto->offset_fund_id = $fundId;
        $dto->weight = (float)str_replace(',', '.', $weightRaw);
        $dto->basis = trim((string)$request->getPost('basis', 'string'));
        $dto->basis_at = $basisAtRaw !== '' ? strtotime($basisAtRaw) : time();
        $dto->ref_tn_code_id = (int)$request->getPost('ref_tn_code_id', 'int');

        $countryId = (int)$request->getPost('ref_country_id', 'int');
        $dto->ref_country_id = $countryId > 0 ? $countryId : 201;

        return $dto;
    }
}
