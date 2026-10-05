<?php

namespace App\Import\Reader;

use PhpOffice\PhpSpreadsheet\Reader\Exception as ReaderException;
use PhpOffice\PhpSpreadsheet\Reader\Ods;

/** Lit toutes les feuilles d'un ODS et renvoie les lignes non vides, sans en-tête, cellules converties en chaînes. */
class OdsFileReader
{
    /**
     * @return \Generator<string, list<string>> indexé par "feuille!ligne", ex. "Feuil2!7"
     *
     * @throws \InvalidArgumentException si le fichier est illisible
     */
    public function read(string $path): \Generator
    {
        $reader = new Ods();
        $reader->setReadDataOnly(true);

        try {
            $spreadsheet = $reader->load($path);
        } catch (ReaderException $e) {
            throw new \InvalidArgumentException(sprintf('Fichier ODS illisible "%s" : %s', $path, $e->getMessage()), 0, $e);
        }

        try {
            foreach ($spreadsheet->getWorksheetIterator() as $sheet) {
                foreach ($sheet->toArray(null, false, false, false) as $index => $cells) {
                    $row = array_map($this->toString(...), $cells);

                    if ('' === implode('', $row)) {
                        continue;
                    }

                    yield sprintf('%s!%d', $sheet->getTitle(), $index + 1) => $row;
                }
            }
        } finally {
            $spreadsheet->disconnectWorksheets();
        }
    }

    private function toString(mixed $cell): string
    {
        return trim((string) $cell);
    }
}
