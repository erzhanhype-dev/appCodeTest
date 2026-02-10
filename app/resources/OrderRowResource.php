<?php
namespace App\Resources;

final class OrderRowResource
{
    public static function collection(iterable $rows): array
    {
        $out = [];
        foreach ($rows as $row) {
            $out[] = self::fromRow($row);
        }
        return $out;
    }

    public static function fromRow(object|array $row): array
    {
        $g = static fn(string $k) => is_array($row) ? ($row[$k] ?? null) : ($row->$k ?? null);

        return [
            'id'         => $g('p_id'),
            'name'       => $g('p_name'),
            'type'       => $g('p_type'),
            'created'    => self::fmtDate($g('p_created')),
            'blocked'    => (bool) ($g('p_blocked') ?? 0),
            'agent_name' => $g('p_agent_name'),
            'sign_date'  => self::fmtDateOrDash($g('p_sign_date')),
            'transaction' => [
                'id'      => $g('tr_id'),
                'status'  => $g('tr_status'),
                'amount'  => self::fmtMoney((float) ($g('tr_amount') ?? 0)),
                'approve' => $g('tr_approve'),
                'dt_sent' => self::fmtDateOrDash($g('tr_dt_sent')),
            ],
        ];
    }

    private static function fmtDate(int|string|null $ts): string
    {
        $t = (int) ($ts ?? 0);
        if ($t <= 0) {
            return '—';
        }
        $dt = new \DateTime("@$t");
        $dt->setTimezone(new \DateTimeZone('Asia/Almaty'));
        return $dt->format('d.m.Y H:i');
    }

    private static function fmtDateOrDash(int|string|null $ts): string
    {
        $t = (int) ($ts ?? 0);
        return $t > 0 ? self::fmtDate($t) : '—';
    }

    private static function fmtMoney(float $num): string
    {
        return number_format($num, 2, ',', "\u{00A0}");
    }
}
