<?php

declare(strict_types=1);

use App\Services\GoogleDrive\FolderNameHints;

it('parses year, program and place from a folder name', function () {
    $hints = (new FolderNameHints)->parse('2026 Summer SF');

    expect($hints)->toMatchArray([
        'year' => 2026,
        'program' => 'Summer',
        'place' => 'SF',
    ]);
});

it('returns nulls when no hints are present', function () {
    $hints = (new FolderNameHints)->parse('Misc dumps');

    expect($hints)->toMatchArray([
        'year' => null,
        'program' => null,
        'place' => null,
    ]);
});
