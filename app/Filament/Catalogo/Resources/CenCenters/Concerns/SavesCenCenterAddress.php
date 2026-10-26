<?php

namespace App\Filament\Catalogo\Resources\CenCenters\Concerns;

use App\Models\CenAddress;
use App\Models\CenCenter;

trait SavesCenCenterAddress
{
    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function extractAddressFormData(array &$data): array
    {
        $address = [
            'via' => $data['address_via'] ?? null,
            'direccion' => $data['address_direccion'] ?? null,
            'numero' => $data['address_numero'] ?? null,
            'telefono' => $data['address_telefono'] ?? null,
            'latitud' => $data['address_latitud'] ?? null,
            'longitud' => $data['address_longitud'] ?? null,
            'cen_commune_id' => $data['address_cen_commune_id'] ?? null,
        ];

        unset(
            $data['address_via'],
            $data['address_direccion'],
            $data['address_numero'],
            $data['address_telefono'],
            $data['address_latitud'],
            $data['address_longitud'],
            $data['address_cen_commune_id'],
        );

        return $address;
    }

    /**
     * @param  array<string, mixed>  $address
     */
    protected function persistCenAddress(CenCenter $center, array $address): void
    {
        $via = $address['via'] ?? null;

        if ($via === null || $via === '') {
            return;
        }

        CenAddress::updateOrCreate(
            ['cen_center_id' => $center->id],
            [
                'cen_commune_id' => $address['cen_commune_id'],
                'via' => $via,
                'numero' => $address['numero'],
                'direccion' => $address['direccion'],
                'telefono' => $address['telefono'],
                'latitud' => $address['latitud'],
                'longitud' => $address['longitud'],
            ]
        );
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mergeAddressIntoFormData(CenCenter $center, array $data): array
    {
        $address = $center->cenAddress;

        if ($address === null) {
            return $data;
        }

        $data['address_via'] = $address->via?->value ?? $address->via;
        $data['address_direccion'] = $address->direccion;
        $data['address_numero'] = $address->numero;
        $data['address_telefono'] = $address->telefono;
        $data['address_latitud'] = $address->latitud;
        $data['address_longitud'] = $address->longitud;
        $data['address_cen_commune_id'] = $address->cen_commune_id;

        return $data;
    }
}
