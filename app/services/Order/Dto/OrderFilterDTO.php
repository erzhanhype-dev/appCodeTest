<?php
namespace App\Services\Order\Dto;

class OrderFilterDTO
{
    public ?int $profileId = null;
    public array $types = [];
    public array $statuses = [];
    public ?int $fromDate = null;
    public ?int $toDate = null;
    public int $page = 1;
    public int $limit = 10;

    public static function createFromRequest(array $queryParams): self
    {
        $dto = new self();

        $dto->profileId = isset($queryParams['pid']) ? (int)$queryParams['pid'] : null;
        $dto->types = self::normalizeArray($queryParams['type'] ?? []);
        $dto->statuses = self::normalizeArray($queryParams['status'] ?? []);
        $dto->fromDate = isset($queryParams['from']) ? (int)$queryParams['from'] : null;
        $dto->toDate = isset($queryParams['to']) ? (int)$queryParams['to'] : null;
        $dto->page = max(1, (int)($queryParams['page'] ?? 1));
        $dto->limit = min(max(10, (int)($queryParams['limit'] ?? 10)), 100);

        return $dto;
    }

    private static function normalizeArray($value): array
    {
        if (is_array($value)) {
            return $value;
        }
        if (is_string($value) && !empty($value)) {
            return [$value];
        }
        return [];
    }

    public function hasFilters(): bool
    {
        return !empty($this->profileId)
            || !empty($this->types)
            || !empty($this->statuses)
            || !empty($this->fromDate)
            || !empty($this->toDate);
    }
}