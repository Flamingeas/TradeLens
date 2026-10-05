<?php

namespace App\Import\Reader;

/**
 * Lit un CSV de trades avec en-tête et renvoie chaque ligne sous forme [colonne => valeur].
 * Une ligne entièrement entourée de guillemets est relue comme une ligne CSV.
 */
class CsvFileReader
{
    /**
     * @return \Generator<int, array<string, string>> indexé par numéro de ligne dans le fichier
     *
     * @throws \InvalidArgumentException si le fichier est illisible ou une ligne n'a pas le bon nombre de colonnes
     */
    public function read(string $path): \Generator
    {
        $handle = @fopen($path, 'r');
        if (false === $handle) {
            throw new \InvalidArgumentException(sprintf('Fichier CSV illisible : "%s".', $path));
        }

        try {
            $header = null;
            $lineNumber = 0;

            while (false !== ($line = fgets($handle))) {
                ++$lineNumber;
                $line = rtrim($line, "\r\n");
                if (1 === $lineNumber) {
                    $line = preg_replace('/^\xEF\xBB\xBF/', '', $line) ?? $line;
                }
                if ('' === trim($line)) {
                    continue;
                }

                if (null === $header) {
                    $header = array_map('trim', str_getcsv($line, escape: ''));
                    continue;
                }

                $fields = str_getcsv($line, escape: '');
                if (1 === count($fields) && count($header) > 1) {
                    $fields = str_getcsv((string) $fields[0], escape: '');
                }

                if (count($fields) !== count($header)) {
                    throw new \InvalidArgumentException(sprintf(
                        'Ligne %d : %d colonnes trouvées, %d attendues.',
                        $lineNumber,
                        count($fields),
                        count($header),
                    ));
                }

                yield $lineNumber => array_combine($header, array_map('trim', $fields));
            }
        } finally {
            fclose($handle);
        }
    }
}
