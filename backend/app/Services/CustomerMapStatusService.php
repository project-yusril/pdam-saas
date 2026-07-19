<?php

namespace App\Services;

use App\Models\Bill;
use App\Models\Customer;
use App\Models\Disconnection;

class CustomerMapStatusService
{
    public function getStatusColor(Customer $customer): string
    {
        if ($customer->status === 'disconnected') {
            $recent = Disconnection::where('customer_id', $customer->id)
                ->where('status', 'active')
                ->exists();
            if ($recent) {
                return 'black';
            }
        }

        $now = now();
        $unpaid = Bill::where('customer_id', $customer->id)
            ->whereIn('status', ['unpaid', 'overdue'])
            ->orderByDesc('period')
            ->get();

        if ($unpaid->isEmpty()) {
            return 'green';
        }

        $overdueMonths = $unpaid->filter(fn ($b) => $b->due_date && $b->due_date->lt($now))->count();
        $lastUnpaid = $unpaid->first();
        $isCurrentDue = $lastUnpaid && $lastUnpaid->due_date && $lastUnpaid->due_date->gte($now->startOfMonth());

        if ($overdueMonths >= 3) {
            return 'red';
        }
        if ($overdueMonths >= 2) {
            return 'yellow';
        }
        if ($isCurrentDue || $overdueMonths === 1) {
            return 'white';
        }

        return 'green';
    }

    public function getArrearsMonths(Customer $customer): int
    {
        return Bill::where('customer_id', $customer->id)
            ->whereIn('status', ['unpaid', 'overdue'])
            ->where('due_date', '<', now())
            ->count();
    }

    public function getTotalArrears(Customer $customer): float
    {
        return (float) Bill::where('customer_id', $customer->id)
            ->whereIn('status', ['unpaid', 'overdue'])
            ->sum('amount_due');
    }

    public function buildGeoJsonFeature(Customer $customer): array
    {
        $color = $this->getStatusColor($customer);
        $arrearsMonths = $this->getArrearsMonths($customer);

        $colorMap = [
            'green' => '#22c55e',
            'white' => '#94a3b8',
            'yellow' => '#eab308',
            'red' => '#ef4444',
            'black' => '#1e293b',
        ];

        return [
            'type' => 'Feature',
            'geometry' => [
                'type' => 'Point',
                'coordinates' => [(float) $customer->longitude, (float) $customer->latitude],
            ],
            'properties' => [
                'id' => $customer->id,
                'customer_number' => $customer->customer_number,
                'name' => $customer->full_name,
                'status_color' => $color,
                'status_label' => $this->colorLabel($color),
                'marker_color' => $colorMap[$color],
                'icon' => $this->iconForColor($color),
                'arrears_months' => $arrearsMonths,
                'total_arrears' => $this->getTotalArrears($customer),
                'customer_status' => $customer->status,
            ],
        ];
    }

    private function colorLabel(string $color): string
    {
        return match ($color) {
            'green' => 'Lunas',
            'white' => 'Belum Bayar',
            'yellow' => 'Menunggak 2 Bulan',
            'red' => 'Menunggak 3+ Bulan',
            'black' => 'Isolir',
            default => 'Unknown',
        };
    }

    private function iconForColor(string $color): string
    {
        return match ($color) {
            'green' => 'marker-green',
            'white' => 'marker-white',
            'yellow' => 'marker-yellow',
            'red' => 'marker-red',
            'black' => 'marker-black',
            default => 'marker-default',
        };
    }
}
