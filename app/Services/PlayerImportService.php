<?php

namespace App\Services;

use App\Enums\PlayerPosition;
use App\Models\Club;
use App\Models\Player;
use App\Models\User;
use Illuminate\Support\Str;

class PlayerImportService
{
    /**
     * Import players from a CSV file path.
     *
     * @return array{imported: int, errors: list<string>}
     */
    public function importFromCsv(Club $club, string $filePath): array
    {
        if (! file_exists($filePath)) {
            return ['imported' => 0, 'errors' => ['Archivo no encontrado']];
        }

        $csv = array_map('str_getcsv', file($filePath));
        $headers = array_map(fn (string $h): string => mb_strtolower(trim($h)), array_shift($csv));

        $nameIndex = array_search('nombre', $headers, true);

        if ($nameIndex === false) {
            return ['imported' => 0, 'errors' => ['Columna "nombre" no encontrada en el CSV']];
        }

        $emailIndex = array_search('email', $headers, true);
        $positionIndex = array_search('posicion', $headers, true);

        $positionMap = $this->buildPositionMap();

        $importedCount = 0;
        $errors = [];

        foreach ($csv as $rowIndex => $row) {
            if (count($row) <= $nameIndex) {
                continue;
            }

            $name = trim($row[$nameIndex]);

            if ($name === '') {
                continue;
            }

            $email = ($emailIndex !== false && isset($row[$emailIndex]))
                ? trim($row[$emailIndex])
                : null;

            $positionRaw = ($positionIndex !== false && isset($row[$positionIndex]))
                ? trim($row[$positionIndex])
                : null;

            $userId = null;

            if ($email !== null && $email !== '') {
                $user = User::query()->where('email', $email)->first();
                $userId = $user?->id;
            }

            $position = null;

            if ($positionRaw !== null && $positionRaw !== '') {
                $position = $this->resolvePosition($positionRaw, $positionMap);
            }

            Player::query()->create([
                'ulid' => (string) Str::ulid(),
                'club_id' => $club->id,
                'user_id' => $userId,
                'name' => $name,
                'position' => $position,
                'is_active' => true,
            ]);

            $importedCount++;
        }

        return ['imported' => $importedCount, 'errors' => $errors];
    }

    /**
     * @return array<string, PlayerPosition>
     */
    private function buildPositionMap(): array
    {
        $map = [];

        foreach (PlayerPosition::cases() as $case) {
            $map[mb_strtoupper($case->value)] = $case;
            $map[mb_strtolower($case->label())] = $case;
        }

        return $map;
    }

    /**
     * @param  array<string, PlayerPosition>  $positionMap
     */
    private function resolvePosition(string $raw, array $positionMap): ?PlayerPosition
    {
        $key = mb_strtoupper($raw);

        return $positionMap[$key]
            ?? $positionMap[mb_strtolower($raw)]
            ?? PlayerPosition::tryFrom($raw)
            ?? null;
    }
}
