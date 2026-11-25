<?php

namespace App\Support;

final class HomeVisitLocations
{
    public const string COUNTRY = 'Nigeria';

    /**
     * States where a home visit can be booked, and the local government areas in each state.
     *
     * @return array<string, list<string>>
     */
    public static function supported(): array
    {
        return [
            'Lagos' => [
                'Agege',
                'Ajeromi-Ifelodun',
                'Alimosho',
                'Amuwo-Odofin',
                'Apapa',
                'Badagry',
                'Epe',
                'Eti-Osa',
                'Ibeju-Lekki',
                'Ifako-Ijaiye',
                'Ikeja',
                'Ikorodu',
                'Kosofe',
                'Lagos Island',
                'Lagos Mainland',
                'Mushin',
                'Ojo',
                'Oshodi-Isolo',
                'Shomolu',
                'Surulere',
            ],
        ];
    }

    public static function canonicalState(string $value): ?string
    {
        $value = trim($value);

        foreach (array_keys(self::supported()) as $state) {
            if (strcasecmp($state, $value) === 0) {
                return $state;
            }
        }

        return null;
    }

    public static function canonicalArea(string $state, string $value): ?string
    {
        $state = self::canonicalState($state);

        if ($state === null) {
            return null;
        }

        $value = trim($value);

        foreach (self::supported()[$state] as $area) {
            if (strcasecmp($area, $value) === 0) {
                return $area;
            }
        }

        return null;
    }
}
