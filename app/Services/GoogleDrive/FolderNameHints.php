<?php

declare(strict_types=1);

namespace App\Services\GoogleDrive;

final class FolderNameHints
{
    /**
     * Parse year / program / place hints from a Drive folder name.
     *
     * @return array{year: int|null, program: string|null, place: string|null}
     */
    public function parse(string $name): array
    {
        $year = null;
        if (preg_match('/\b(20\d{2}|19\d{2})\b/', $name, $matches) === 1) {
            $year = (int) $matches[1];
        }

        $place = null;
        $knownPlaces = ['NYC', 'SF', 'TLV', 'Tel Aviv', 'Jerusalem', 'Boston', 'Chicago', 'LA', 'London'];
        foreach ($knownPlaces as $candidate) {
            if (preg_match('/\b'.preg_quote($candidate, '/').'\b/i', $name) === 1) {
                $place = $candidate;
                break;
            }
        }

        $program = null;
        $knownPrograms = ['Summer', 'Winter', 'Gap', 'Fellowship', 'Orientation', 'Graduation'];
        foreach ($knownPrograms as $candidate) {
            if (preg_match('/\b'.preg_quote($candidate, '/').'\b/i', $name) === 1) {
                $program = $candidate;
                break;
            }
        }

        return [
            'year' => $year,
            'program' => $program,
            'place' => $place,
        ];
    }
}
