<?php

namespace App\Import\Source;

use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

/** Toutes les sources d'import disponibles (implémentations de TradeSource). */
class SourceRegistry
{
    /**
     * @param iterable<TradeSource> $sources
     */
    public function __construct(#[AutowireIterator('app.import.source')] private readonly iterable $sources)
    {
    }

    /**
     * @return list<TradeSource>
     */
    public function all(): array
    {
        return [...$this->sources];
    }

    /**
     * @throws \InvalidArgumentException si la clé est inconnue
     */
    public function get(string $key): TradeSource
    {
        foreach ($this->all() as $source) {
            if ($source->key() === $key) {
                return $source;
            }
        }

        throw new \InvalidArgumentException(sprintf('Source inconnue "%s". %s', $key, $this->describe()));
    }

    /**
     * Trouve la source qui reconnaît le fichier.
     *
     * @throws \InvalidArgumentException si aucune, ou plusieurs, sources le reconnaissent
     */
    public function detect(string $path): TradeSource
    {
        $matching = array_values(array_filter($this->all(), static fn (TradeSource $s): bool => $s->supports($path)));

        if (1 === count($matching)) {
            return $matching[0];
        }

        throw new \InvalidArgumentException(sprintf(
            '%s "%s". Précisez le format avec --source. %s',
            [] === $matching ? 'Format non reconnu pour' : 'Plusieurs formats possibles pour',
            $path,
            $this->describe(),
        ));
    }

    private function describe(): string
    {
        return 'Formats disponibles : '.implode(' ; ', array_map(
            static fn (TradeSource $s): string => sprintf('%s (%s)', $s->key(), $s->label()),
            $this->all(),
        ));
    }
}
