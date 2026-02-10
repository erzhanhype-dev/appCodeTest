<?php
namespace App\Services\Order\Dto;

final class OrderCreateDTO
{
    public function __construct(
        public string $type,
        public string $comment,
        public string $agent_status,
    ) {}

    public static function fromRequest(\Phalcon\Http\Request $r): self
    {
        $type    = (string)$r->getPost('order_type', 'string');
        $comment = trim((string)$r->getPost('order_comment', 'string')) ?: '(добавлено через форму)';

        return new self(
            type: $type,
            comment: $comment,
            agent_status: (string)$r->getPost('agent_status', 'string'),
        );
    }
}
